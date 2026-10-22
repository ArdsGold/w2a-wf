<?php
/**
 * Plugin Name: Wolf Forge Article Generator
 * Description: Import .docx files as WordPress articles with bulk upload, formatting cleanup, scheduling, and optional media matching.
 * Version: r4.0.0
 * Author: Macky Villafuerte, Arden Guinto
 * License: GPL v2 or later
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: wolf-forge-article-generator
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WTAI_VERSION', 'r4.0.0');
define('WTAI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WTAI_PLUGIN_URL', plugin_dir_url(__FILE__));

class Word_To_Article_Importer {
    private const Plugin_Icon = "icon.png";
    private static $instance = null;
    private $error_handler;
    private $admin_hooks = array();

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->includes();
        WTAI_Media_Index::register_hooks();
        add_shortcode('wtai_gbp_photo', array('WTAI_GBP_Photos', 'shortcode'));
        $this->error_handler = new WTAI_Error_Handler();
        add_action('admin_init', array($this, 'maybe_upgrade'));
        add_action('admin_menu', array($this, 'redirect_legacy_settings'), 1);
        add_action('admin_post_wtai_trash_duplicate', array($this, 'trash_duplicate'));

        register_activation_hook(__FILE__, array($this, 'activate'));

        if (is_admin()) {
            add_action('admin_menu', array($this, 'add_admin_menu'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        }

        add_action('wp_ajax_wtai_upload_documents', array($this, 'ajax_upload_documents'));
        add_action('wp_ajax_wtai_validate_import', array($this, 'ajax_validate_import'));
        add_action('wp_ajax_wtai_save_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_wtai_get_media_images', array($this, 'ajax_get_media_images'));
        add_action('wp_ajax_wtai_preview_images', array($this, 'ajax_preview_images'));
        add_action('wp_ajax_wtai_refresh_media_index', array($this, 'ajax_refresh_media_index'));
        add_action('wp_ajax_wtai_google_photos', array($this, 'ajax_google_photos'));
        add_action('wp_ajax_wtai_google_drive', array($this, 'ajax_google_drive'));
        add_action('wp_ajax_wtai_pixabay', array($this, 'ajax_pixabay'));
        add_action('wp_ajax_wtai_get_import_history', array($this, 'ajax_get_import_history'));
        add_action('wp_ajax_wtai_trash_import', array($this, 'ajax_trash_import'));
        add_action('wp_ajax_wtai_clear_import_history', array($this, 'ajax_clear_import_history'));
        add_action('wp_ajax_wtai_reset_settings', array($this, 'ajax_reset_settings'));
        add_action('wp_ajax_wtai_get_error_stats', array($this, 'ajax_get_error_stats'));
        add_action('wp_ajax_wtai_clear_error_logs', array($this, 'ajax_clear_error_logs'));
    }

    private function includes() {
        require_once WTAI_PLUGIN_DIR . 'includes/class-settings.php';
        require_once WTAI_PLUGIN_DIR . 'includes/class-scheduler.php';
        require_once WTAI_PLUGIN_DIR . 'includes/class-seo.php';
        require_once WTAI_PLUGIN_DIR . 'includes/class-importer.php';
        require_once WTAI_PLUGIN_DIR . 'includes/class-internal-linker.php';
        require_once WTAI_PLUGIN_DIR . 'includes/class-duplicate-pages.php';
        require_once WTAI_PLUGIN_DIR . 'includes/class-error-handler.php';
        require_once WTAI_PLUGIN_DIR . 'includes/class-docx-parser.php';
        require_once WTAI_PLUGIN_DIR . 'includes/class-content-processor.php';
        require_once WTAI_PLUGIN_DIR . 'includes/class-image-matcher.php';
        require_once WTAI_PLUGIN_DIR . 'includes/class-media-index.php';
        require_once WTAI_PLUGIN_DIR . 'includes/class-gbp-photos.php';
        require_once WTAI_PLUGIN_DIR . 'includes/class-google-drive.php';
        require_once WTAI_PLUGIN_DIR . 'includes/class-pixabay.php';
        require_once WTAI_PLUGIN_DIR . 'includes/class-server-info.php';
    }

    public function get_error_handler() {
        return $this->error_handler;
    }

    public function maybe_upgrade() {
        if (get_option('wtai_schema_version') !== WTAI_VERSION && current_user_can('manage_options')) {
            $this->activate();
        }
    }

    public function activate() {
        $this->create_tables();
        $this->set_default_options();
        update_option('wtai_schema_version', WTAI_VERSION, false);
    }

    private function create_tables() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wtai_import_history';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL,
            original_filename varchar(255) NOT NULL,
            import_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            settings longtext NOT NULL,
            status varchar(50) NOT NULL DEFAULT 'completed',
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY import_date (import_date)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    private function set_default_options() {
        foreach (WTAI_Settings::defaults() as $key => $value) {
            if (null === get_option('wtai_' . $key, null)) {
                add_option('wtai_' . $key, $value);
            }
        }
    }

    public function add_admin_menu() {
        $this->admin_hooks[] = add_menu_page(
            __('Wolf Forge Article Generator', 'wolf-forge-article-generator'),
            __('Wolf Forge Articles', 'wolf-forge-article-generator'),
            'manage_options',
            'word-to-article-importer',
            array($this, 'render_admin_page'),
            WTAI_PLUGIN_URL . self::Plugin_Icon,
            59
        );

    }

    public function enqueue_admin_scripts($hook) {
        wp_add_inline_style('admin-menu', '#adminmenu #toplevel_page_word-to-article-importer .wp-menu-image img { width: 20px; height: 20px; object-fit: contain; }');
        if (!in_array($hook, $this->admin_hooks, true)) {
            return;
        }

        $css_version = WTAI_VERSION . '-' . md5_file(WTAI_PLUGIN_DIR . 'assets/css/admin.css');
        $js_version = WTAI_VERSION . '-' . md5_file(WTAI_PLUGIN_DIR . 'assets/js/admin.js');
        wp_enqueue_style('wtai-admin-style', WTAI_PLUGIN_URL . 'assets/css/admin.css', array(), $css_version);
        wp_enqueue_script('wtai-admin-script', WTAI_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), $js_version, true);
        wp_localize_script('wtai-admin-script', 'wtai_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wtai_ajax_nonce'),
            'max_upload_size' => wp_max_upload_size(),
            'edit_post_url' => admin_url('post.php') . '?post=%d&action=edit',
            'strings' => array(
                'select_file' => __('Please select at least one .docx file.', 'wolf-forge-article-generator'),
                'invalid_file' => __('Only .docx files are supported.', 'wolf-forge-article-generator'),
                'uploading' => __('Importing documents...', 'wolf-forge-article-generator'),
                'unknown_error' => __('An unexpected error occurred.', 'wolf-forge-article-generator'),
            ),
        ));
    }

    public function render_admin_page() {
        if (!current_user_can('manage_options')) return;
        require WTAI_PLUGIN_DIR . 'admin/views/admin-page.php';
    }

    public function trash_duplicate() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') wp_die(esc_html__('Invalid request.', 'wolf-forge-article-generator'), '', array('response' => 405));
        if (!current_user_can('manage_options')) wp_die(esc_html__('Permission denied.', 'wolf-forge-article-generator'), '', array('response' => 403));
        $post_id = isset($_POST['post_id']) && is_scalar($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        check_admin_referer('wtai_trash_duplicate_' . $post_id);
        $result = WTAI_Duplicate_Pages::trash($post_id);
        if (is_wp_error($result)) wp_die(esc_html($result->get_error_message()), '', array('response' => 400, 'back_link' => true));
        $page = isset($_POST['duplicate_page']) && is_scalar($_POST['duplicate_page']) ? max(1, absint($_POST['duplicate_page'])) : 1;
        wp_safe_redirect(add_query_arg(array('page' => 'word-to-article-importer', 'tab' => 'duplicates', 'duplicate_page' => $page, 'duplicate_trashed' => 1), admin_url('admin.php')));
        exit;
    }

    public function redirect_legacy_settings() {
        if (isset($_GET['page']) && $_GET['page'] === 'word-to-article-importer-settings') {
            wp_safe_redirect(admin_url('admin.php?page=word-to-article-importer&tab=settings'));
            exit;
        }
    }

    private function normalize_files($files) {
        if (empty($files) || !isset($files['name'])) {
            return array();
        }

        $normalized = array();
        $names = is_array($files['name']) ? $files['name'] : array($files['name']);
        $tmp_names = is_array($files['tmp_name']) ? $files['tmp_name'] : array($files['tmp_name']);
        $errors = is_array($files['error']) ? $files['error'] : array($files['error']);
        $sizes = is_array($files['size']) ? $files['size'] : array($files['size']);
        $types = is_array($files['type']) ? $files['type'] : array($files['type']);

        foreach ($names as $i => $name) {
            $normalized[] = array(
                'name' => sanitize_file_name($name),
                'tmp_name' => isset($tmp_names[$i]) ? $tmp_names[$i] : '',
                'error' => isset($errors[$i]) ? (int) $errors[$i] : UPLOAD_ERR_NO_FILE,
                'size' => isset($sizes[$i]) ? (int) $sizes[$i] : 0,
                'type' => isset($types[$i]) ? sanitize_text_field($types[$i]) : '',
            );
        }
        return $normalized;
    }

    private function validate_docx_upload($file, $require_upload = true) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('wtai_upload_error', $this->upload_error_message($file['error']));
        }
        if (!$file['tmp_name'] || ($require_upload && !is_uploaded_file($file['tmp_name']))) {
            return new WP_Error('wtai_invalid_upload', __('The uploaded file is invalid.', 'wolf-forge-article-generator'));
        }
        if ($file['size'] <= 0) {
            return new WP_Error('wtai_empty_file', __('The uploaded file is empty.', 'wolf-forge-article-generator'));
        }
        if ($file['size'] > wp_max_upload_size()) {
            return new WP_Error('wtai_file_too_large', __('The file exceeds the server upload limit.', 'wolf-forge-article-generator'));
        }
        if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'docx') {
            return new WP_Error('wtai_invalid_file_type', __('Only .docx files are supported.', 'wolf-forge-article-generator'));
        }

        $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
        if ($finfo) {
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            $allowed = array('application/zip', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/octet-stream');
            if ($mime && !in_array($mime, $allowed, true)) {
                return new WP_Error('wtai_invalid_file_type', __('The uploaded file is not a valid DOCX/ZIP file.', 'wolf-forge-article-generator'));
            }
        }
        return true;
    }

    private function upload_error_message($code) {
        $messages = array(
            UPLOAD_ERR_INI_SIZE => __('The file exceeds the server upload limit.', 'wolf-forge-article-generator'),
            UPLOAD_ERR_FORM_SIZE => __('The file exceeds the form upload limit.', 'wolf-forge-article-generator'),
            UPLOAD_ERR_PARTIAL => __('The file was only partially uploaded.', 'wolf-forge-article-generator'),
            UPLOAD_ERR_NO_FILE => __('No file was uploaded.', 'wolf-forge-article-generator'),
            UPLOAD_ERR_NO_TMP_DIR => __('The server is missing its temporary upload directory.', 'wolf-forge-article-generator'),
            UPLOAD_ERR_CANT_WRITE => __('The server could not write the uploaded file.', 'wolf-forge-article-generator'),
            UPLOAD_ERR_EXTENSION => __('A server extension stopped the upload.', 'wolf-forge-article-generator'),
        );
        return isset($messages[$code]) ? $messages[$code] : __('The file upload failed.', 'wolf-forge-article-generator');
    }

    private function authorize_ajax() {
        check_ajax_referer('wtai_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'wolf-forge-article-generator')), 403);
        }
    }

    public function ajax_validate_import() {
        $this->authorize_ajax();
        $settings = WTAI_Settings::for_import(isset($_POST['settings']) ? $_POST['settings'] : array());
        $count = isset($_POST['document_count']) && is_scalar($_POST['document_count']) ? (int) $_POST['document_count'] : 0;
        if (!empty($_POST['preview'])) {
            $preview = WTAI_Scheduler::preview($settings, $count);
            if (is_wp_error($preview)) wp_send_json_error(array('message' => $preview->get_error_message()), 400);
            wp_send_json_success(array('preview' => $preview));
        }
        $dates = WTAI_Scheduler::calculate($settings, $count);
        if (is_wp_error($dates)) wp_send_json_error(array('message' => $dates->get_error_message()), 400);
        wp_send_json_success(array('total' => $count, 'settings' => $settings));
    }

    public function ajax_save_settings() {
        $this->authorize_ajax();
        if (!isset($_POST['settings']) || !is_array($_POST['settings'])) {
            wp_send_json_error(array('message' => __('No options were submitted.', 'wolf-forge-article-generator')), 400);
        }
        $settings = WTAI_Settings::save($_POST['settings']);
        if (is_wp_error($settings)) wp_send_json_error(array('message' => $settings->get_error_message()), 500);
        wp_send_json_success(array('settings' => $settings));
    }

    public function ajax_upload_documents() {
        $this->authorize_ajax();
        $files = $this->normalize_files(isset($_FILES['documents']) ? $_FILES['documents'] : array());
        if (count($files) !== 1) {
            wp_send_json_error(array('message' => __('Upload one document per request. Reload the import page to update the interface.', 'wolf-forge-article-generator')), 400);
        }
        $settings = WTAI_Settings::for_import(isset($_POST['settings']) ? $_POST['settings'] : array());
        $count = isset($_POST['document_count']) && is_scalar($_POST['document_count']) ? (int) $_POST['document_count'] : 1;
        $index = isset($_POST['document_index']) && is_scalar($_POST['document_index']) ? (int) $_POST['document_index'] : 0;
        $dates = WTAI_Scheduler::calculate($settings, $count);
        if (is_wp_error($dates) || $index < 0 || $index >= $count) {
            $message = is_wp_error($dates) ? $dates->get_error_message() : __('Invalid document position.', 'wolf-forge-article-generator');
            wp_send_json_error(array('message' => $message), 400);
        }
        $valid = $this->validate_docx_upload($files[0]);
        if (is_wp_error($valid)) wp_send_json_error(array('message' => $valid->get_error_message()), 400);

        try {
            $result = (new WTAI_Importer($this->error_handler))->import($files[0], $settings, $dates[$index]);
            wp_send_json_success($result);
        } catch (Throwable $error) {
            $this->error_handler->log_error(WTAI_Error_Handler::ERR_UNKNOWN_ERROR, array('file' => $files[0]['name'], 'error' => $error->getMessage()));
            wp_send_json_error(array('message' => $error->getMessage()), 500);
        }
    }

    public function ajax_pixabay() {
        $this->authorize_ajax();
        $operation = isset($_POST['operation']) && is_string($_POST['operation']) ? $_POST['operation'] : '';
        if ($operation === 'status') $result = WTAI_Pixabay::status();
        elseif ($operation === 'remove_key') $result = WTAI_Pixabay::remove_key();
        elseif ($operation === 'save_key') {
            $key = isset($_POST['key']) && is_string($_POST['key']) ? trim(wp_unslash($_POST['key'])) : '';
            $result = WTAI_Pixabay::save_key($key);
        } else $result = new WP_Error('wtai_pixabay_operation', __('Unknown Pixabay operation.', 'wolf-forge-article-generator'));
        if (is_wp_error($result)) wp_send_json_error(array('message' => $result->get_error_message()), 400);
        wp_send_json_success($result);
    }

    public function ajax_google_drive() {
        $this->authorize_ajax();
        $input = function ($key) { return isset($_POST[$key]) && is_string($_POST[$key]) ? wp_unslash($_POST[$key]) : ''; };
        $operation = $input('operation');
        $result = null;
        if ($operation === 'save_key') $result = WTAI_Google_Drive::save_key(trim($input('key')));
        elseif ($operation === 'remove_key') $result = WTAI_Google_Drive::remove_key();
        elseif ($operation === 'status') $result = WTAI_Google_Drive::status();
        elseif ($operation === 'list') $result = WTAI_Google_Drive::files($input('folder_url'), $input('page_token'));
        elseif ($operation === 'import') {
            $settings = WTAI_Settings::for_import(isset($_POST['settings']) ? $_POST['settings'] : array());
            $count = (int) $input('document_count');
            $index = (int) $input('document_index');
            $dates = WTAI_Scheduler::calculate($settings, $count);
            if (is_wp_error($dates)) $result = $dates;
            elseif ($index < 0 || $index >= $count) $result = new WP_Error('wtai_drive_position', __('Invalid document position.', 'wolf-forge-article-generator'));
            else {
                $file = WTAI_Google_Drive::download($input('file_id'), $input('resource_key'));
                if (is_wp_error($file)) $result = $file;
                else {
                    try {
                        $valid = $this->validate_docx_upload($file, false);
                        $result = is_wp_error($valid) ? $valid : (new WTAI_Importer($this->error_handler))->import($file, $settings, $dates[$index]);
                    } catch (Throwable $error) {
                        $result = new WP_Error('wtai_drive_import', $error->getMessage());
                    } finally {
                        wp_delete_file($file['tmp_name']);
                    }
                }
            }
        } else $result = new WP_Error('wtai_drive_operation', __('Unknown Google Drive operation.', 'wolf-forge-article-generator'));
        if (is_wp_error($result)) wp_send_json_error(array('message' => $result->get_error_message(), 'code' => $result->get_error_code(), 'drive_status' => WTAI_Google_Drive::status()), $result->get_error_code() === 'wtai_drive_quota' ? 429 : 400);
        if ($operation === 'import') $result['drive_status'] = WTAI_Google_Drive::status();
        wp_send_json_success($result);
    }

    public function ajax_get_media_images() {
        check_ajax_referer('wtai_ajax_nonce', 'nonce');
        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'wolf-forge-article-generator')), 403);
        }

        $search = isset($_GET['search']) ? sanitize_text_field(wp_unslash($_GET['search'])) : '';
        $page = max(1, isset($_GET['page']) ? absint($_GET['page']) : 1);
        $data = (new WTAI_Image_Matcher($this->error_handler))->get_media_images($search, $page, 20);
        wp_send_json_success($data);
    }

    private function authorize_media() {
        $this->authorize_ajax();
        if (!current_user_can('upload_files')) wp_send_json_error(array('message' => __('Media Library access is required.', 'wolf-forge-article-generator')), 403);
    }

    public function ajax_refresh_media_index() {
        $this->authorize_media();
        WTAI_Media_Index::invalidate();
        $index = WTAI_Media_Index::get();
        wp_send_json_success(array('count' => $index['count']));
    }

    public function ajax_preview_images() {
        $this->authorize_media();
        $content = isset($_POST['content']) && is_string($_POST['content']) ? wp_unslash($_POST['content']) : '';
        if (strlen($content) > 200000 || trim(wp_strip_all_tags($content)) === '') {
            wp_send_json_error(array('message' => __('Paste article text or HTML, up to 200 KB.', 'wolf-forge-article-generator')), 400);
        }
        $title = isset($_POST['title']) && is_string($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
        $content = wp_kses_post(wpautop($content));
        $settings = WTAI_Settings::for_import(isset($_POST['settings']) ? $_POST['settings'] : array());
        $matcher = new WTAI_Image_Matcher($this->error_handler);
        $html = !empty($settings['auto_insert_images']) ? $matcher->insert_images($content, $settings, $title) : $content;
        $report = $matcher->get_report();
        if (!$report) $report = array('requested' => $settings['max_images_per_post'], 'inserted' => 0, 'placements' => array(), 'message' => __('Automatic image insertion is disabled.', 'wolf-forge-article-generator'));
        wp_send_json_success(array('content' => wp_kses_post(WTAI_GBP_Photos::preview($html)), 'report' => $report));
    }

    public function ajax_google_photos() {
        $this->authorize_ajax();
        $operation = isset($_POST['operation']) && is_string($_POST['operation']) ? $_POST['operation'] : 'check';
        if ($operation === 'save_key' || $operation === 'remove_key') {
            if (defined('WTAI_GOOGLE_PLACES_API_KEY')) wp_send_json_error(array('message' => __('The Google API key is managed in wp-config.php.', 'wolf-forge-article-generator')), 400);
            if ($operation === 'remove_key') {
                delete_option('wtai_google_places_api_key');
                if (WTAI_GBP_Photos::has_key()) wp_send_json_error(array('message' => __('Google API key could not be removed. Please retry.', 'wolf-forge-article-generator')), 500);
            }
            else {
                $key = isset($_POST['key']) && is_string($_POST['key']) ? trim(wp_unslash($_POST['key'])) : '';
                if (!preg_match('/^[A-Za-z0-9_-]{20,200}$/D', $key)) wp_send_json_error(array('message' => __('Enter a valid Google Places API key.', 'wolf-forge-article-generator')), 400);
                update_option('wtai_google_places_api_key', $key, false);
                if (get_option('wtai_google_places_api_key') !== $key) wp_send_json_error(array('message' => __('Google API key could not be saved. Please retry.', 'wolf-forge-article-generator')), 500);
            }
            wp_send_json_success(array('message' => WTAI_GBP_Photos::has_key() ? __('Google API key saved.', 'wolf-forge-article-generator') : __('Google API key removed. Live Google photos will no longer load.', 'wolf-forge-article-generator')));
        }
        try {
            $settings = WTAI_Settings::read();
            $profile = WTAI_GBP_Photos::profile($settings['gbp_profile_url']);
            wp_send_json_success(array('message' => sprintf(__('%s: %d photos available from Google. Confirm this is the intended business.', 'wolf-forge-article-generator'), $profile['displayName']['text'] ?? $profile['id'], count($profile['photos'])), 'content' => count($profile['photos']) ? WTAI_GBP_Photos::shortcode(array('place' => $profile['id'], 'slot' => 0)) : ''));
        } catch (Throwable $error) {
            wp_send_json_error(array('message' => $error->getMessage()), 400);
        }
    }

    public function ajax_get_import_history() {
        $this->authorize_ajax();
        wp_send_json_success(array('history' => $this->get_import_history()));
    }

    public function get_import_history() {
        if (!current_user_can('manage_options')) return array();
        global $wpdb;
        $table = $wpdb->prefix . 'wtai_import_history';
        $history = $wpdb->get_results("SELECT id, post_id, original_filename, import_date, status FROM {$table} ORDER BY import_date DESC, id DESC LIMIT 10", ARRAY_A) ?: array();
        foreach ($history as &$item) {
            $post = get_post($item['post_id']);
            $readable = $post && current_user_can('read_post', $post->ID);
            $available = $readable && !in_array($post->post_status, array('trash', 'auto-draft'), true);
            $item['post_title'] = $readable ? (get_the_title($post) ?: __('Untitled', 'wolf-forge-article-generator')) : '';
            $item['post_status'] = $post ? $post->post_status : 'deleted';
            $item['view_url'] = $available ? (in_array($post->post_status, array('draft', 'pending', 'future'), true) ? get_preview_post_link($post) : get_permalink($post)) : '';
            $item['edit_url'] = $available ? get_edit_post_link($post->ID, 'raw') : '';
            $item['can_trash'] = $available && $post->post_type === 'post' && current_user_can('delete_post', $post->ID) && (!defined('EMPTY_TRASH_DAYS') || (int) EMPTY_TRASH_DAYS > 0);
        }
        unset($item);
        return $history ?: array();
    }

    public function trash_import($post_id) {
        if (!current_user_can('manage_options') || !current_user_can('delete_post', $post_id)) return new WP_Error('wtai_import_permission', __('Permission denied.', 'wolf-forge-article-generator'));
        if (defined('EMPTY_TRASH_DAYS') && (int) EMPTY_TRASH_DAYS === 0) return new WP_Error('wtai_trash_disabled', __('WordPress Trash is disabled. No post was deleted.', 'wolf-forge-article-generator'));
        global $wpdb;
        $imported = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}wtai_import_history WHERE post_id = %d LIMIT 1", $post_id));
        if (!$imported || get_post_type($post_id) !== 'post' || get_post_status($post_id) === 'trash') return new WP_Error('wtai_invalid_import', __('This imported post is unavailable. Refresh the history and retry.', 'wolf-forge-article-generator'));
        if (!wp_trash_post($post_id)) return new WP_Error('wtai_import_trash_failed', __('The post could not be moved to Trash.', 'wolf-forge-article-generator'));
        return true;
    }

    public function ajax_trash_import() {
        $this->authorize_ajax();
        $post_id = isset($_POST['post_id']) && is_scalar($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $result = $this->trash_import($post_id);
        if (is_wp_error($result)) wp_send_json_error(array('message' => $result->get_error_message()), 400);
        wp_send_json_success(array('message' => __('Post moved to Trash. You can restore it from Posts > Trash.', 'wolf-forge-article-generator')));
    }

    public function ajax_clear_import_history() {
        $this->authorize_ajax();
        global $wpdb;
        if ($wpdb->query("TRUNCATE TABLE {$wpdb->prefix}wtai_import_history") === false) {
            wp_send_json_error(array('message' => __('Import history could not be cleared.', 'wolf-forge-article-generator')), 500);
        }
        wp_send_json_success(array('message' => __('Import history cleared.', 'wolf-forge-article-generator')));
    }

    public function ajax_reset_settings() {
        $this->authorize_ajax();
        $settings = WTAI_Settings::save(WTAI_Settings::defaults());
        if (is_wp_error($settings)) wp_send_json_error(array('message' => $settings->get_error_message()), 500);
        wp_send_json_success(array('settings' => $settings));
    }

    public function ajax_get_error_stats() {
        $this->authorize_ajax();
        wp_send_json_success($this->error_handler->get_error_stats());
    }

    public function ajax_clear_error_logs() {
        $this->authorize_ajax();
        if ($this->error_handler->clear_error_logs()) {
            wp_send_json_success(array('message' => __('Error logs cleared.', 'wolf-forge-article-generator')));
        }
        wp_send_json_error(array('message' => __('Failed to clear error logs.', 'wolf-forge-article-generator')));
    }
}

function wtai_init() {
    return Word_To_Article_Importer::get_instance();
}

wtai_init();
