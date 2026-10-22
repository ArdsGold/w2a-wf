<?php
/** Local WordPress integration. Pixabay HTTP is mocked; all test posts/files/keys are restored. */
if (PHP_SAPI !== 'cli') exit;
define('DOING_AJAX', true);
define('WTAI_PIXABAY_API_KEY', 'test-pixabay-default-123456');
require dirname(__DIR__, 4) . '/wp-load.php';
require_once dirname(__DIR__) . '/word-to-article-importer.php';
require __DIR__ . '/fixtures.php';
$checks = 0;
function pixabay_check($condition, $message) {
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
$admins = get_users(array('role' => 'administrator', 'number' => 1));
wp_set_current_user($admins[0]->ID);
$original = array();
foreach (array_merge(array_keys(WTAI_Settings::defaults()), array('pixabay_api_key')) as $name) $original[$name] = get_option('wtai_' . $name, null);
$before_transients = $wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient_%wtai_pixabay%'");
$token = 'wfapix' . bin2hex(random_bytes(5));
$first_id = random_int(100000000, 900000000);
$attachments = $posts = $paths = $requests = array();
$capture = function ($id) use (&$attachments) { $attachments[] = $id; };
add_action('add_attachment', $capture);
$mode = 'good';
$api_code = 200;
$hits = array();
for ($i = 0; $i < 8; $i++) $hits[] = array('id' => $first_id + $i, 'type' => 'photo', 'tags' => "$token forest photography", 'largeImageURL' => 'https://cdn.pixabay.com/photo/test/' . ($first_id + $i) . '.png', 'pageURL' => 'https://pixabay.com/photos/test-' . ($first_id + $i) . '/', 'user' => 'Test photographer');
$chunk = function ($type, $data) { return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data)); };
$png = "\x89PNG\r\n\x1a\n" . $chunk('IHDR', pack('NNCCCCC', 640, 320, 8, 2, 0, 0, 0)) . $chunk('IDAT', gzcompress(str_repeat("\0" . str_repeat("\x40\x80\x20", 640), 320))) . $chunk('IEND', '');
$http = function ($pre, $args, $url) use (&$requests, &$mode, &$api_code, &$hits, $png) {
    if (strpos($url, 'https://pixabay.com/api/?') === 0) {
        parse_str(wp_parse_url($url, PHP_URL_QUERY), $query);
        $requests[] = array('kind' => 'search', 'query' => $query, 'args' => $args);
        return array('response' => array('code' => $api_code), 'headers' => array('x-ratelimit-reset' => '60'), 'body' => wp_json_encode(array('hits' => $hits)));
    }
    if (strpos($url, 'https://cdn.pixabay.com/photo/test/') === 0) {
        $requests[] = array('kind' => 'download', 'args' => $args);
        file_put_contents($args['filename'], $mode === 'html' ? '<html>not a photo</html>' : ($mode === 'oversize' ? str_repeat('x', 10485761) : $png));
        return array('response' => array('code' => $mode === 'redirect' ? 302 : 200), 'headers' => array(), 'body' => '');
    }
    return new WP_Error('unexpected_test_http', 'Unexpected network request blocked by test.');
};
add_filter('pre_http_request', $http, 10, 3);
$die = function () { return function ($message = '') { if (is_scalar($message)) echo $message; throw new RuntimeException('pixabay-json-complete'); }; };
add_filter('wp_die_ajax_handler', $die);
function pixabay_ajax($input, $valid_nonce = true) {
    $_POST = array_merge(array('nonce' => $valid_nonce ? wp_create_nonce('wtai_ajax_nonce') : 'invalid'), $input);
    $_REQUEST = $_POST;
    ob_start();
    try { wtai_init()->ajax_pixabay(); }
    catch (RuntimeException $error) { if ($error->getMessage() !== 'pixabay-json-complete') throw $error; }
    return json_decode(ob_get_clean(), true);
}
try {
    delete_option('wtai_pixabay_api_key');
    pixabay_check(WTAI_Pixabay::status()['source'] === 'default', 'The optional server default is recognized.');
    pixabay_check(is_wp_error(WTAI_Pixabay::save_key('<invalid>')), 'Malformed credentials are rejected.');
    $key = 'test-pixabay-override-' . $token;
    $saved = pixabay_ajax(array('operation' => 'save_key', 'key' => $key));
    pixabay_check($saved['success'] && $saved['data']['source'] === 'override', 'An administrator can save a private override through AJAX.');
    wp_set_current_user(0);
    $denied = pixabay_ajax(array('operation' => 'remove_key'));
    pixabay_check(!$denied['success'] && get_option('wtai_pixabay_api_key') === $key, 'Unauthorized users cannot remove credentials.');
    pixabay_check(is_wp_error(WTAI_Pixabay::download($photos[0] ?? array())), 'Users without upload permission cannot download media.');
    wp_set_current_user($admins[0]->ID);
    pixabay_check(pixabay_ajax(array('operation' => 'remove_key'), false) === -1 && get_option('wtai_pixabay_api_key') === $key, 'A valid nonce is required for credential changes.');
    pixabay_check(strpos(wp_json_encode(WTAI_Pixabay::status()), $key) === false, 'Status never returns the credential.');
    pixabay_check(!array_key_exists('pixabay_api_key', WTAI_Settings::read()), 'Credentials are absent from ordinary settings.');
    pixabay_check(!in_array($wpdb->get_var("SELECT autoload FROM {$wpdb->options} WHERE option_name = 'wtai_pixabay_api_key'"), array('yes', 'on', 'auto-on'), true), 'The private credential is not autoloaded.');
    pixabay_check(!WTAI_Settings::defaults()['pixabay_fallback'], 'The optional fallback defaults to off.');
    WTAI_Settings::save(array('pixabay_fallback' => true));
    pixabay_check(WTAI_Settings::read()['pixabay_fallback'], 'The fallback preference persists.');
    WTAI_Settings::save(array('pixabay_fallback' => false));
    pixabay_check(!WTAI_Settings::read()['pixabay_fallback'], 'Disabling the preference persists.');
    foreach (array('http://cdn.pixabay.com/photo/test.png', 'https://cdn.pixabay.com.evil.test/photo/a.png', 'https://user@cdn.pixabay.com/photo/a.png', 'https://cdn.pixabay.com:444/photo/a.png', 'https://127.0.0.1/a.png', 'https://pixabay.com/users/a/') as $url) pixabay_check(!WTAI_Pixabay::allowed_url($url, true), 'Untrusted photo URL rejected.');
    $photos = WTAI_Pixabay::search("$token forest photography");
    pixabay_check(count($photos) === 8, 'Photo metadata is accepted from the provider.');
    pixabay_check($requests[0]['query']['key'] === $key && $requests[0]['query']['image_type'] === 'photo' && $requests[0]['query']['safesearch'] === 'true', 'Searches authenticate privately and request safe photos.');
    pixabay_check($requests[0]['args']['redirection'] === 0, 'API redirects are disabled.');
    $before = count($requests);
    WTAI_Pixabay::search("$token forest photography");
    pixabay_check(count($requests) === $before, 'Repeated searches use the 24-hour cache.');
    $timeouts = $wpdb->get_col("SELECT option_value FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_wtai_pixabay_%'");
    pixabay_check((bool) array_filter($timeouts, function ($expiry) { return (int) $expiry >= time() + 86300; }), 'Search cache expires after 24 hours.');
    $good_hits = $hits;
    $hits[] = array_merge($hits[0], array('id' => $first_id + 20, 'tags' => "$token forest companyLogo"));
    $hits[] = array_merge($hits[0], array('id' => $first_id + 21, 'type' => 'vector'));
    $hits[] = array_merge($hits[0], array('id' => $first_id + 22, 'largeImageURL' => 'https://evil.test/image.png'));
    pixabay_check(count(WTAI_Pixabay::search("$token filtered")) === 8, 'Labeled logos, nonphotos, and off-provider downloads are excluded.');
    $hits = $good_hits;
    $settings = array('image_source' => 'media_library', 'max_images_per_post' => 3, 'pixabay_fallback' => true);
    $body = str_repeat('<p>' . str_repeat("$token forest photography ", 40) . '</p>', 3) . '<h2>Conclusion</h2><p>End.</p>';
    $matcher = new WTAI_Image_Matcher();
    $matcher->insert_images($body, $settings, "$token forest photography");
    pixabay_check(count($requests) === $before + 1 && !$attachments, 'Preview matching never searches or downloads Pixabay photos.');
    $html = $matcher->insert_images($body, $settings, "$token forest photography", true);
    $report = $matcher->get_report();
    pixabay_check($report['inserted'] === 3 && count($attachments) === 3, 'A real import saves three matching photos and inserts them.');
    pixabay_check($report['pixabay_downloaded'] === 3 && strpos($report['message'], 'Pixabay: 3') !== false, 'The result explains the fallback additions.');
    pixabay_check(strrpos($html, '<figure') < strpos($html, '<h2>Conclusion'), 'Downloaded images stay before Conclusion.');
    pixabay_check($report['placements'][1]['actual_percent'] - $report['placements'][0]['actual_percent'] >= 27, 'Downloaded images retain the 100-word spacing rule.');
    pixabay_check(strpos($html, 'cdn.pixabay.com') === false && strpos($html, 'https://pixabay.com/photos/') !== false, 'Article images use local media and credit the source page.');
    pixabay_check(substr_count($html, '<figcaption>') === 3, 'Each image has one valid caption with credit.');
    foreach ($attachments as $id) {
        pixabay_check(get_post_meta($id, '_wp_attachment_image_alt', true) === "$token forest photography", 'Alt text uses the photo tags without inventing an article-specific description.');
        pixabay_check(is_file(get_attached_file($id)) && get_post_meta($id, '_wtai_pixabay_id', true), 'The attachment has a local file and reusable provider ID.');
    }
    $before = count($requests);
    $again = $matcher->insert_images($body, $settings, "$token forest photography", true);
    pixabay_check(count($requests) === $before && count($attachments) === 3 && $matcher->get_report()['inserted'] === 3, 'Later imports reuse Media Library photos without another provider request.');
    pixabay_check(WTAI_Pixabay::download($photos[0]) === $attachments[0] && count($requests) === $before, 'Provider IDs deduplicate direct retries.');
    $matcher->insert_images('<p>' . "$token forest photography" . '</p>', $settings, "$token forest photography", true);
    pixabay_check(count($requests) === $before && $matcher->get_report()['inserted'] === 1, 'A spacing-only shortfall does not trigger pointless downloads.');
    $matcher->insert_images($body, array_merge($settings, array('pixabay_fallback' => false)), $token, true);
    pixabay_check(count($requests) === $before, 'Disabling the fallback prevents all provider activity.');
    $xml = str_repeat('<w:p><w:r><w:t>' . str_repeat("$token forest photography ", 40) . '</w:t></w:r></w:p>', 3);
    $paths[] = $success_path = wfag_test_archive(wfag_test_document($xml));
    $success_settings = WTAI_Settings::for_import(array_merge($settings, array('auto_insert_images' => true, 'max_internal_links_per_post' => 0, 'publish_schedule' => 'immediate')));
    $importer = new WTAI_Importer(wtai_init()->get_error_handler());
    $success_file = array('name' => "$token Forest.docx", 'tmp_name' => $success_path);
    $success = $importer->import($success_file, $success_settings, null);
    $posts[] = $success['post_id'];
    pixabay_check($success['images']['inserted'] === 3 && substr_count(get_post_field('post_content', $success['post_id']), 'wtai-inserted-image') === 3, 'Real DOCX publishing preserves local Pixabay attachments and the image report.');
    pixabay_check(substr_count($success['image_previews'], 'class="wtai-result-thumbnail"') === 3 && strpos($success['image_previews'], 'cdn.pixabay.com') === false, 'Pixabay result thumbnails use the downloaded local media files.');
    $retry = $importer->import($success_file, $success_settings, null);
    pixabay_check($retry['existing'] && $retry['post_id'] === $success['post_id'] && count($requests) === $before, 'An identical completed import retries without new posts, searches, or downloads.');
    pixabay_check($retry['image_previews'] === $success['image_previews'], 'Reused Pixabay imports keep their thumbnail row.');
    $long_body = str_repeat('<p>' . str_repeat("$token forest photography ", 40) . '</p>', 12);
    $matcher->insert_images($long_body, array_merge($settings, array('max_images_per_post' => 20)), "$token forest photography", true);
    $new_requests = array_slice($requests, $before);
    pixabay_check(count(array_filter($new_requests, function ($request) { return $request['kind'] === 'search'; })) <= 3, 'Each article uses at most three search queries.');
    pixabay_check(count(array_filter($new_requests, function ($request) { return $request['kind'] === 'download'; })) === 5 && count($attachments) === 8, 'The fallback limits new downloads to five while keeping existing matches.');
    pixabay_check($matcher->get_report()['inserted'] === 8, 'A large image maximum still permits fewer matching images.');
    $before_downloads = count($attachments);
    $matcher->insert_images('<p>quartz mountain glacier</p>', $settings, 'quartz mountain glacier', true);
    pixabay_check($matcher->get_report()['inserted'] === 0 && count($attachments) === $before_downloads, 'Unrelated provider tags never cause a download.');
    $before = count($requests);
    $matcher->insert_images($body, array_merge($settings, array('image_source' => 'gbp')), $token, true);
    pixabay_check(count($requests) === $before, 'The GBP-only source never calls the Pixabay fallback.');
    foreach (array('redirect', 'html', 'oversize') as $i => $failure) {
        $mode = $failure;
        $bad_photo = array_merge($photos[0], array('id' => $first_id + 40 + $i));
        pixabay_check(is_wp_error(WTAI_Pixabay::download($bad_photo)), 'Invalid download is rejected: ' . $failure);
        $request = end($requests);
        pixabay_check(!is_file($request['args']['filename']), 'Rejected downloads remove temporary files.');
    }
    $mode = 'good';
    $api_code = 429;
    pixabay_check(is_wp_error(WTAI_Pixabay::search("$token limited")), 'Rate limit is a recoverable error.');
    $before = count($requests);
    pixabay_check(is_wp_error(WTAI_Pixabay::search("$token limited again")) && count($requests) === $before, 'Rate-limit cooldown prevents repeated requests.');
    pixabay_check(count(WTAI_Pixabay::search("$token forest photography")) === 8 && count($requests) === $before, 'Cached searches still work during cooldown.');
    // Real DOCX import: network failure cannot prevent the article being saved.
    WTAI_Pixabay::save_key('test-other-project-' . $token);
    $api_code = 403;
    $xml = str_repeat('<w:p><w:r><w:t>' . str_repeat('unmatchedtopic lavender garden ', 40) . '</w:t></w:r></w:p>', 3);
    $path = wfag_test_archive(wfag_test_document($xml));
    $paths[] = $path;
    $import_settings = WTAI_Settings::for_import(array('pixabay_fallback' => true, 'auto_insert_images' => true, 'image_source' => 'media_library', 'max_images_per_post' => 3, 'max_internal_links_per_post' => 0, 'publish_schedule' => 'immediate'));
    $result = (new WTAI_Importer(wtai_init()->get_error_handler()))->import(array('name' => "$token Garden.docx", 'tmp_name' => $path), $import_settings, null);
    $posts[] = $result['post_id'];
    pixabay_check($result['status'] === 'publish' && strpos($result['images']['message'], 'HTTP 403') !== false, 'Provider access failure preserves successful article publishing and reports the cause.');
    $history = $wpdb->get_var($wpdb->prepare("SELECT settings FROM {$wpdb->prefix}wtai_import_history WHERE post_id = %d", $result['post_id']));
    pixabay_check(strpos($history, 'test-other-project-') === false && strpos(get_post_field('post_content', $result['post_id']), 'test-other-project-') === false, 'No API credentials enter history or article content.');
    pixabay_check(WTAI_Pixabay::remove_key()['source'] === 'default', 'Removing an override restores the server default.');
    echo "PASS: $checks Pixabay WordPress integration checks (mocked HTTP).\n";
} finally {
    remove_filter('pre_http_request', $http, 10);
    remove_filter('wp_die_ajax_handler', $die);
    remove_action('add_attachment', $capture);
    foreach ($posts as $id) { $wpdb->delete($wpdb->prefix . 'wtai_import_history', array('post_id' => $id)); wp_delete_post($id, true); }
    foreach ($attachments as $id) wp_delete_attachment($id, true);
    foreach ($paths as $path) if (is_file($path)) unlink($path);
    foreach ($original as $name => $value) { if ($value === null) delete_option('wtai_' . $name); else update_option('wtai_' . $name, $value, false); }
    $after = $wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient_%wtai_pixabay%'");
    foreach (array_diff($after, $before_transients) as $name) delete_option($name);
    WTAI_Media_Index::invalidate();
}
