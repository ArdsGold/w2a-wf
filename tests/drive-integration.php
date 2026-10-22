<?php
/** php -n with WordPress extensions; Google HTTP is mocked and temporary data is removed. */
if (PHP_SAPI !== 'cli') exit;
define('DOING_AJAX', true);
require dirname(__DIR__, 4) . '/wp-load.php';
require_once dirname(__DIR__) . '/word-to-article-importer.php';
require __DIR__ . '/fixtures.php';
if (!defined('WTAI_GOOGLE_DRIVE_API_KEY')) define('WTAI_GOOGLE_DRIVE_API_KEY', 'test-server-drive-default-123456');
$checks = 0;
function drive_check($condition, $message) {
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
$admins = get_users(array('role' => 'administrator', 'number' => 1));
if (!$admins) throw new RuntimeException('A local administrator is required.');
wp_set_current_user($admins[0]->ID);
wtai_init()->activate();
$original = array();
foreach (array_merge(array_keys(WTAI_Settings::defaults()), array('google_drive_api_key', 'drive_folder_url')) as $name) $original[$name] = get_option('wtai_' . $name, null);
$token = 'wfagdrive' . bin2hex(random_bytes(5));
$ids = array();
$paths = array();
$capture = function ($id, $post) use (&$ids, $token) { if (strpos($post->post_title, $token) !== false) $ids[$id] = $id; };
add_action('save_post', $capture, 10, 2);
$key_a = 'test-drive-key-project-a-123456';
$key_b = 'test-drive-key-project-b-123456';
$folder = 'PublicFolder123';
$file_id = 'PublicDocument123';
$google_id = 'PublicGoogleDoc123';
$url = 'https://drive.google.com/drive/folders/' . $folder . '?resourcekey=0-testResource123';
$mode = 'normal';
$requests = array();
$die = function () { return function ($message = '') { if (is_scalar($message)) echo $message; throw new RuntimeException('drive-json-complete'); }; };
add_filter('wp_die_ajax_handler', $die);
function drive_ajax($input, $valid_nonce = true) {
    $_POST = array_merge(array('nonce' => $valid_nonce ? wp_create_nonce('wtai_ajax_nonce') : 'invalid'), $input);
    $_REQUEST = $_POST;
    ob_start();
    try { wtai_init()->ajax_google_drive(); }
    catch (RuntimeException $error) { if ($error->getMessage() !== 'drive-json-complete') throw $error; }
    return json_decode(ob_get_clean(), true);
}
$http = null;
try {
    WTAI_Settings::save(array('pixabay_fallback' => false));
    foreach (array('convert_numbered_to_dotted', 'unbold_except_headings', 'auto_link_phone_numbers', 'auto_insert_images', 'max_internal_links_per_post', 'max_images_per_post') as $name) delete_option('wtai_' . $name);
    $fresh = WTAI_Settings::read();
    drive_check($fresh['convert_numbered_to_dotted'] && $fresh['unbold_except_headings'] && $fresh['auto_link_phone_numbers'] && $fresh['auto_insert_images'] && $fresh['max_internal_links_per_post'] === 5 && $fresh['max_images_per_post'] === 3, 'Fresh options match the requested screenshot.');
    WTAI_Settings::save(array('auto_insert_images' => false, 'max_internal_links_per_post' => 0));
    wtai_init()->activate();
    drive_check(!WTAI_Settings::read()['auto_insert_images'] && WTAI_Settings::read()['max_internal_links_per_post'] === 0, 'Reactivation preserves saved disabled preferences.');
    foreach (WTAI_Settings::defaults() as $name => $unused) {
        if ($original[$name] === null) delete_option('wtai_' . $name); else update_option('wtai_' . $name, $original[$name]);
    }
    foreach (array('http://drive.google.com/drive/folders/' . $folder, 'https://drive.google.com.evil.test/drive/folders/' . $folder, 'https://user@drive.google.com/drive/folders/' . $folder, 'https://drive.google.com:443/drive/folders/' . $folder, 'https://drive.google.com/drive/folders/short', 'https://drive.google.com/open?id[]=bad') as $bad) drive_check(is_wp_error(WTAI_Google_Drive::folder($bad)), 'Untrusted or malformed folder links are rejected.');
    drive_check(WTAI_Google_Drive::folder($url)['resource_key'] === '0-testResource123', 'Resource-key folder links parse safely.');
    drive_check(WTAI_Google_Drive::folder('https://drive.google.com/drive/u/0/folders/' . $folder)['id'] === $folder, 'Account-specific public folder links are accepted.');
    drive_check(is_wp_error(WTAI_Google_Drive::save_key('bad key')), 'Invalid key formats are rejected.');
    $saved = drive_ajax(array('operation' => 'save_key', 'key' => $key_a));
    drive_check($saved['data']['connection'] === 'unchecked', 'A saved key is not reported as connected before Google confirms it.');
    drive_check($saved['success'] && $saved['data']['source'] === 'override', 'An authorized administrator can save an override through AJAX.');
    drive_check(strpos(wp_json_encode($saved), $key_a) === false && !isset(WTAI_Settings::read()['google_drive_api_key']), 'Credentials are absent from AJAX responses and normal preference snapshots.');
    $paths[] = $archive = wfag_test_archive(wfag_test_document());
    $bytes = file_get_contents($archive);
    $http = function ($preempt, $args, $request_url) use (&$mode, &$requests, $folder, $file_id, $google_id, $token, $bytes) {
        if (strpos($request_url, 'https://www.googleapis.com/drive/v3/') !== 0) return $preempt;
        $requests[] = array('url' => $request_url, 'args' => $args);
        parse_str(wp_parse_url($request_url, PHP_URL_QUERY), $query);
        if ($mode === 'network') return new WP_Error('network', 'private network details');
        if ($mode === 'export-limit' && strpos($request_url, '/export?') !== false) {
            $body = wp_json_encode(array('error' => array('errors' => array(array('reason' => 'exportSizeLimitExceeded')))));
            file_put_contents($args['filename'], $body);
            return array('response' => array('code' => 403), 'body' => $body, 'headers' => array());
        }
        if ($mode === 'quota' || $mode === 'denied') {
            $payload = array('error' => array('message' => 'private upstream details', 'errors' => array(array('reason' => $mode === 'quota' ? 'rateLimitExceeded' : 'forbidden'))));
            $body = wp_json_encode($payload);
            if (!empty($args['filename'])) file_put_contents($args['filename'], $body);
            return array('response' => array('code' => $mode === 'quota' ? 429 : 403), 'body' => $body, 'headers' => array());
        }
        $path = wp_parse_url($request_url, PHP_URL_PATH);
        if ($path === '/drive/v3/files/' . $folder) $payload = array('id' => $folder, 'mimeType' => 'application/vnd.google-apps.folder');
        elseif ($path === '/drive/v3/files') $payload = array('nextPageToken' => empty($query['pageToken']) ? 'next-page' : '', 'files' => array(array('id' => $file_id, 'name' => '1. ' . $token . '.docx', 'mimeType' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'size' => strlen($bytes), 'resourceKey' => '0-testResource123'), array('id' => $google_id, 'name' => '2. ' . $token . ' Google Docs', 'mimeType' => 'application/vnd.google-apps.document', 'size' => PHP_INT_MAX), array('id' => 'bad', 'name' => 'unsafe.docx')));
        elseif (($query['alt'] ?? '') === 'media' || substr($path, -7) === '/export') {
            file_put_contents($args['filename'], $bytes);
            return array('response' => array('code' => 200), 'body' => '', 'headers' => array());
        } elseif ($path === '/drive/v3/files/' . $google_id) $payload = array('id' => $google_id, 'name' => '2. ' . $token . ' Google Docs', 'mimeType' => 'application/vnd.google-apps.document', 'size' => PHP_INT_MAX, 'capabilities' => array('canDownload' => true));
        else $payload = array('id' => $file_id, 'name' => '1. ' . $token . '.docx', 'mimeType' => $mode === 'wrong-type' ? 'text/html' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'size' => $mode === 'oversize' ? wp_max_upload_size() + 1 : strlen($bytes), 'capabilities' => array('canDownload' => $mode !== 'no-download'));
        return array('response' => array('code' => 200), 'body' => wp_json_encode($payload), 'headers' => array());
    };
    add_filter('pre_http_request', $http, 10, 3);
    $listing = drive_ajax(array('operation' => 'list', 'folder_url' => $url));
    drive_check($listing['success'] && count($listing['data']['files']) === 2 && $listing['data']['next_page'] === 'next-page', 'AJAX lists DOCX and native Google Docs records with a continuation token.');
    drive_check(strpos($requests[0]['url'], $key_a) === false && $requests[0]['args']['headers']['X-Goog-Api-Key'] === $key_a && $requests[0]['args']['redirection'] === 0, 'The key uses a server header and redirects remain disabled.');
    drive_check($requests[0]['args']['headers']['X-Goog-Drive-Resource-Keys'] === $folder . '/0-testResource123', 'Public folder resource keys are sent to Google.');
    drive_check(get_option('wtai_drive_folder_url') === $url, 'A successfully loaded folder persists.');
    drive_check($listing['data']['status']['connection'] === 'connected', 'A successful Google response reports the API as connected.');
    drive_check($listing['data']['files'][1]['kind'] === 'google_doc' && substr($listing['data']['files'][1]['name'], -5) === '.docx' && $listing['data']['files'][1]['size'] === 0, 'Google Docs receive Word filenames without treating Google metadata size as export size.');
    $next = WTAI_Google_Drive::files($url, 'next-page');
    drive_check($next['next_page'] === '', 'Subsequent listing pages can reach completion.');
    $download = WTAI_Google_Drive::download($file_id, '0-testResource123');
    drive_check(!is_wp_error($download) && file_get_contents($download['tmp_name']) === $bytes, 'Public DOCX downloads stream into a temporary file.');
    wp_delete_file($download['tmp_name']);
    $import_input = array('operation' => 'import', 'file_id' => $file_id, 'resource_key' => '0-testResource123', 'document_count' => '1', 'document_index' => '0', 'settings' => array('publish_schedule' => 'specific_date', 'schedule_start_date' => '2099-08-01', 'auto_insert_images' => '0', 'max_internal_links_per_post' => '0'));
    $imported = drive_ajax($import_input);
    drive_check($imported['success'] && get_post_status($imported['data']['post_id']) === 'future', 'A downloaded DOCX uses the existing scheduled article importer.');
    $stream = end($requests);
    drive_check(!file_exists($stream['args']['filename']), 'The AJAX importer removes its temporary download.');
    drive_check(get_the_title($imported['data']['post_id']) === $token, 'Drive filenames receive the same leading-number cleanup.');
    $retry = drive_ajax($import_input);
    drive_check($retry['success'] && $retry['data']['existing'] && $retry['data']['post_id'] === $imported['data']['post_id'], 'An identical Drive retry reuses the completed article.');
    $doc_input = $import_input;
    $doc_input['file_id'] = $google_id;
    $doc_input['settings']['schedule_start_date'] = '2099-08-02';
    $doc_import = drive_ajax($doc_input);
    drive_check($doc_import['success'] && get_post_status($doc_import['data']['post_id']) === 'future', 'Native Google Docs export to DOCX and use the normal scheduled importer.');
    drive_check(get_the_title($doc_import['data']['post_id']) === $token . ' Google Docs', 'Exported documents receive filename-number cleanup.');
    $export_request = end($requests);
    drive_check(strpos($export_request['url'], '/export?') !== false && !file_exists($export_request['args']['filename']), 'Google Docs use the export endpoint and remove temporary output after import.');
    $mode = 'export-limit';
    $too_big = WTAI_Google_Drive::download($google_id);
    drive_check(is_wp_error($too_big) && $too_big->get_error_code() === 'wtai_drive_export_size' && WTAI_Google_Drive::status()['connection'] === 'connected', 'Google export-size errors explain the limit without mislabeling the API key as invalid.');
    foreach (array('wrong-type', 'oversize', 'no-download') as $mode) drive_check(is_wp_error(WTAI_Google_Drive::download($file_id)), 'Unsupported, oversized, and restricted files cannot download.');
    $mode = 'quota';
    $quota = drive_ajax(array('operation' => 'list', 'folder_url' => $url));
    drive_check(!$quota['success'] && $quota['data']['code'] === 'wtai_drive_quota' && WTAI_Google_Drive::status()['notice'] !== '', 'Quota errors expose a safe code and a persistent inline notice.');
    drive_check($quota['data']['drive_status']['connection'] === 'quota', 'Quota errors update the card status.');
    drive_check(strpos(wp_json_encode($quota), 'private upstream details') === false && strpos(wp_json_encode($quota), $key_a) === false, 'Quota notices reveal neither upstream payloads nor API credentials.');
    WTAI_Google_Drive::save_key($key_b);
    drive_check(WTAI_Google_Drive::status()['notice'] === '', 'A new override does not inherit the previous key’s quota notice.');
    $mode = 'normal';
    WTAI_Google_Drive::files($url);
    drive_check(end($requests)['args']['headers']['X-Goog-Api-Key'] === $key_b, 'Subsequent Drive requests use the saved override.');
    $mode = 'denied';
    $denied = WTAI_Google_Drive::files($url);
    drive_check(is_wp_error($denied) && $denied->get_error_code() === 'wtai_drive_access', 'Ordinary permission errors are not mislabeled as quota exhaustion.');
    drive_check(WTAI_Google_Drive::status()['connection'] === 'access_error', 'Permission/API errors update the card status.');
    $mode = 'network';
    WTAI_Google_Drive::files($url);
    drive_check(WTAI_Google_Drive::status()['connection'] === 'network_error', 'Network errors are reported separately from key configuration.');
    $before = count($requests);
    wp_set_current_user(0);
    $unauthorized = drive_ajax(array('operation' => 'save_key', 'key' => $key_a));
    drive_check(!$unauthorized['success'] && get_option('wtai_google_drive_api_key') === $key_b && count($requests) === $before, 'Unauthorized users cannot update credentials or access Drive.');
    wp_set_current_user($admins[0]->ID);
    drive_check(drive_ajax(array('operation' => 'save_key', 'key' => $key_a), false) === -1 && get_option('wtai_google_drive_api_key') === $key_b, 'Invalid nonces cannot update credentials.');
    $removed = WTAI_Google_Drive::remove_key();
    drive_check($removed['source'] === ((defined('WTAI_GOOGLE_DRIVE_API_KEY') && WTAI_GOOGLE_DRIVE_API_KEY !== '') ? 'default' : 'missing'), 'Removing the override returns to the server default or reports no configured key.');
    $mode = 'normal';
    WTAI_Google_Drive::files($url);
    drive_check(end($requests)['args']['headers']['X-Goog-Api-Key'] === WTAI_GOOGLE_DRIVE_API_KEY, 'After removing an override, subsequent requests use the server-configured default.');
    echo 'PASS: ' . $checks . " Google Drive integration checks.\n";
} finally {
    if ($http) remove_filter('pre_http_request', $http, 10);
    remove_filter('wp_die_ajax_handler', $die);
    remove_action('save_post', $capture, 10);
    foreach ($ids as $id) { $wpdb->delete($wpdb->prefix . 'wtai_import_history', array('post_id' => $id)); wp_delete_post($id, true); }
    foreach ($paths as $path) if (is_file($path)) unlink($path);
    foreach ($requests as $request) if (!empty($request['args']['filename']) && is_file($request['args']['filename'])) unlink($request['args']['filename']);
    foreach (array($key_a, $key_b, WTAI_GOOGLE_DRIVE_API_KEY) as $key) { $cache_key = 'wtai_drive_' . substr(hash('sha256', $key), 0, 24); delete_transient($cache_key); delete_transient($cache_key . '_connection'); }
    foreach ($original as $name => $value) { if ($value === null) delete_option('wtai_' . $name); else update_option('wtai_' . $name, $value); }
}
