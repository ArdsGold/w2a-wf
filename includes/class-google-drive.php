<?php
/** Public-folder DOCX/Google Docs access. Keys stay server-side; downloads use temporary files. */
if (!defined('ABSPATH')) exit;

class WTAI_Google_Drive {
    private const DOCX = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    private const GOOGLE_DOC = 'application/vnd.google-apps.document';

    private static function key() {
        $override = (string) get_option('wtai_google_drive_api_key', '');
        if ($override !== '') return $override;
        if (defined('WTAI_GOOGLE_DRIVE_API_KEY')) return (string) WTAI_GOOGLE_DRIVE_API_KEY;
        $path = dirname(__DIR__) . '/config.php';
        $config = is_readable($path) ? include $path : array();
        return is_array($config) && is_string($config['google_drive_api_key'] ?? null) ? trim($config['google_drive_api_key']) : '';
    }

    private static function notice_key() { return 'wtai_drive_' . substr(hash('sha256', self::key()), 0, 24); }

    public static function status() {
        $source = get_option('wtai_google_drive_api_key', '') !== '' ? 'override' : (self::key() !== '' ? 'default' : 'missing');
        $connection = $source === 'missing' ? 'missing' : (get_transient(self::notice_key() . '_connection') ?: 'unchecked');
        $labels = array(
            'missing' => __('Not configured', 'wolf-forge-article-generator'),
            'unchecked' => __('Configured (not checked)', 'wolf-forge-article-generator'),
            'connected' => __('Connected', 'wolf-forge-article-generator'),
            'quota' => __('Rate or download limit reached', 'wolf-forge-article-generator'),
            'access_error' => __('Access or API key error', 'wolf-forge-article-generator'),
            'network_error' => __('Connection error', 'wolf-forge-article-generator'),
        );
        return array('source' => $source, 'connection' => $connection, 'connection_label' => $labels[$connection] ?? $labels['unchecked'], 'notice' => get_transient(self::notice_key()) ?: '', 'folder_url' => (string) get_option('wtai_drive_folder_url', ''));
    }

    public static function save_key($key) {
        if (!is_string($key) || !preg_match('/^[A-Za-z0-9_-]{20,200}$/D', $key)) return new WP_Error('wtai_drive_key', __('Enter a valid Google Drive API key.', 'wolf-forge-article-generator'));
        update_option('wtai_google_drive_api_key', $key, false);
        if (get_option('wtai_google_drive_api_key') !== $key) return new WP_Error('wtai_drive_save', __('The Drive API key could not be saved.', 'wolf-forge-article-generator'));
        delete_transient(self::notice_key());
        delete_transient(self::notice_key() . '_connection');
        return self::status();
    }

    public static function remove_key() {
        delete_option('wtai_google_drive_api_key');
        if (get_option('wtai_google_drive_api_key', '') !== '') return new WP_Error('wtai_drive_save', __('The Drive API key override could not be removed.', 'wolf-forge-article-generator'));
        return self::status();
    }

    private static function valid_id($id) { return is_string($id) && preg_match('/^[A-Za-z0-9_-]{10,200}$/D', $id); }

    public static function folder($url) {
        if (!is_string($url) || strlen($url) > 2048) return new WP_Error('wtai_drive_folder', __('Paste a public HTTPS Google Drive folder link.', 'wolf-forge-article-generator'));
        $parts = wp_parse_url($url);
        if (!$parts || ($parts['scheme'] ?? '') !== 'https' || strtolower($parts['host'] ?? '') !== 'drive.google.com' || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) return new WP_Error('wtai_drive_folder', __('Paste a public HTTPS Google Drive folder link.', 'wolf-forge-article-generator'));
        parse_str($parts['query'] ?? '', $query);
        $id = '';
        if (preg_match('~^/(?:drive/(?:u/\d+/)?)?folders/([A-Za-z0-9_-]+)/?$~D', $parts['path'] ?? '', $match)) $id = $match[1];
        elseif (($parts['path'] ?? '') === '/open') $id = $query['id'] ?? '';
        $resource = $query['resourcekey'] ?? '';
        if (!self::valid_id($id) || !is_string($resource) || ($resource !== '' && !self::valid_id($resource))) return new WP_Error('wtai_drive_folder', __('The Google Drive folder link is invalid.', 'wolf-forge-article-generator'));
        return array('id' => $id, 'resource_key' => $resource);
    }

    private static function api($path, $params, $id = '', $resource = '', $filename = null) {
        if (self::key() === '') return new WP_Error('wtai_drive_key', __('No Drive API key is configured. Open Settings & Logs to enter your own key.', 'wolf-forge-article-generator'));
        $args = array('timeout' => 25, 'redirection' => 0, 'limit_response_size' => $filename ? wp_max_upload_size() + 1 : 1048576, 'headers' => array('X-Goog-Api-Key' => self::key()));
        if ($resource !== '') {
            if (!self::valid_id($resource)) return new WP_Error('wtai_drive_file', __('Invalid Drive resource key.', 'wolf-forge-article-generator'));
            $args['headers']['X-Goog-Drive-Resource-Keys'] = $id . '/' . $resource;
        }
        if ($filename) { $args['stream'] = true; $args['filename'] = $filename; }
        $response = wp_safe_remote_get('https://www.googleapis.com/drive/v3/' . $path . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986), $args);
        if (is_wp_error($response)) {
            $message = __('Google Drive could not be reached. Keep your selection and retry.', 'wolf-forge-article-generator');
            set_transient(self::notice_key(), $message, HOUR_IN_SECONDS);
            set_transient(self::notice_key() . '_connection', 'network_error', HOUR_IN_SECONDS);
            return new WP_Error('wtai_drive_network', $message);
        }
        $status = wp_remote_retrieve_response_code($response);
        if ($status !== 200) {
            $body = $filename && is_file($filename) ? file_get_contents($filename, false, null, 0, 65536) : wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            $reasons = array_column($data['error']['errors'] ?? array(), 'reason');
            if (in_array('exportSizeLimitExceeded', $reasons, true)) {
                delete_transient(self::notice_key());
                set_transient(self::notice_key() . '_connection', 'connected', HOUR_IN_SECONDS);
                return new WP_Error('wtai_drive_export_size', __('Google Docs exports are limited to 10 MB. Download a smaller DOCX copy or reduce the document size before importing.', 'wolf-forge-article-generator'));
            }
            $quota = $status === 429 || array_intersect($reasons, array('rateLimitExceeded', 'userRateLimitExceeded', 'dailyLimitExceeded', 'downloadQuotaExceeded', 'quotaExceeded')) || ($data['error']['status'] ?? '') === 'RESOURCE_EXHAUSTED';
            if ($quota) {
                $message = __('Google Drive reports a rate or download limit. Importing has paused. Wait and retry the same selection, or set an API key from a different Cloud project in Settings & Logs. A new key on the same project may share the same quota; file-specific download limits still apply.', 'wolf-forge-article-generator');
                set_transient(self::notice_key(), $message, HOUR_IN_SECONDS);
                set_transient(self::notice_key() . '_connection', 'quota', HOUR_IN_SECONDS);
                return new WP_Error('wtai_drive_quota', $message);
            }
            $message = sprintf(__('Google Drive returned HTTP %d. Check public Viewer access, download permissions, Drive API enablement, and API key restrictions in Settings & Logs.', 'wolf-forge-article-generator'), $status);
            set_transient(self::notice_key(), $message, HOUR_IN_SECONDS);
            set_transient(self::notice_key() . '_connection', 'access_error', HOUR_IN_SECONDS);
            return new WP_Error('wtai_drive_access', $message);
        }
        delete_transient(self::notice_key());
        set_transient(self::notice_key() . '_connection', 'connected', HOUR_IN_SECONDS);
        return $response;
    }

    public static function files($url, $page_token = '') {
        $folder = self::folder($url);
        if (is_wp_error($folder)) return $folder;
        if (!is_string($page_token) || strlen($page_token) > 4096) return new WP_Error('wtai_drive_page', __('Invalid Drive listing page.', 'wolf-forge-article-generator'));
        // Verify that /open points to a public folder, rather than an unrelated file.
        $metadata = self::api('files/' . $folder['id'], array('fields' => 'id,mimeType', 'supportsAllDrives' => 'true'), $folder['id'], $folder['resource_key']);
        if (is_wp_error($metadata)) return $metadata;
        $details = json_decode(wp_remote_retrieve_body($metadata), true);
        if (($details['mimeType'] ?? '') !== 'application/vnd.google-apps.folder') return new WP_Error('wtai_drive_folder', __('That link does not identify an accessible public folder.', 'wolf-forge-article-generator'));
        $params = array('q' => "'" . $folder['id'] . "' in parents and trashed = false and (mimeType = '" . self::DOCX . "' or mimeType = '" . self::GOOGLE_DOC . "')", 'fields' => 'nextPageToken,files(id,name,mimeType,size,resourceKey)', 'pageSize' => 200, 'orderBy' => 'name_natural', 'supportsAllDrives' => 'true', 'includeItemsFromAllDrives' => 'true');
        if ($page_token !== '') $params['pageToken'] = $page_token;
        $response = self::api('files', $params, $folder['id'], $folder['resource_key']);
        if (is_wp_error($response)) return $response;
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($data) || !isset($data['files']) || !is_array($data['files'])) return new WP_Error('wtai_drive_response', __('Google Drive returned an invalid file list.', 'wolf-forge-article-generator'));
        $files = array();
        foreach ($data['files'] as $file) {
            if (!self::valid_id($file['id'] ?? '') || !is_string($file['name'] ?? null)) continue;
            $is_google_doc = ($file['mimeType'] ?? '') === self::GOOGLE_DOC;
            if (!$is_google_doc && (($file['mimeType'] ?? '') !== self::DOCX || strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'docx')) continue;
            $name = $file['name'];
            if ($is_google_doc && strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'docx') $name .= '.docx';
            $files[] = array('id' => $file['id'], 'name' => $name, 'kind' => $is_google_doc ? 'google_doc' : 'docx', 'size' => $is_google_doc ? 0 : max(0, (int) ($file['size'] ?? 0)), 'resource_key' => $file['resourceKey'] ?? '');
        }
        update_option('wtai_drive_folder_url', $url, false);
        return array('files' => $files, 'next_page' => $data['nextPageToken'] ?? '', 'status' => self::status());
    }

    public static function download($id, $resource = '') {
        if (!self::valid_id($id) || !is_string($resource)) return new WP_Error('wtai_drive_file', __('Invalid Drive file.', 'wolf-forge-article-generator'));
        $response = self::api('files/' . $id, array('fields' => 'id,name,mimeType,size,capabilities(canDownload)', 'supportsAllDrives' => 'true'), $id, $resource);
        if (is_wp_error($response)) return $response;
        $file = json_decode(wp_remote_retrieve_body($response), true);
        $is_google_doc = ($file['mimeType'] ?? '') === self::GOOGLE_DOC;
        if (!is_string($file['name'] ?? null) || (!$is_google_doc && (($file['mimeType'] ?? '') !== self::DOCX || strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'docx')) || ($file['capabilities']['canDownload'] ?? true) === false) return new WP_Error('wtai_drive_file', __('This public file is not a downloadable DOCX or Google Docs document.', 'wolf-forge-article-generator'));
        if (!$is_google_doc && (int) ($file['size'] ?? 0) > wp_max_upload_size()) return new WP_Error('wtai_drive_size', __('The Drive document exceeds the server file size limit.', 'wolf-forge-article-generator'));
        $path = wp_tempnam('wtai-drive.docx');
        if (!$path) return new WP_Error('wtai_drive_temp', __('A temporary file could not be created.', 'wolf-forge-article-generator'));
        $response = $is_google_doc
            ? self::api('files/' . $id . '/export', array('mimeType' => self::DOCX), $id, $resource, $path)
            : self::api('files/' . $id, array('alt' => 'media', 'supportsAllDrives' => 'true'), $id, $resource, $path);
        if (is_wp_error($response)) { wp_delete_file($path); return $response; }
        $size = filesize($path);
        if (!$size || $size > wp_max_upload_size()) { wp_delete_file($path); return new WP_Error('wtai_drive_size', __('The downloaded document is empty or exceeds the server file size limit.', 'wolf-forge-article-generator')); }
        $name = $file['name'];
        if ($is_google_doc && strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'docx') $name .= '.docx';
        return array('name' => sanitize_file_name($name), 'tmp_name' => $path, 'size' => $size, 'error' => UPLOAD_ERR_OK);
    }
}
