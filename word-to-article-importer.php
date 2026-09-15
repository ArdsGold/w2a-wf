<?php
/**
 * Plugin Name: Word to Article Importer
 * Description: Import .docx files as WordPress articles with bulk upload, formatting cleanup, scheduling, and optional media matching.
 * Version: 3.0.0
 * Author: Macky Villafuerte, Arden Guinto
 * License: GPL v2 or later
 * Text Domain: word-to-article-importer
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WTAI_VERSION', '1.10.1');
define('WTAI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WTAI_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WTAI_PLUGIN_BASENAME', plugin_basename(__FILE__));

class Word_To_Article_Importer {
    private static $instance = null;
    private $error_handler;
    private $admin_page;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->includes();
        $this->error_handler = new WTAI_Error_Handler();
        $this->admin_page = new WTAI_Admin_Page();

        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        if (is_admin()) {
            add_action('admin_menu', array($this, 'add_admin_menu'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        }

        add_action('wp_ajax_wtai_upload_documents', array($this, 'ajax_upload_documents'));
        add_action('wp_ajax_wtai_get_media_images', array($this, 'ajax_get_media_images'));
        add_action('wp_ajax_wtai_get_import_history', array($this, 'ajax_get_import_history'));
        add_action('wp_ajax_wtai_clear_import_history', array($this, 'ajax_clear_import_history'));
        add_action('wp_ajax_wtai_reset_settings', array($this, 'ajax_reset_settings'));
        add_action('wp_ajax_wtai_get_error_stats', array($this, 'ajax_get_error_stats'));
        add_action('wp_ajax_wtai_clear_error_logs', array($this, 'ajax_clear_error_logs'));
    }

    private function includes() {
        require_once WTAI_PLUGIN_DIR . 'includes/class-error-handler.php';
        require_once WTAI_PLUGIN_DIR . 'includes/class-docx-parser.php';
        require_once WTAI_PLUGIN_DIR . 'includes/class-content-processor.php';
        require_once WTAI_PLUGIN_DIR . 'includes/class-image-matcher.php';
        require_once WTAI_PLUGIN_DIR . 'includes/class-server-info.php';
        require_once WTAI_PLUGIN_DIR . 'admin/class-admin-page.php';
    }

    public function get_error_handler() {
        return $this->error_handler;
    }

    public function activate() {
        $this->create_tables();
        $this->set_default_options();
        flush_rewrite_rules();
    }

    public function deactivate() {
        flush_rewrite_rules();
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

    public function get_defaults() {
        return array(
            'convert_numbered_to_dotted' => false,
            'unbold_except_headings' => false,
            'auto_insert_images' => false,
            'image_matching_method' => 'title',
            'max_images_per_post' => 3,
            'default_author' => get_current_user_id(),
            'default_category' => (int) get_option('default_category', 1),
            'publish_schedule' => 'immediate',
            'schedule_start_date' => '',
            'schedule_end_date' => '',
            'schedule_interval' => 'day',
        );
    }

    private function set_default_options() {
        foreach ($this->get_defaults() as $key => $value) {
            if (false === get_option('wtai_' . $key, false)) {
                add_option('wtai_' . $key, $value);
            }
        }
    }

    public function add_admin_menu() {
        add_menu_page(
            __('Word to Article Importer', 'word-to-article-importer'),
            __('Word Importer', 'word-to-article-importer'),
            'manage_options',
            'word-to-article-importer',
            array($this, 'render_admin_page'),
            'dashicons-media-document',
            30
        );

        add_submenu_page(
            'word-to-article-importer',
            __('Import Settings', 'word-to-article-importer'),
            __('Settings', 'word-to-article-importer'),
            'manage_options',
            'word-to-article-importer-settings',
            array($this, 'render_settings_page')
        );
    }

    public function enqueue_admin_scripts($hook) {
        if ('toplevel_page_word-to-article-importer' !== $hook && 'word-importer_page_word-to-article-importer-settings' !== $hook) {
            return;
        }

        wp_enqueue_style('wtai-admin-style', WTAI_PLUGIN_URL . 'assets/css/admin.css', array(), WTAI_VERSION);
        wp_enqueue_script('wtai-admin-script', WTAI_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), WTAI_VERSION, true);
        wp_localize_script('wtai-admin-script', 'wtai_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wtai_ajax_nonce'),
            'edit_post_url' => admin_url('post.php') . '?post=%d&action=edit',
            'strings' => array(
                'select_file' => __('Please select at least one .docx file.', 'word-to-article-importer'),
                'invalid_file' => __('Only .docx files are supported.', 'word-to-article-importer'),
                'uploading' => __('Importing documents...', 'word-to-article-importer'),
                'unknown_error' => __('An unexpected error occurred.', 'word-to-article-importer'),
            ),
        ));
    }

    public function render_admin_page() {
        require WTAI_PLUGIN_DIR . 'admin/views/import-page.php';
    }

    public function render_settings_page() {
        require WTAI_PLUGIN_DIR . 'admin/views/settings-page.php';
    }

    private function sanitize_import_settings($input) {
        $defaults = $this->get_defaults();
        $input = is_array($input) ? wp_unslash($input) : array();

        $settings = array(
            'convert_numbered_to_dotted' => !empty($input['convert_numbered_to_dotted']),
            'unbold_except_headings' => !empty($input['unbold_except_headings']),
            'auto_insert_images' => !empty($input['auto_insert_images']),
            'image_matching_method' => in_array(isset($input['image_matching_method']) ? $input['image_matching_method'] : '', array('title', 'description', 'both'), true) ? $input['image_matching_method'] : $defaults['image_matching_method'],
            'max_images_per_post' => min(10, max(0, absint(isset($input['max_images_per_post']) ? $input['max_images_per_post'] : $defaults['max_images_per_post']))),
            'default_author' => absint(isset($input['default_author']) ? $input['default_author'] : $defaults['default_author']),
            'default_category' => absint(isset($input['default_category']) ? $input['default_category'] : $defaults['default_category']),
            'publish_schedule' => in_array(isset($input['publish_schedule']) ? $input['publish_schedule'] : '', array('immediate', 'custom_range', 'specific_date'), true) ? $input['publish_schedule'] : 'immediate',
            'schedule_start_date' => $this->sanitize_date(isset($input['schedule_start_date']) ? $input['schedule_start_date'] : ''),
            'schedule_end_date' => $this->sanitize_date(isset($input['schedule_end_date']) ? $input['schedule_end_date'] : ''),
            'schedule_interval' => in_array(isset($input['schedule_interval']) ? $input['schedule_interval'] : '', array('day', 'week', 'month'), true) ? $input['schedule_interval'] : 'day',
        );

        if (!get_user_by('id', $settings['default_author']) || !user_can($settings['default_author'], 'edit_posts')) {
            $settings['default_author'] = get_current_user_id();
        }

        if (!term_exists($settings['default_category'], 'category')) {
            $settings['default_category'] = (int) get_option('default_category', 1);
        }

        if ($settings['publish_schedule'] !== 'custom_range') {
            $settings['schedule_end_date'] = '';
        }

        if ($settings['publish_schedule'] === 'immediate') {
            $settings['schedule_start_date'] = '';
        }

        return $settings;
    }

    private function sanitize_date($value) {
        $value = sanitize_text_field($value);
        if (!$value) {
            return '';
        }
        $date = DateTime::createFromFormat('!Y-m-d', $value, wp_timezone());
        return ($date && $date->format('Y-m-d') === $value) ? $value : '';
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

    private function validate_docx_upload($file) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('wtai_upload_error', $this->upload_error_message($file['error']));
        }
        if (!$file['tmp_name'] || !is_uploaded_file($file['tmp_name'])) {
            return new WP_Error('wtai_invalid_upload', __('The uploaded file is invalid.', 'word-to-article-importer'));
        }
        if ($file['size'] <= 0) {
            return new WP_Error('wtai_empty_file', __('The uploaded file is empty.', 'word-to-article-importer'));
        }
        if ($file['size'] > wp_max_upload_size()) {
            return new WP_Error('wtai_file_too_large', __('The file exceeds the server upload limit.', 'word-to-article-importer'));
        }
        if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'docx') {
            return new WP_Error('wtai_invalid_file_type', __('Only .docx files are supported.', 'word-to-article-importer'));
        }

        $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
        if ($finfo) {
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            $allowed = array('application/zip', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/octet-stream');
            if ($mime && !in_array($mime, $allowed, true)) {
                return new WP_Error('wtai_invalid_file_type', __('The uploaded file is not a valid DOCX/ZIP file.', 'word-to-article-importer'));
            }
        }
        return true;
    }

    private function upload_error_message($code) {
        $messages = array(
            UPLOAD_ERR_INI_SIZE => __('The file exceeds the server upload limit.', 'word-to-article-importer'),
            UPLOAD_ERR_FORM_SIZE => __('The file exceeds the form upload limit.', 'word-to-article-importer'),
            UPLOAD_ERR_PARTIAL => __('The file was only partially uploaded.', 'word-to-article-importer'),
            UPLOAD_ERR_NO_FILE => __('No file was uploaded.', 'word-to-article-importer'),
            UPLOAD_ERR_NO_TMP_DIR => __('The server is missing its temporary upload directory.', 'word-to-article-importer'),
            UPLOAD_ERR_CANT_WRITE => __('The server could not write the uploaded file.', 'word-to-article-importer'),
            UPLOAD_ERR_EXTENSION => __('A server extension stopped the upload.', 'word-to-article-importer'),
        );
        return isset($messages[$code]) ? $messages[$code] : __('The file upload failed.', 'word-to-article-importer');
    }

    public function ajax_upload_documents() {
        check_ajax_referer('wtai_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'word-to-article-importer')), 403);
        }

        $files = $this->normalize_files(isset($_FILES['documents']) ? $_FILES['documents'] : array());
        if (!$files) {
            wp_send_json_error(array('message' => __('No files uploaded.', 'word-to-article-importer')), 400);
        }

        $settings = $this->sanitize_import_settings(isset($_POST['settings']) ? $_POST['settings'] : array());
        $publish_dates = $this->calculate_publish_dates($settings, count($files));
        if (is_wp_error($publish_dates)) {
            wp_send_json_error(array('message' => $publish_dates->get_error_message()), 400);
        }
        $parser = new WTAI_Docx_Parser($this->error_handler);
        $processor = new WTAI_Content_Processor($this->error_handler);
        $image_matcher = new WTAI_Image_Matcher($this->error_handler);
        $results = array();
        $errors = array();

        foreach ($files as $index => $file) {
            try {
                $valid = $this->validate_docx_upload($file);
                if (is_wp_error($valid)) {
                    throw new Exception($valid->get_error_message());
                }

                $content = $parser->parse($file['tmp_name']);
                $processed_content = $processor->process($content, $settings);
                $processed_content = $this->sanitize_imported_html($processed_content);

                if ($settings['auto_insert_images'] && $settings['max_images_per_post'] > 0) {
                    $processed_content = $image_matcher->insert_images($processed_content, $settings);
                }

                $publish_date = isset($publish_dates[$index]) ? $publish_dates[$index] : null;
                $article_title = $this->generate_title($file['name'], $content);
                $post_data = array(
                    'post_title' => $article_title,
                    'post_name' => sanitize_title($article_title),
                    'post_content' => $processed_content,
                    'post_status' => $this->get_post_status($publish_date),
                    'post_author' => $settings['default_author'],
                    'post_category' => array($settings['default_category']),
                );

                if ($publish_date instanceof DateTime) {
                    $post_data['post_date'] = $publish_date->format('Y-m-d H:i:s');
                    $post_data['post_date_gmt'] = get_gmt_from_date($post_data['post_date']);
                }

                $post_id = wp_insert_post(wp_slash($post_data), true);
                if (is_wp_error($post_id)) {
                    throw new Exception($post_id->get_error_message());
                }

                $this->set_seo_metadata($post_id, $article_title, $processed_content);

                $this->log_import_history($post_id, $file['name'], $settings);
                $results[] = array(
                    'file' => $file['name'],
                    'post_id' => $post_id,
                    'status' => get_post_status($post_id),
                    'publish_date' => $publish_date instanceof DateTime ? $publish_date->format('Y-m-d H:i:s') : __('immediate', 'word-to-article-importer'),
                );
            } catch (Throwable $e) {
                $this->error_handler->log_error(WTAI_Error_Handler::ERR_UNKNOWN_ERROR, array(
                    'file' => $file['name'],
                    'error' => $e->getMessage(),
                ));
                $errors[] = array(
                    'file' => $file['name'],
                    'error' => $e->getMessage(),
                    'error_code' => WTAI_Error_Handler::ERR_UNKNOWN_ERROR,
                );
            }
        }

        wp_send_json_success(array(
            'results' => $results,
            'errors' => $errors,
            'total' => count($files),
            'successful' => count($results),
            'failed' => count($errors),
        ));
    }

    /**
     * Populate SEO metadata. Yoast gets the focus keyphrase as well as title/description.
     */
    private function set_seo_metadata($post_id, $title, $content) {
        $title = wp_strip_all_tags($title);
        $description = $this->build_meta_description($content);
        $focus_keyphrase = $this->build_focus_keyphrase($title, $content);

        if (defined('WPSEO_VERSION') || class_exists('WPSEO_Options')) {
            update_post_meta($post_id, '_yoast_wpseo_title', $title);
            update_post_meta($post_id, '_yoast_wpseo_metadesc', $description);
            update_post_meta($post_id, '_yoast_wpseo_focuskw', $focus_keyphrase);
        } elseif (defined('RANK_MATH_VERSION') || class_exists('RankMath')) {
            update_post_meta($post_id, 'rank_math_title', $title);
            update_post_meta($post_id, 'rank_math_description', $description);
        } elseif (defined('AIOSEO_VERSION') || class_exists('AIOSEO\\Plugin\\AIOSEO')) {
            update_post_meta($post_id, '_aioseo_title', $title);
            update_post_meta($post_id, '_aioseo_description', $description);
        } elseif (defined('SEOPRESS_VERSION')) {
            update_post_meta($post_id, '_seopress_titles_title', $title);
            update_post_meta($post_id, '_seopress_titles_desc', $description);
        }
    }

    /**
     * Select a deterministic focus keyphrase from the title, headings, and body.
     */
    private function build_focus_keyphrase($title, $content) {
        $stop_words = array_flip(array(
            'a','an','and','are','as','at','be','been','being','but','by','can','could','did','do','does','for','from',
            'had','has','have','how','if','in','into','is','it','its','may','more','most','of','on','or','should','so',
            'that','the','their','there','these','they','this','those','to','was','were','what','when','where','which',
            'who','why','will','with','would','you','your'
        ));

        $normalize = function ($text) {
            $text = html_entity_decode(wp_strip_all_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $text = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
            return preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text);
        };

        $tokenize = function ($text) use ($normalize, $stop_words) {
            $words = preg_split('/\s+/u', trim($normalize($text)), -1, PREG_SPLIT_NO_EMPTY);
            return array_values(array_filter($words, function ($word) use ($stop_words) {
                return !isset($stop_words[$word]);
            }));
        };

        $title_words = $tokenize($title);
        if (!$title_words) {
            return '';
        }

        $body_text = $normalize($content);
        $body_words = preg_split('/\s+/u', trim($body_text), -1, PREG_SPLIT_NO_EMPTY);
        $frequencies = array_count_values($body_words);

        $heading_text = '';
        if (preg_match_all('/<h[1-6]\b[^>]*>(.*?)<\/h[1-6]>/is', $content, $matches)) {
            $heading_text = implode(' ', $matches[1]);
        }
        $heading_text = $normalize($heading_text);

        $candidates = array();
        $count = count($title_words);

        // Generate only title-derived phrases. This prevents arbitrary body text
        // from becoming the focus keyphrase and keeps the result topic-focused.
        for ($i = 0; $i < $count; $i++) {
            for ($size = 1; $size <= 4 && $i + $size <= $count; $size++) {
                $phrase_words = array_slice($title_words, $i, $size);
                $phrase = implode(' ', $phrase_words);
                $score = 0;

                // Prefer useful 2-3 word phrases; allow 1 or 4 when clearly stronger.
                $score += $size === 2 ? 24 : ($size === 3 ? 22 : ($size === 4 ? 10 : 4));

                foreach ($phrase_words as $word) {
                    $score += min(8, isset($frequencies[$word]) ? $frequencies[$word] : 0);
                }

                // Strongest signal: the phrase itself occurs in the article title.
                $normalized_title = ' ' . implode(' ', $title_words) . ' ';
                if (strpos($normalized_title, ' ' . $phrase . ' ') !== false) {
                    $score += 30;
                }

                // Headings are stronger topical signals than ordinary body text.
                if ($heading_text !== '' && strpos(' ' . $heading_text . ' ', ' ' . $phrase . ' ') !== false) {
                    $score += 15;
                }

                // Avoid weak phrases made only from numbers.
                if (preg_match('/^[\p{N}\s]+$/u', $phrase)) {
                    $score -= 20;
                }

                // Prefer a phrase with a substantive word over a generic short phrase.
                if ($size === 1 && (function_exists('mb_strlen') ? mb_strlen($phrase, 'UTF-8') < 4 : strlen($phrase) < 4)) {
                    $score -= 6;
                }

                if (!isset($candidates[$phrase]) || $score > $candidates[$phrase]) {
                    $candidates[$phrase] = $score;
                }
            }
        }

        arsort($candidates, SORT_NUMERIC);
        return (string) key($candidates);
    }

    /**
     * Build a maximum-142-character description without cutting a word in half.
     */
    private function build_meta_description($content) {
        $paragraphs = array();
        if (preg_match_all('/<p\\b[^>]*>(.*?)<\\/p>/is', $content, $matches)) {
            foreach ($matches[1] as $paragraph) {
                $paragraph = trim(preg_replace('/\\s+/u', ' ', wp_strip_all_tags($paragraph)));
                if ($paragraph !== '') {
                    $paragraphs[] = $paragraph;
                }
            }
        }

        $text = isset($paragraphs[0]) ? $paragraphs[0] : trim(preg_replace('/\\s+/u', ' ', wp_strip_all_tags($content)));
        $length = preg_match_all('/./us', $text, $chars);
        if ($length === false || $length <= 142) {
            return $text;
        }

        $chars = $chars[0];
        $candidate = implode('', array_slice($chars, 0, 139));
        $candidate = preg_replace('/\\s+\\S*$/u', '', $candidate);
        return rtrim($candidate) . '...';
    }

    /**
     * Sanitize imported HTML without flattening semantic formatting.
     *
     * WordPress KSES normally permits these elements, but explicitly defining
     * the allowed document structure prevents an importer/filter mismatch
     * from turning headings and lists into plain paragraphs.
     */
    private function sanitize_imported_html($html) {
        $allowed = wp_kses_allowed_html('post');

        foreach (array('h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'ul', 'ol', 'li', 'strong', 'em', 'u', 's', 'br', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td') as $tag) {
            if (!isset($allowed[$tag])) {
                $allowed[$tag] = array();
            }
        }

        return wp_kses($html, $allowed);
    }

    private function calculate_publish_dates($settings, $count) {
        if ($settings['publish_schedule'] === 'immediate' || $count < 1) {
            return array_fill(0, $count, null);
        }

        try {
            $timezone = wp_timezone();
            $start = $this->parse_schedule_date($settings['schedule_start_date'], $timezone);
            if (!$start) {
                throw new Exception(__('Please select a valid start date.', 'word-to-article-importer'));
            }
            $start->setTime(9, 0, 0);

            $interval = $this->get_interval($settings['schedule_interval']);
            $end = null;
            if ($settings['publish_schedule'] === 'custom_range') {
                $end = $this->parse_schedule_date($settings['schedule_end_date'], $timezone);
                if (!$end) {
                    throw new Exception(__('Please select a valid end date.', 'word-to-article-importer'));
                }
                $end->setTime(23, 59, 59);
                if ($end < $start) {
                    throw new Exception(__('End date must not be before the start date.', 'word-to-article-importer'));
                }
            }

            $dates = array();
            $current = clone $start;
            $slots = $this->count_schedule_slots($start, $end, $settings['schedule_interval']);

            if ($end && $count > $slots) {
                throw new Exception(sprintf(
                    __('The selected date range contains %1$d %2$s publishing slot%3$s, but %4$d documents were selected. Extend the range or use a longer interval.', 'word-to-article-importer'),
                    $slots,
                    $settings['schedule_interval'] === 'day' ? __('daily', 'word-to-article-importer') : ($settings['schedule_interval'] === 'week' ? __('weekly', 'word-to-article-importer') : __('monthly', 'word-to-article-importer')),
                    $slots === 1 ? '' : 's',
                    $count
                ));
            }

            for ($i = 0; $i < $count; $i++) {
                $dates[] = clone $current;
                $current->add($interval);
            }

            return $dates;
        } catch (Throwable $e) {
            $this->error_handler->log_error(WTAI_Error_Handler::ERR_SCHEDULE_CALCULATION_FAILED, array('error' => $e->getMessage()));
            return new WP_Error('wtai_schedule_error', $e->getMessage());
        }
    }

    private function parse_schedule_date($value, DateTimeZone $timezone) {
        $value = sanitize_text_field($value);
        if ($value === '') {
            return null;
        }
        $date = DateTime::createFromFormat('!Y-m-d', $value, $timezone);
        return ($date && $date->format('Y-m-d') === $value) ? $date : null;
    }

    private function count_schedule_slots(DateTime $start, DateTime $end, $interval_type) {
        if (!$end) {
            return PHP_INT_MAX;
        }

        $count = 0;
        $current = clone $start;
        $interval = $this->get_interval($interval_type);
        while ($current <= $end) {
            $count++;
            $current->add($interval);
            if ($count > 100000) {
                break;
            }
        }
        return $count;
    }

    private function get_interval($interval_type) {
        switch ($interval_type) {
            case 'week': return new DateInterval('P1W');
            case 'month': return new DateInterval('P1M');
            default: return new DateInterval('P1D');
        }
    }

    private function get_post_status($publish_date) {
        if ($publish_date instanceof DateTime) {
            $now = new DateTime('now', wp_timezone());
            return $publish_date > $now ? 'future' : 'publish';
        }
        return 'publish';
    }

    private function generate_title($filename, $content) {
        $title = pathinfo($filename, PATHINFO_FILENAME);
        $title = preg_replace('/[\s_-]+/u', ' ', $title);
        $title = trim($title);

        if ($title === '' || preg_match('/^\d+$/', $title)) {
            if (preg_match('/<h[1-6][^>]*>(.*?)<\/h[1-6]>/is', $content, $matches)) {
                $title = wp_strip_all_tags($matches[1]);
            }
        }

        return wp_strip_all_tags($title ?: __('Imported Article', 'word-to-article-importer'));
    }

    private function log_import_history($post_id, $filename, $settings) {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'wtai_import_history',
            array(
                'post_id' => $post_id,
                'original_filename' => $filename,
                'settings' => wp_json_encode($settings),
                'status' => 'completed',
            ),
            array('%d', '%s', '%s', '%s')
        );
    }

    public function ajax_get_media_images() {
        check_ajax_referer('wtai_ajax_nonce', 'nonce');
        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'word-to-article-importer')), 403);
        }

        $search = isset($_GET['search']) ? sanitize_text_field(wp_unslash($_GET['search'])) : '';
        $page = max(1, isset($_GET['page']) ? absint($_GET['page']) : 1);
        $data = (new WTAI_Image_Matcher($this->error_handler))->get_media_images($search, $page, 20);
        wp_send_json_success($data);
    }

    public function ajax_get_import_history() {
        check_ajax_referer('wtai_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'word-to-article-importer')), 403);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'wtai_import_history';
        $history = $wpdb->get_results("SELECT id, post_id, original_filename, import_date, status FROM {$table} ORDER BY import_date DESC LIMIT 10", ARRAY_A);
        wp_send_json_success(array('history' => $history ? $history : array()));
    }

    public function ajax_clear_import_history() {
        check_ajax_referer('wtai_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'word-to-article-importer')), 403);
        }
        global $wpdb;
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}wtai_import_history");
        wp_send_json_success(array('message' => __('Import history cleared.', 'word-to-article-importer')));
    }

    public function ajax_reset_settings() {
        check_ajax_referer('wtai_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'word-to-article-importer')), 403);
        }
        foreach ($this->get_defaults() as $key => $value) {
            update_option('wtai_' . $key, $value);
        }
        wp_send_json_success(array('message' => __('Settings reset to defaults.', 'word-to-article-importer')));
    }

    public function ajax_get_error_stats() {
        check_ajax_referer('wtai_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'word-to-article-importer')), 403);
        }
        wp_send_json_success($this->error_handler->get_error_stats());
    }

    public function ajax_clear_error_logs() {
        check_ajax_referer('wtai_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'word-to-article-importer')), 403);
        }
        if ($this->error_handler->clear_error_logs()) {
            wp_send_json_success(array('message' => __('Error logs cleared.', 'word-to-article-importer')));
        }
        wp_send_json_error(array('message' => __('Failed to clear error logs.', 'word-to-article-importer')));
    }
}

function wtai_init() {
    return Word_To_Article_Importer::get_instance();
}

wtai_init();
