<?php
/** Import one document, keeping retries attached to the same WordPress post. */
if (!defined('ABSPATH')) exit;

class WTAI_Importer {
    private $error_handler;

    public function __construct($error_handler) {
        $this->error_handler = $error_handler;
    }

    public function import($file, $settings, $date) {
        global $wpdb;
        $file_hash = hash_file('sha256', $file['tmp_name']);
        if (!$file_hash) throw new Exception(__('The uploaded file could not be read.', 'wolf-forge-article-generator'));
        $identity_settings = $settings;
        if (empty($identity_settings['pixabay_fallback'])) unset($identity_settings['pixabay_fallback']);
        // Disabled linking preserves fingerprints created before this option existed.
        if (empty($identity_settings['max_internal_links_per_post'])) unset($identity_settings['max_internal_links_per_post']);
        if (($identity_settings['image_source'] ?? 'media_library') === 'media_library') unset($identity_settings['image_source'], $identity_settings['gbp_profile_url']);
        $key = hash('sha256', $file_hash . wp_json_encode($identity_settings) . ($date ? $date->format('c') : 'immediate'));
        $marker = 'wfag:' . $key;
        $lock = 'wfag_' . substr(hash('sha256', DB_NAME . '.' . $wpdb->posts . $key), 0, 59);

        // A connection-scoped lock is released even if PHP terminates mid-import.
        if ((string) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $lock)) !== '1') {
            throw new Exception(__('This document is already being imported, or the database lock is unavailable. Retry shortly.', 'wolf-forge-article-generator'));
        }

        $post_id = 0;
        try {
            // The marker is stored atomically with the draft row, closing the crash
            // window between post creation and a separate metadata/history write.
            // ponytail: this lookup scans posts; add an indexed import ledger if
            // profiling on a large site shows that recovery lookups are a bottleneck.
            $post_id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'post' AND post_content_filtered = %s ORDER BY ID LIMIT 1",
                $marker
            ));
            if ($wpdb->last_error) throw new Exception(__('Previous imports could not be checked. Retry after the database is available.', 'wolf-forge-article-generator'));
            if ($post_id && get_post_status($post_id) === 'trash') {
                throw new Exception(sprintf(__('This document already exists in Trash as post #%d. Restore or permanently delete it before importing again.', 'wolf-forge-article-generator'), $post_id));
            }
            if ($post_id && (get_post_meta($post_id, '_wtai_import_complete', true) || in_array(get_post_status($post_id), array('publish', 'future'), true))) {
                return $this->result($file['name'], $post_id, true);
            }

            $parser = new WTAI_Docx_Parser($this->error_handler);
            $processor = new WTAI_Content_Processor($this->error_handler);
            $document = $processor->prepare_document($parser->parse($file['tmp_name']), $file['name']);
            $content = $processor->process($document['content'], $settings);
            $image_report = null;
            if (!empty($settings['auto_insert_images']) && $settings['max_images_per_post'] > 0) {
                $matcher = new WTAI_Image_Matcher($this->error_handler);
                $content = $matcher->insert_images($content, $settings, $document['title'], true);
                $image_report = $matcher->get_report();
            }
            $content = wp_kses_post($content);
            if (trim(wp_strip_all_tags($content)) === '') {
                throw new Exception(__('The document has no supported body text. No article was published.', 'wolf-forge-article-generator'));
            }

            $data = array(
                'post_title' => $document['title'],
                'post_name' => sanitize_title($document['title']),
                'post_content' => $content,
                'post_content_filtered' => $marker,
                'post_status' => 'draft',
                'post_author' => $settings['default_author'],
                'post_category' => array($settings['default_category']),
            );
            if ($post_id) $data['ID'] = $post_id;
            $saved = $post_id ? wp_update_post(wp_slash($data), true) : wp_insert_post(wp_slash($data), true);
            if (is_wp_error($saved)) throw new Exception($saved->get_error_message());
            if (!$saved) throw new Exception(__('WordPress could not save the draft.', 'wolf-forge-article-generator'));
            $post_id = (int) $saved;

            $link_report = null;
            if (!empty($settings['max_internal_links_per_post'])) {
                $linker = new WTAI_Internal_Linker();
                $linked_content = $linker->insert_links($content, $post_id, $settings['max_internal_links_per_post']);
                $link_report = $linker->get_report();
                if ($linked_content !== $content) {
                    $linked = wp_update_post(wp_slash(array('ID' => $post_id, 'post_content' => $linked_content)), true);
                    if (is_wp_error($linked)) throw new Exception($linked->get_error_message());
                    if (!$linked) throw new Exception(__('WordPress could not save internal links. The draft was retained.', 'wolf-forge-article-generator'));
                    $content = $linked_content;
                }
            }
            (new WTAI_SEO())->apply($post_id, $document['title'], $content);
            $this->save_history($post_id, $file['name'], $settings);

            $publish = array('ID' => $post_id, 'post_status' => WTAI_Scheduler::status($date), 'edit_date' => true);
            if ($date) {
                $publish['post_date'] = $date->format('Y-m-d H:i:s');
                $publish['post_date_gmt'] = get_gmt_from_date($publish['post_date']);
            } else {
                $publish['post_date'] = current_time('mysql');
                $publish['post_date_gmt'] = current_time('mysql', true);
            }
            $published = wp_update_post(wp_slash($publish), true);
            if (is_wp_error($published)) throw new Exception($published->get_error_message());
            if (!$published) throw new Exception(__('WordPress could not publish the draft.', 'wolf-forge-article-generator'));
            update_post_meta($post_id, '_wtai_import_complete', 1);
            $result = $this->result($file['name'], $post_id, false);
            if ($image_report !== null) $result['images'] = $image_report;
            if ($link_report !== null) $result['links'] = $link_report;
            return $result;
        } catch (Throwable $error) {
            // A hook can throw after WordPress has already committed publication.
            // Report the existing article rather than a misleading failed import.
            if ($post_id && in_array(get_post_status($post_id), array('publish', 'future'), true)) {
                $result = $this->result($file['name'], $post_id, false);
                $result['warning'] = $error->getMessage();
                return $result;
            }
            if ($post_id && get_post_status($post_id) === 'draft') {
                throw new Exception($error->getMessage() . ' ' . sprintf(__('Draft #%d was retained. Retry with the same file and settings to resume it.', 'wolf-forge-article-generator'), $post_id), 0, $error);
            }
            throw $error;
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
        }
    }

    private function save_history($post_id, $filename, $settings) {
        global $wpdb;
        $table = $wpdb->prefix . 'wtai_import_history';
        $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE post_id = %d LIMIT 1", $post_id));
        if ($wpdb->last_error) throw new Exception(__('Import history could not be checked.', 'wolf-forge-article-generator'));
        if ($existing) return;
        $saved = $wpdb->insert($table, array(
            'post_id' => $post_id,
            'original_filename' => $filename,
            'import_date' => current_time('mysql'),
            'settings' => wp_json_encode($settings),
            'status' => 'prepared',
        ), array('%d', '%s', '%s', '%s', '%s'));
        if ($saved === false) throw new Exception(__('Import history could not be saved. The article remains a draft.', 'wolf-forge-article-generator'));
    }

    private function result($filename, $post_id, $existing) {
        global $wpdb;
        $history = $wpdb->update($wpdb->prefix . 'wtai_import_history', array('status' => 'completed'), array('post_id' => $post_id), array('%s'), array('%d'));
        $result = array(
            'file' => $filename,
            'post_id' => $post_id,
            'status' => get_post_status($post_id),
            'publish_date' => get_post_field('post_date', $post_id),
            'existing' => $existing,
            'image_previews' => (new WTAI_Image_Matcher())->result_thumbnails((string) get_post_field('post_content', $post_id)),
        );
        if ($history === false) $result['warning'] = __('The article was saved, but its history status could not be updated.', 'wolf-forge-article-generator');
        return $result;
    }
}
