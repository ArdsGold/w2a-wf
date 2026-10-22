<?php
/** User-triggered Pixabay photo search and bounded, local WordPress downloads. */
if (!defined('ABSPATH')) exit;

class WTAI_Pixabay {
    const MAX_DOWNLOADS = 5;

    private static function key() {
        $override = (string) get_option('wtai_pixabay_api_key', '');
        if ($override !== '') return $override;
        if (defined('WTAI_PIXABAY_API_KEY')) return (string) WTAI_PIXABAY_API_KEY;
        $path = dirname(__DIR__) . '/config.php';
        $config = is_readable($path) ? include $path : array();
        return is_array($config) && is_string($config['pixabay_api_key'] ?? null) ? trim($config['pixabay_api_key']) : '';
    }

    public static function status() {
        $source = get_option('wtai_pixabay_api_key', '') !== '' ? 'override' : (self::key() !== '' ? 'default' : 'missing');
        return array('source' => $source, 'message' => $source === 'missing'
            ? __('Pixabay API key not configured.', 'wolf-forge-article-generator')
            : __('Pixabay API key configured. It will be checked when a fallback search is needed during Submit.', 'wolf-forge-article-generator'));
    }

    public static function save_key($key) {
        if (!is_string($key) || !preg_match('/^[A-Za-z0-9_-]{10,200}$/D', $key)) return new WP_Error('wtai_pixabay_key', __('Enter a valid Pixabay API key.', 'wolf-forge-article-generator'));
        update_option('wtai_pixabay_api_key', $key, false);
        if (get_option('wtai_pixabay_api_key') !== $key) return new WP_Error('wtai_pixabay_save', __('The Pixabay API key could not be saved.', 'wolf-forge-article-generator'));
        return self::status();
    }

    public static function remove_key() {
        delete_option('wtai_pixabay_api_key');
        if (get_option('wtai_pixabay_api_key', '') !== '') return new WP_Error('wtai_pixabay_save', __('The Pixabay API key override could not be removed.', 'wolf-forge-article-generator'));
        return self::status();
    }

    public static function allowed_url($url, $photo = false) {
        if (!is_string($url) || strlen($url) > 2048) return false;
        $parts = wp_parse_url($url);
        if (!$parts || ($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass']) || isset($parts['port']) || isset($parts['fragment'])) return false;
        $host = strtolower($parts['host'] ?? '');
        if ($photo) return ($host === 'cdn.pixabay.com' && strpos($parts['path'] ?? '', '/photo/') === 0)
            || ($host === 'pixabay.com' && strpos($parts['path'] ?? '', '/get/') === 0);
        return $host === 'pixabay.com' && strpos($parts['path'] ?? '', '/photos/') === 0 && empty($parts['query']);
    }

    public static function search($query) {
        $key = self::key();
        if ($key === '') return new WP_Error('wtai_pixabay_key', __('Pixabay fallback needs an API key in Settings & Logs.', 'wolf-forge-article-generator'));
        $query = implode(' ', array_slice(WTAI_Media_Index::tokens($query), 0, 6));
        // Keep q below the provider's 100-character limit, even without mbstring.
        while (strlen($query) > 100) $query = preg_replace('/\s*\S+$/u', '', $query);
        if ($query === '') return array();
        $identity = substr(hash('sha256', $key), 0, 24);
        $cache = 'wtai_pixabay_' . hash('sha256', $identity . ':' . $query);
        $cached = get_transient($cache);
        if (is_array($cached)) return $cached;
        if (get_transient('wtai_pixabay_limit_' . $identity)) return new WP_Error('wtai_pixabay_quota', __('Pixabay rate limit reached. Photos already in Media Library remain available; retry later.', 'wolf-forge-article-generator'));
        $params = array('key' => $key, 'q' => $query, 'image_type' => 'photo', 'safesearch' => 'true', 'min_width' => 640, 'min_height' => 320, 'per_page' => 20);
        $response = wp_safe_remote_get('https://pixabay.com/api/?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986), array('timeout' => 15, 'redirection' => 0, 'limit_response_size' => 1048576));
        if (is_wp_error($response)) return new WP_Error('wtai_pixabay_network', __('Pixabay could not be reached. The article will use available images.', 'wolf-forge-article-generator'));
        $status = wp_remote_retrieve_response_code($response);
        $remaining = wp_remote_retrieve_header($response, 'x-ratelimit-remaining');
        if ($status === 429 || ($status === 200 && $remaining !== '' && (int) $remaining === 0)) {
            $reset = max(60, min(3600, (int) wp_remote_retrieve_header($response, 'x-ratelimit-reset')));
            set_transient('wtai_pixabay_limit_' . $identity, true, $reset);
        }
        if ($status !== 200) return new WP_Error('wtai_pixabay_api', sprintf(__('Pixabay returned HTTP %d. Check the API key or retry later; the article import continues.', 'wolf-forge-article-generator'), $status));
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($data) || !isset($data['hits']) || !is_array($data['hits'])) return new WP_Error('wtai_pixabay_response', __('Pixabay returned an invalid photo list.', 'wolf-forge-article-generator'));
        $photos = array();
        foreach (array_slice($data['hits'], 0, 20) as $hit) {
            if (!is_array($hit) || ($hit['type'] ?? '') !== 'photo' || !is_numeric($hit['id'] ?? null) || (int) $hit['id'] < 1) continue;
            $tags = is_string($hit['tags'] ?? null) ? sanitize_text_field($hit['tags']) : '';
            if ($tags === '' || WTAI_Media_Index::is_graphic($tags) || !self::allowed_url($hit['largeImageURL'] ?? '', true) || !self::allowed_url($hit['pageURL'] ?? '')) continue;
            $photos[] = array('id' => (int) $hit['id'], 'tags' => $tags, 'url' => $hit['largeImageURL'], 'page' => $hit['pageURL'], 'author' => is_string($hit['user'] ?? null) ? sanitize_text_field($hit['user']) : 'Pixabay');
        }
        set_transient($cache, $photos, 86400);
        return $photos;
    }

    public static function download($photo) {
        if (!current_user_can('upload_files')) return new WP_Error('wtai_pixabay_permission', __('Media upload permission is required for Pixabay downloads.', 'wolf-forge-article-generator'));
        if (!self::allowed_url($photo['url'] ?? '', true) || !self::allowed_url($photo['page'] ?? '') || empty($photo['id']) || empty($photo['tags'])) return new WP_Error('wtai_pixabay_photo', __('Invalid Pixabay photo.', 'wolf-forge-article-generator'));
        global $wpdb;
        $lock = 'wtai_photo_' . substr(hash('sha256', DB_NAME . '.' . $wpdb->posts . ':' . (int) $photo['id']), 0, 48);
        if ((string) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $lock)) !== '1') return new WP_Error('wtai_pixabay_busy', __('This Pixabay photo is being downloaded by another import. Retry later.', 'wolf-forge-article-generator'));
        $temporary = '';
        try {
            $existing = get_posts(array('post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => 'image', 'meta_key' => '_wtai_pixabay_id', 'meta_value' => (int) $photo['id'], 'numberposts' => 1, 'fields' => 'ids'));
            if ($existing && is_file(get_attached_file($existing[0]))) return (int) $existing[0];
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            $temporary = wp_tempnam('wtai-pixabay');
            if (!$temporary) return new WP_Error('wtai_pixabay_temp', __('A temporary photo file could not be created.', 'wolf-forge-article-generator'));
            $limit = min(10485760, wp_max_upload_size());
            $response = wp_safe_remote_get($photo['url'], array('timeout' => 20, 'redirection' => 0, 'stream' => true, 'filename' => $temporary, 'limit_response_size' => $limit + 1));
            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200 || filesize($temporary) > $limit) return new WP_Error('wtai_pixabay_download', __('A Pixabay photo could not be downloaded within the size limit.', 'wolf-forge-article-generator'));
            $size = wp_getimagesize($temporary);
            $extensions = array('image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp');
            if (!$size || !isset($extensions[$size['mime'] ?? '']) || min($size[0], $size[1]) < 320 || $size[0] * $size[1] > 40000000) return new WP_Error('wtai_pixabay_format', __('Pixabay returned an unsupported or unsafe image file.', 'wolf-forge-article-generator'));
            $id = media_handle_sideload(array('name' => 'pixabay-' . (int) $photo['id'] . '.' . $extensions[$size['mime']], 'tmp_name' => $temporary), 0, null, array(
                'post_title' => $photo['tags'],
                'post_excerpt' => sprintf(__('Photo: %s / Pixabay.', 'wolf-forge-article-generator'), $photo['author']),
                'post_content' => $photo['tags'] . "\n" . $photo['page'] . "\nhttps://pixabay.com/service/license-summary/",
            ));
            if (is_wp_error($id)) return new WP_Error('wtai_pixabay_media', __('WordPress could not save the Pixabay photo to Media Library.', 'wolf-forge-article-generator'));
            update_post_meta($id, '_wp_attachment_image_alt', wp_slash($photo['tags']));
            update_post_meta($id, '_wtai_pixabay_id', (int) $photo['id']);
            update_post_meta($id, '_wtai_pixabay_credit', wp_slash(array('page' => $photo['page'], 'author' => $photo['author'], 'license' => 'https://pixabay.com/service/license-summary/')));
            WTAI_Media_Index::invalidate();
            return (int) $id;
        } finally {
            if ($temporary && is_file($temporary)) wp_delete_file($temporary);
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
        }
    }
}
