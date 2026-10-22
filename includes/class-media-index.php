<?php
/** Cached, weighted image metadata vocabulary. No AI or fixed stopword list. */
if (!defined('ABSPATH')) exit;

class WTAI_Media_Index {
    const CACHE_KEY = 'wtai_media_index_v3';

    public static function register_hooks() {
        foreach (array('add_attachment', 'edit_attachment', 'delete_attachment') as $hook) {
            add_action($hook, array(__CLASS__, 'invalidate'));
        }
        foreach (array('added_post_meta', 'updated_post_meta', 'deleted_post_meta') as $hook) {
            add_action($hook, array(__CLASS__, 'metadata_changed'), 10, 3);
        }
        foreach (array('added_option', 'updated_option', 'deleted_option') as $hook) {
            add_action($hook, array(__CLASS__, 'options_changed'));
        }
    }

    public static function invalidate() {
        delete_transient(self::CACHE_KEY);
        delete_transient('wtai_media_index_v2');
        delete_transient('wtai_media_index_v1');
    }

    public static function metadata_changed($meta_id, $post_id, $key) {
        if (in_array($key, array('_wp_attachment_image_alt', '_wp_attachment_metadata', '_wp_attached_file'), true) && get_post_type($post_id) === 'attachment') self::invalidate();
    }

    public static function options_changed($option) {
        if (in_array($option, array('site_icon', 'site_logo'), true) || strpos($option, 'theme_mods_') === 0) self::invalidate();
    }

    public static function tokens($text) {
        $text = html_entity_decode(wp_strip_all_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = remove_accents($text);
        $text = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
        $words = preg_split('/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        return array_values(array_filter($words ?: array(), function ($word) { return (bool) preg_match('/\p{L}/u', $word); }));
    }

    public static function add_terms(&$vector, $text, $weight = 1) {
        foreach (array_count_values(self::tokens($text)) as $term => $count) {
            $vector[$term] = ($vector[$term] ?? 0) + $weight * (1 + log($count));
        }
    }

    public static function is_graphic($metadata) {
        $metadata = html_entity_decode(wp_strip_all_tags($metadata), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $metadata = preg_replace('/([\p{Ll}\p{N}])([\p{Lu}])/u', '$1 $2', $metadata);
        $metadata = preg_replace('/(\p{Lu})(\p{Lu}\p{Ll})/u', '$1 $2', $metadata);
        return (bool) preg_match('/(?<!\p{L})(?:logos?|logotypes?|logomarks?|icons?|favicons?)(?!\p{L})/iu', $metadata);
    }

    public static function get() {
        $cached = get_transient(self::CACHE_KEY);
        if (is_array($cached)) return $cached;
        $index = array('images' => array(), 'primary' => array(), 'df' => array(), 'count' => 0);
        $site_graphics = array_filter(array((int) get_option('site_icon', 0), (int) get_option('site_logo', 0), (int) get_theme_mod('custom_logo', 0)));
        $page = 1;
        do {
            $query = new WP_Query(array(
                'post_type' => 'attachment', 'post_mime_type' => 'image', 'post_status' => 'inherit',
                'posts_per_page' => 200, 'paged' => $page++, 'orderby' => 'ID', 'order' => 'ASC',
                'no_found_rows' => true, 'update_post_meta_cache' => true, 'update_post_term_cache' => false,
            ));
            foreach ($query->posts as $image) {
                if (in_array((int) $image->ID, $site_graphics, true)) continue;
                $alt = get_post_meta($image->ID, '_wp_attachment_image_alt', true);
                $filename = basename((string) get_post_meta($image->ID, '_wp_attached_file', true));
                $metadata = implode(' ', array($image->post_title, $alt, $image->post_excerpt, $image->post_content, $filename));
                if (self::is_graphic($metadata)) continue;
                $vector = array();
                self::add_terms($vector, $image->post_title, 3);
                self::add_terms($vector, $alt, 3);
                self::add_terms($vector, $image->post_excerpt, 2);
                self::add_terms($vector, $image->post_content);
                if (!$vector) continue;
                $index['images'][(int) $image->ID] = $vector;
                $primary = array('title' => array(), 'alt' => array());
                self::add_terms($primary['title'], $image->post_title);
                self::add_terms($primary['alt'], $alt);
                $index['primary'][(int) $image->ID] = $primary;
                foreach ($vector as $term => $weight) $index['df'][$term] = ($index['df'][$term] ?? 0) + 1;
                $index['count']++;
            }
        } while (count($query->posts) === 200);
        set_transient(self::CACHE_KEY, $index, 3600);
        return $index;
    }

    public static function vector($terms, $index) {
        $vector = array();
        $norm = 0;
        foreach ($terms as $term => $weight) {
            // Smoothed inverse frequency also works with a one-image library.
            $idf = log(1 + ($index['count'] + 1) / (1 + ($index['df'][$term] ?? 0)));
            $vector[$term] = $weight * $idf;
            $norm += $vector[$term] * $vector[$term];
        }
        if ($norm > 0) foreach ($vector as &$weight) $weight /= sqrt($norm);
        unset($weight);
        return $vector;
    }
}
