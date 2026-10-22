<?php
/** Run: php -n wp-content/plugins/w2a-wf/tests/run.php */
if (PHP_SAPI !== 'cli') exit;
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    if (error_reporting() & $severity) throw new ErrorException($message, 0, $severity, $file, $line);
    return false;
});
define('ABSPATH', dirname(__DIR__, 4) . '/');
function __($text, $domain = '') { return $text; }
function wp_strip_all_tags($text) { return strip_tags($text); }
function esc_html($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
function esc_attr($text) { return esc_html($text); }
function esc_url_raw($url, $protocols = null) { return preg_match('~^(https?://|mailto:|tel:)~i', $url) ? $url : ''; }
function esc_url($url, $protocols = null) { return esc_attr(esc_url_raw($url)); }
function wp_timezone() { return new DateTimeZone($GLOBALS['wfag_test_timezone'] ?? 'Asia/Manila'); }
function wp_date($format, $timestamp, $timezone = null) { return (new DateTime('@' . $timestamp))->setTimezone($timezone ?: wp_timezone())->format($format); }
function sanitize_text_field($text) { return trim(strip_tags($text)); }
function absint($value) { return abs((int) $value); }
function get_current_user_id() { return 1; }
function get_option($key, $default = false) { return array_key_exists($key, $GLOBALS['wfag_test_options'] ?? array()) ? $GLOBALS['wfag_test_options'][$key] : $default; }
function update_option($key, $value) {
    if (!empty($GLOBALS['wfag_fail_save'])) return false;
    $GLOBALS['wfag_test_options'][$key] = $value;
    return true;
}
function add_option($key, $value) { return get_option($key, null) === null ? update_option($key, $value) : false; }
function get_user_by($field, $id) { return $id === 1; }
function user_can($id, $capability) { return $id === 1; }
function term_exists($id, $taxonomy) { return $id === 1; }
function wp_unslash($value) { return $value; }
function wp_json_encode($value) { return json_encode($value); }
function is_wp_error($value) { return $value instanceof WP_Error; }
function wp_safe_remote_request($url, $args) {
    $GLOBALS['wfag_google_requests'][] = array('url' => $url, 'args' => $args);
    if (isset($GLOBALS['wfag_google_failure'])) return $GLOBALS['wfag_google_failure'];
    if (strpos($url, 'places:searchText') !== false) $body = array('places' => $GLOBALS['wfag_google_search'] ?? array(array('id' => 'ChIJsearchresult0001')));
    elseif (strpos($url, '/media?') !== false) $body = array('photoUri' => $GLOBALS['wfag_google_photo_uri'] ?? 'https://lh3.googleusercontent.com/places/fresh-image');
    else {
        $id = basename(wp_parse_url($url, PHP_URL_PATH));
        $photos = array();
        for ($slot = 0; $slot < ($GLOBALS['wfag_google_count'] ?? 3); $slot++) $photos[] = array('name' => 'places/' . $id . '/photos/photo' . $slot, 'googleMapsUri' => 'https://www.google.com/maps/photo/example', 'authorAttributions' => array(array('displayName' => 'Photo & Author', 'uri' => 'https://www.google.com/maps/contrib/example', 'photoUri' => 'https://lh3.googleusercontent.com/avatar')));
        $body = array('id' => $id, 'displayName' => array('text' => 'Test Business'), 'photos' => $photos);
    }
    return array('response' => array('code' => 200), 'body' => json_encode($body));
}
function wp_safe_remote_get($url, $args) {
    $GLOBALS['wfag_google_requests'][] = array('url' => $url, 'args' => $args);
    return array('headers' => array('location' => $GLOBALS['wfag_google_redirect'] ?? 'https://www.google.com/maps/?query_place_id=ChIJshortresult0001'));
}
function wp_remote_retrieve_body($response) { return $response['body'] ?? ''; }
function wp_remote_retrieve_response_code($response) { return $response['response']['code'] ?? 0; }
function wp_remote_retrieve_header($response, $name) { return $response['headers'][$name] ?? ''; }
function wp_max_upload_size() { return 100 * 1024 * 1024; }
function wp_get_attachment_image_url($id, $size) { return 'https://example.com/' . $id . '.jpg'; }
function wp_get_attachment_url($id) { return wp_get_attachment_image_url($id, 'large'); }
function get_post_meta($id, $key, $single) { return $GLOBALS['wfag_test_seo_meta'][$id][$key] ?? ($key === '_wp_attachment_image_alt' ? ($GLOBALS['wfag_test_media'][$id]['alt'] ?? '') : ($key === '_wp_attached_file' ? ($GLOBALS['wfag_test_media'][$id]['filename'] ?? '') : '')); }
function get_theme_mod($key, $default = false) { return $GLOBALS['wfag_test_theme_mods'][$key] ?? $default; }
function update_post_meta($id, $key, $value) { $GLOBALS['wfag_test_seo_meta'][$id][$key] = $value; }
function remove_accents($text) { return $text; }
function get_transient($key) { return $GLOBALS['wfag_transients'][$key] ?? false; }
function set_transient($key, $value, $expires) { $GLOBALS['wfag_transients'][$key] = $value; }
function delete_transient($key) { unset($GLOBALS['wfag_transients'][$key]); }
function get_the_title($id) { return $GLOBALS['wfag_test_media'][$id]['title'] ?? ''; }
function get_post_field($field, $id) { return $field === 'post_excerpt' ? ($GLOBALS['wfag_test_media'][$id]['caption'] ?? '') : ''; }
function get_edit_post_link($id, $context) { return '/post.php?post=' . $id; }
function home_url($path = '') { return 'https://example.com' . $path; }
function wp_parse_url($url, $component = -1) { return parse_url($url, $component); }
function get_permalink($id) { return $GLOBALS['wfag_test_posts'][$id]['url'] ?? 'https://example.com/?p=' . $id; }
function get_post($id) {
    if (!isset($GLOBALS['wfag_test_posts'][$id])) return null;
    $record = $GLOBALS['wfag_test_posts'][$id];
    return (object) array('ID' => $id, 'post_type' => $record['type'] ?? 'post', 'post_status' => $record['status'] ?? 'publish', 'post_password' => $record['password'] ?? '');
}
function url_to_postid($url) {
    foreach ($GLOBALS['wfag_test_posts'] ?? array() as $id => $post) if ($url === get_permalink($id)) return $id;
    return 0;
}
function wp_get_attachment_image($id, $size, $icon, $attributes) {
    if (!empty($GLOBALS['wfag_test_media'][$id]['missing'])) return '';
    return '<img src="https://example.com/' . $id . '.jpg" class="wp-image-' . $id . '" alt="' . esc_attr(get_post_meta($id, '_wp_attachment_image_alt', true)) . '" />';
}
class WP_Query {
    public $posts = array();
    public $found_posts = 0;
    public $max_num_pages = 0;
    public function __construct($args) {
        $records = ($args['post_type'] ?? '') === 'post' ? ($GLOBALS['wfag_test_posts'] ?? array()) : ($GLOBALS['wfag_test_media'] ?? array());
        if (isset($args['post__in'])) $records = array_intersect_key($records, array_flip($args['post__in']));
        $this->found_posts = count($records);
        $this->max_num_pages = (int) ceil(count($records) / $args['posts_per_page']);
        foreach (array_slice($records, (($args['paged'] ?? 1) - 1) * $args['posts_per_page'], $args['posts_per_page'], true) as $id => $record) {
            $this->posts[] = (object) array('ID' => $id, 'post_title' => $record['title'] ?? '', 'post_excerpt' => $record['caption'] ?? '', 'post_content' => $record['description'] ?? '', 'post_type' => $record['type'] ?? 'post', 'post_status' => $record['status'] ?? 'publish', 'post_password' => $record['password'] ?? '');
        }
    }
}
function get_plugins() { return $GLOBALS['wfag_test_plugins'] ?? array(); }
function is_plugin_active($file) { return in_array($file, $GLOBALS['wfag_test_active_plugins'] ?? array(), true); }
class WP_Error {
    private $message;
    private $code;
    public function __construct($code, $message) { $this->message = $message; $this->code = $code; }
    public function get_error_message() { return $this->message; }
    public function get_error_code() { return $this->code; }
}
foreach (array('settings', 'scheduler', 'error-handler', 'docx-parser', 'content-processor', 'media-index', 'image-matcher', 'gbp-photos', 'internal-linker', 'seo', 'server-info') as $class) {
    require_once dirname(__DIR__) . '/includes/class-' . $class . '.php';
}
require __DIR__ . '/fixtures.php';
$checks = 0;
function check($condition, $message) {
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
function rejects($callback, $message) {
    try { $callback(); } catch (Throwable $error) { check(true, $message); return; }
    check(false, $message);
}

$paths = array();
try {
    $first_launch = WTAI_Settings::defaults();
    check($first_launch['convert_numbered_to_dotted'] && $first_launch['unbold_except_headings'] && $first_launch['auto_link_phone_numbers'] && $first_launch['auto_insert_images'], 'First-launch content processing checkboxes are enabled.');
    check($first_launch['max_internal_links_per_post'] === 5 && $first_launch['max_images_per_post'] === 3, 'First-launch link and image counts match the requested defaults.');
    $settings = WTAI_Settings::for_import(array('max_images_per_post' => -8, 'default_author' => 999, 'schedule_start_date' => '2026-02-30'));
    check($settings['max_images_per_post'] === 0, 'Negative image counts clamp to zero.');
    check($settings['default_author'] === 1, 'Invalid authors fall back.');
    check($settings['schedule_start_date'] === '', 'Impossible dates are rejected.');
    check(WTAI_Settings::sanitize('schedule_start_date', array('bad')) === '', 'Array input is rejected.');
    $preferences = array('publish_schedule' => 'custom_range', 'schedule_start_date' => '2027-01-01', 'schedule_end_date' => '2027-02-01', 'schedule_interval' => 'week', 'auto_insert_images' => true, 'max_images_per_post' => 6);
    $saved = WTAI_Settings::save($preferences);
    check(is_array($saved) && WTAI_Settings::read() === $saved, 'Options persist through the shared WordPress option store.');
    $preferences['publish_schedule'] = 'immediate';
    $preferences['auto_insert_images'] = false;
    $immediate = WTAI_Settings::save($preferences);
    check($immediate['schedule_start_date'] === '2027-01-01' && $immediate['schedule_end_date'] === '2027-02-01', 'Changing to immediate mode retains both saved dates.');
    check($immediate['schedule_interval'] === 'week' && !$immediate['auto_insert_images'], 'Inactive preferences and unchecked booleans persist.');
    $import_settings = WTAI_Settings::for_import($preferences);
    check($import_settings['schedule_start_date'] === '' && $import_settings['schedule_end_date'] === '' && $import_settings['schedule_interval'] === 'day', 'Immediate imports ignore remembered dates and intervals.');
    $preferences['publish_schedule'] = 'specific_date';
    $specific = WTAI_Settings::save($preferences);
    check($specific['schedule_end_date'] === '2027-02-01', 'Specific-date preferences remember the inactive range end.');
    check(WTAI_Settings::for_import($preferences)['schedule_end_date'] === '', 'Specific-date imports ignore the inactive range end.');
    $GLOBALS['wfag_fail_save'] = true;
    $preferences['max_images_per_post'] = 9;
    check(WTAI_Settings::save($preferences) instanceof WP_Error, 'A failed options write is not reported as saved.');
    unset($GLOBALS['wfag_fail_save'], $GLOBALS['wfag_test_options']);
    $month_settings = WTAI_Settings::for_import(array('publish_schedule' => 'specific_month', 'schedule_month' => '2028-02', 'schedule_start_date' => '2028-01-01', 'schedule_end_date' => '2028-12-31', 'schedule_interval' => 'week'));
    check($month_settings['schedule_start_date'] === '' && $month_settings['schedule_end_date'] === '' && $month_settings['schedule_interval'] === 'day', 'Month scheduling ignores remembered dates and intervals.');
    check(WTAI_Settings::sanitize('schedule_month', '2028-13') === '' && WTAI_Settings::sanitize('schedule_month', array()) === '', 'Invalid month preferences are rejected.');
    WTAI_Settings::save(array('publish_schedule' => 'specific_month', 'schedule_month' => '2028-02'));
    WTAI_Settings::save(array('publish_schedule' => 'immediate'));
    check(WTAI_Settings::read()['schedule_month'] === '2028-02', 'The selected month survives switching publish modes.');
    check(!array_key_exists('schedule_month', WTAI_Settings::for_import(array('publish_schedule' => 'immediate'))), 'Inactive month options do not change legacy import fingerprints.');
    unset($GLOBALS['wfag_test_options']);
    $dates = WTAI_Scheduler::calculate($month_settings, 3);
    check(array_map(function ($date) { return $date->format('Y-m-d H:i'); }, $dates) === array('2028-02-01 09:00', '2028-02-15 09:00', '2028-02-29 09:00'), 'Three articles span a leap-year February evenly.');
    $month_settings['schedule_month'] = '2027-02';
    $dates = WTAI_Scheduler::calculate($month_settings, 30);
    check($dates[27]->format('Y-m-d') === '2027-02-28' && $dates[28]->format('Y-m-d') === '2027-02-01' && $dates[29]->format('Y-m-d') === '2027-02-02', 'February overflow repeats from day one without entering March.');
    $month_settings['schedule_month'] = '2027-01';
    $dates = WTAI_Scheduler::calculate($month_settings, 31);
    check($dates[0]->format('d') === '01' && $dates[30]->format('d') === '31', 'A full 31-day month gives one article to each day.');
    check(WTAI_Scheduler::calculate($month_settings, 1)[0]->format('Y-m-d') === '2027-01-01', 'A single article uses the first day of the month.');
    $month_settings['schedule_month'] = '2027-11';
    $dates = WTAI_Scheduler::calculate($month_settings, 4);
    check(array_map(function ($date) { return $date->format('d'); }, $dates) === array('01', '11', '20', '30'), 'A smaller batch is spread across the whole month with gaps differing by at most one day.');
    $dates = WTAI_Scheduler::calculate($month_settings, 1000);
    check(count($dates) === 1000 && $dates[30] !== $dates[0] && $dates[999]->format('Y-m-d') === '2027-11-10', 'Month scheduling supports full batches and independent repeated date objects.');
    foreach (array('', '2027-00', '2027-13', '2027-2', 'not-a-month') as $invalid_month) {
        $month_settings['schedule_month'] = $invalid_month;
        check(WTAI_Scheduler::calculate($month_settings, 3) instanceof WP_Error, 'Missing or malformed month values fail before importing.');
    }
    $schedule = array('publish_schedule' => 'specific_date', 'schedule_start_date' => '2027-01-31', 'schedule_end_date' => '', 'schedule_interval' => 'month');
    $dates = WTAI_Scheduler::calculate($schedule, 3);
    check(is_array($dates), 'Specific-date scheduling works without an end date.');
    check(array_map(function ($date) { return $date->format('Y-m-d H:i'); }, $dates) === array('2027-01-31 09:00', '2027-02-28 09:00', '2027-03-31 09:00'), 'Monthly schedules retain the original anchor day.');
    $schedule['schedule_start_date'] = '2028-01-31';
    check(WTAI_Scheduler::calculate($schedule, 2)[1]->format('Y-m-d') === '2028-02-29', 'Leap-year month end.');
    $schedule['publish_schedule'] = 'custom_range';
    $schedule['schedule_end_date'] = '2028-02-29';
    $dates = WTAI_Scheduler::calculate($schedule, 5);
    check(array_map(function ($date) { return $date->format('Y-m-d'); }, $dates) === array('2028-01-31', '2028-02-29', '2028-01-31', '2028-02-29', '2028-01-31'), 'Monthly ranges repeat their month-end dates for extra documents.');
    $daily = array('publish_schedule' => 'custom_range', 'schedule_start_date' => '2026-11-01', 'schedule_end_date' => '2026-11-03', 'schedule_interval' => 'day');
    $dates = WTAI_Scheduler::calculate($daily, 7);
    check(array_map(function ($date) { return $date->format('Y-m-d H:i'); }, $dates) === array('2026-11-01 09:00', '2026-11-02 09:00', '2026-11-03 09:00', '2026-11-01 09:00', '2026-11-02 09:00', '2026-11-03 09:00', '2026-11-01 09:00'), 'Daily ranges cycle in document order, including their end date.');
    check($dates[0] !== $dates[3], 'Repeated dates are independent DateTime objects.');
    $daily['schedule_end_date'] = '2026-11-01';
    $dates = WTAI_Scheduler::calculate($daily, 1000);
    check(count($dates) === 1000 && $dates[999]->format('Y-m-d H:i') === '2026-11-01 09:00', 'A single-day range accepts the full batch at the same time.');
    $daily['schedule_end_date'] = '2026-11-10';
    $daily['schedule_interval'] = 'week';
    check(array_map(function ($date) { return $date->format('Y-m-d'); }, WTAI_Scheduler::calculate($daily, 4)) === array('2026-11-01', '2026-11-08', '2026-11-01', '2026-11-08'), 'Weekly ranges cycle only through valid interval dates.');
    $schedule['schedule_end_date'] = '2028-01-01';
    check(WTAI_Scheduler::calculate($schedule, 1) instanceof WP_Error, 'Reversed ranges are rejected.');
    $schedule['publish_schedule'] = 'immediate';
    check(WTAI_Scheduler::calculate($schedule, 2) === array(null, null), 'Immediate imports use null dates.');
    check(WTAI_Scheduler::calculate($schedule, 1001) instanceof WP_Error, 'Batch size is bounded.');
    $preview = WTAI_Scheduler::preview(array('publish_schedule' => 'specific_month', 'schedule_month' => '2096-02'), 3);
    check(array_column($preview['dates'], 'date') === array('2096-02-01', '2096-02-15', '2096-02-29'), 'Schedule preview shares leap-month spacing with actual imports.');
    check($preview['publish_now'] === 0 && $preview['timezone'] === 'Asia/Manila', 'Preview times use WordPress timezone and distinguish future publication.');
    $preview = WTAI_Scheduler::preview(array('publish_schedule' => 'custom_range', 'schedule_start_date' => '2099-11-01', 'schedule_end_date' => '2099-11-03', 'schedule_interval' => 'day'), 7);
    check($preview['repeated'] === 4 && array_column($preview['dates'], 'article') === array(1, 2, 3, 4, 5, 7), 'Preview reports reused dates and includes the last document in larger batches.');
    check($preview['dates'][5]['date'] === '2099-11-01', 'The final preview date reflects date-range overflow looping.');
    $preview = WTAI_Scheduler::preview(array('publish_schedule' => 'specific_date', 'schedule_start_date' => '2000-01-01', 'schedule_interval' => 'day'), 2);
    check($preview['publish_now'] === 2, 'Preview warns when all selected dates will publish immediately.');
    check(WTAI_Scheduler::preview(array('publish_schedule' => 'specific_month', 'schedule_month' => ''), 3) instanceof WP_Error, 'Incomplete dates produce the same validation errors in preview and import.');
    check(WTAI_Scheduler::preview(array('publish_schedule' => 'immediate'), 0) instanceof WP_Error, 'Preview enforces the same batch bounds as importing.');
    $GLOBALS['wfag_test_timezone'] = 'America/Los_Angeles';
    $schedule = array('publish_schedule' => 'specific_date', 'schedule_start_date' => '2027-03-13', 'schedule_end_date' => '', 'schedule_interval' => 'day');
    $dates = WTAI_Scheduler::calculate($schedule, 3);
    check($dates[0]->format('H:i') === '09:00' && $dates[1]->format('H:i') === '09:00' && $dates[0]->format('P') !== $dates[1]->format('P'), 'Daily schedules retain local wall time across DST.');
    $dates = WTAI_Scheduler::calculate(array('publish_schedule' => 'specific_month', 'schedule_month' => '2027-03'), 31);
    check($dates[0]->format('H:i') === '09:00' && $dates[30]->format('H:i') === '09:00' && $dates[0]->format('P') !== $dates[30]->format('P'), 'Month scheduling retains 9 AM local time across DST.');

    $paths[] = $path = wfag_test_archive(wfag_test_document());
    $parser = new WTAI_Docx_Parser();
    $html = $parser->parse($path);
    check(strpos($html, '<h1>Document Title</h1>') !== false, 'The parser preserves title text.');
    check(strpos($html, 'Before<br>After&emsp;Tab') !== false, 'Run text, breaks, and tabs retain their order.');
    check(strpos($html, '<strong>') !== false, 'Inline bold is preserved.');
    check(strpos($html, '<a href="https://example.com/guide">Website</a>') !== false, 'External hyperlink destinations survive.');
    check(strpos($html, 'javascript:') === false && strpos($html, 'Unsafe') !== false, 'Unsafe link schemes retain only their text.');
    check(strpos($html, '<ol>') !== false && strpos($html, '<ul>') !== false, 'Numbered and bullet lists remain distinct.');
    check(strpos($html, '<th>Header</th>') !== false, 'Word table header rows become HTML headers.');
    $dom = new DOMDocument();
    @$dom->loadHTML('<html><body>' . $html . '</body></html>');
    $xpath = new DOMXPath($dom);
    check($xpath->query('//ol/li/ul/li')->length === 1, 'Mixed nested lists have valid parent list items.');
    $processor = new WTAI_Content_Processor();
    require __DIR__ . '/phone-checks.php';
    $document = $processor->prepare_document($html, '123.docx');
    check($document['title'] === 'Document Title', 'Numeric filenames use the document title.');
    check(strpos($document['content'], '<h1>') === false, 'A matching title is omitted from the body.');
    $different = $processor->prepare_document($html, 'Different_Filename.docx');
    check($different['title'] === 'Different Filename' && strpos($different['content'], 'Document Title') === false && strpos($different['content'], 'Before') !== false, 'The initial DOCX H1 is removed even when the filename differs, preserving the body.');
    $headings = $processor->prepare_document('<H1 class="title"><strong>Original title</strong></H1><p>Body.</p><h1>Section heading</h1><h3>Detail</h3>', 'Different.docx');
    check(stripos($headings['content'], '<h1') === false && strpos($headings['content'], 'Original title') === false && strpos($headings['content'], '<h2>Section heading</h2>') !== false, 'Remove the first rich-text H1 and demote later H1s without losing section text.');
    check($processor->prepare_document('<h2>Section</h2><p>Body.</p>', 'Topic.docx')['content'] === '<h2>Section</h2><p>Body.</p>', 'Documents without H1 retain their existing headings and body.');
    check($processor->prepare_document('<p>Body</p>', '123.docx')['title'] === 'Imported Article', 'Numeric filenames without a heading use the fallback title.');
    foreach (array('1. Article Title.docx', '001. Article_Title.docx', '2) Article-Title.docx', '(3) Article Title.docx', '[4] Article Title.docx', '5.Article Title.docx') as $numbered_filename) {
        check($processor->prepare_document('<p>Body</p>', $numbered_filename)['title'] === 'Article Title', 'Leading list numbering is removed from filename-derived titles.');
    }
    foreach (array('2026 Marketing Trends.docx' => '2026 Marketing Trends', '2026-Marketing-Trends.docx' => '2026 Marketing Trends', '10 Tips for Gardening.docx' => '10 Tips for Gardening', '3.14 Explained.docx' => '3.14 Explained', 'Article 2.docx' => 'Article 2') as $meaningful_filename => $expected_title) {
        check($processor->prepare_document('<p>Body</p>', $meaningful_filename)['title'] === $expected_title, 'Meaningful title numbers and decimal prefixes are preserved.');
    }
    check($processor->prepare_document('<h1>Document title</h1><p>Body</p>', '1. .docx')['title'] === 'Document title', 'An empty title after removing numbering falls back to the document heading.');
    check(strpos($processor->process($html, array()), '<ol>') !== false, 'Disabling list conversion preserves numbering.');
    check(strpos($processor->process($html, array('convert_numbered_to_dotted' => true)), '<ol>') === false, 'Enabling list conversion creates bullets.');
    check($processor->process('<h2><strong>Heading</strong></h2><p><strong>Body</strong></p>', array('unbold_except_headings' => true)) === '<h2><strong>Heading</strong></h2><p>Body</p>', 'Bold cleanup preserves headings.');
    $paths[] = $empty = wfag_test_archive(wfag_test_document('<w:p><w:r><w:t>Plain second document</w:t></w:r></w:p>'));
    check($parser->parse($empty) === "<p>Plain second document</p>\n", 'Parser state is reset between documents.');
    $styled = wfag_test_document('<w:p><w:pPr><w:pStyle w:val="CustomSection"/></w:pPr><w:r><w:t>Styled heading</w:t></w:r></w:p><w:p><w:pPr><w:pStyle w:val="CustomNumber"/></w:pPr><w:r><w:t>Styled number</w:t></w:r></w:p>');
    $styled['word/styles.xml'] = '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:style w:type="paragraph" w:styleId="CustomSection"><w:name w:val="Heading 3"/></w:style><w:style w:type="paragraph" w:styleId="CustomNumber"><w:pPr><w:numPr><w:numId w:val="1"/></w:numPr></w:pPr></w:style></w:styles>';
    $paths[] = $styled_path = wfag_test_archive($styled);
    $styled_html = $parser->parse($styled_path);
    check(strpos($styled_html, '<h3>Styled heading</h3>') !== false, 'Heading names are read from Word namespace attributes.');
    check(strpos($styled_html, '<ol>') !== false, 'Numbering inherited directly from a paragraph style is read.');
    $paths[] = $bad = wfag_test_archive(array('word/document.xml' => '<broken>'));
    rejects(function () use ($parser, $bad) { $parser->parse($bad); }, 'Malformed XML is rejected.');
    $paths[] = $entity = wfag_test_archive(array('word/document.xml' => '<!DOCTYPE x [<!ENTITY e SYSTEM "file:///test">]><x>&e;</x>'));
    rejects(function () use ($parser, $entity) { $parser->parse($entity); }, 'Entity declarations are rejected.');
    $paths[] = $large = wfag_test_archive(array('word/document.xml' => str_repeat('x', WTAI_Docx_Parser::MAX_XML_BYTES + 1)));
    rejects(function () use ($parser, $large) { $parser->parse($large); }, 'Oversized XML is rejected before parsing.');
    $paths[] = $missing = wfag_test_archive(array('unrelated.xml' => '<x/>'));
    rejects(function () use ($parser, $missing) { $parser->parse($missing); }, 'Missing document XML is rejected.');

    require __DIR__ . '/image-checks.php';
    require __DIR__ . '/gbp-checks.php';
    require __DIR__ . '/internal-link-checks.php';
    $seo = new WTAI_SEO();
    $method = new ReflectionMethod($seo, 'build_meta_description');
    if (PHP_VERSION_ID < 80100) $method->setAccessible(true);
    $description = $method->invoke($seo, '<h2>Heading</h2><p>First paragraph text.</p><p>Later text.</p>');
    check($description === 'First paragraph text.', 'SEO descriptions use the first paragraph.');
    $server = new WTAI_Server_Info();
    $status = $server->get_system_status(array('is_sufficient' => true), array('zip' => array('loaded' => false, 'required' => false, 'fallback_available' => true)));
    check($status['overall'] === 'warning' && !$status['issues'], 'PclZip fallback is a warning rather than a false critical issue.');
    $status = $server->get_system_status(array('is_sufficient' => false), array('simplexml' => array('loaded' => false, 'required' => true, 'description' => 'Required XML reader')));
    check($status['overall'] === 'error', 'A required-extension error takes precedence over upload warnings.');
    $error_handler = new WTAI_Error_Handler();
    $error_info = $error_handler->get_error_info(WTAI_Error_Handler::ERR_IMAGE_MATCHING_FAILED, array('file' => 'demo.docx'));
    check($error_info['severity'] === 'warning' && $error_info['context']['file'] === 'demo.docx', 'Active error catalog entries retain severity and diagnostic context after cleanup.');
    check($error_handler->get_error_info('unrecognized-code')['message'] === 'Unknown error occurred', 'Unrecognized error codes retain the safe fallback.');
    check(WTAI_SEO::yoast_status() === array('installed' => false, 'active' => false, 'version' => ''), 'An absent Yoast plugin is reported as not installed.');
    $GLOBALS['wfag_test_plugins'] = array('wordpress-seo/wp-seo.php' => array('Version' => 'test-free', 'TextDomain' => 'wordpress-seo'));
    check(WTAI_SEO::yoast_status() === array('installed' => true, 'active' => false, 'version' => 'test-free'), 'An installed but inactive Yoast plugin reports its version.');
    $GLOBALS['wfag_test_plugins'] = array('custom-premium/plugin.php' => array('Version' => 'test-premium', 'TextDomain' => 'wordpress-seo-premium'));
    check(WTAI_SEO::yoast_status()['installed'], 'Yoast Premium is recognized by its plugin metadata.');
    check(array_keys(WTAI_SEO::plugin_statuses()) === array('Yoast SEO', 'Rank Math SEO'), 'Plugin status includes only supported SEO plugins.');
    check(WTAI_SEO::plugin_statuses()['Rank Math SEO'] === 'not_installed', 'Missing Rank Math is reported as not installed.');
    $GLOBALS['wfag_test_plugins']['seo-by-rank-math/rank-math.php'] = array('TextDomain' => 'seo-by-rank-math');
    check(WTAI_SEO::plugin_statuses()['Rank Math SEO'] === 'inactive', 'Installed inactive Rank Math is detected.');
    $seo->apply(901, 'Junk Removal', '<p>Junk removal helps clear your home.</p>');
    check(empty($GLOBALS['wfag_test_seo_meta'][901]), 'No SEO metadata or generated keyword is written without an active SEO plugin.');
    define('WPSEO_VERSION', 'test-active');
    check(WTAI_SEO::yoast_status() === array('installed' => true, 'active' => true, 'version' => 'test-active'), 'Runtime Yoast detection controls both status and import integration.');
    $GLOBALS['wfag_test_seo_meta'][902]['_yoast_wpseo_focuskw'] = 'Existing manual phrase';
    $seo->apply(902, 'Junk Removal', '<p>Junk removal helps clear your home.</p>');
    check($GLOBALS['wfag_test_seo_meta'][902]['_yoast_wpseo_title'] === 'Junk Removal' && $GLOBALS['wfag_test_seo_meta'][902]['_yoast_wpseo_focuskw'] === 'Existing manual phrase', 'Yoast still receives its title while its existing focus keyphrase is preserved.');
    $seo->apply(903, 'Junk Removal', '<p>Junk removal helps clear your home.</p>');
    check($GLOBALS['wfag_test_seo_meta'][903] === array('_yoast_wpseo_title' => 'Junk Removal', '_yoast_wpseo_metadesc' => 'Junk removal helps clear your home.', '_yoast_wpseo_focuskw' => 'junk removal'), 'Active Yoast receives SEO title, meta description, and generated focus keyphrase.');
    define('RANK_MATH_VERSION', 'test-active');
    $seo->apply(904, 'Junk Removal', '<h2>Junk Removal</h2><p>Junk removal helps clear your home.</p>');
    check($GLOBALS['wfag_test_seo_meta'][904] === array('rank_math_focus_keyword' => 'junk removal', '_yoast_wpseo_title' => 'Junk Removal', '_yoast_wpseo_metadesc' => 'Junk removal helps clear your home.', '_yoast_wpseo_focuskw' => 'junk removal'), 'Rank Math receives only its keyphrase while Yoast receives title, description, and keyphrase.');
    $GLOBALS['wfag_test_seo_meta'][905] = array('rank_math_focus_keyword' => 'Rank Math manual phrase', '_yoast_wpseo_focuskw' => 'Yoast manual phrase', 'rank_math_title' => 'Manual SEO title', 'rank_math_description' => 'Manual meta description');
    $seo->apply(905, 'Junk Removal', '<p>Junk removal helps clear your home.</p>');
    check($GLOBALS['wfag_test_seo_meta'][905]['rank_math_focus_keyword'] === 'Rank Math manual phrase' && $GLOBALS['wfag_test_seo_meta'][905]['_yoast_wpseo_focuskw'] === 'Yoast manual phrase', 'Neither integration overwrites an existing manual focus keyphrase.');
    check($GLOBALS['wfag_test_seo_meta'][905]['rank_math_title'] === 'Manual SEO title' && $GLOBALS['wfag_test_seo_meta'][905]['rank_math_description'] === 'Manual meta description', 'Existing Rank Math SEO title and description are untouched.');
    check(WTAI_SEO::plugin_statuses()['Rank Math SEO'] === 'active', 'Active Rank Math appears in plugin statuses.');
    echo 'PASS: ' . $checks . ' regression checks (' . (class_exists('ZipArchive') ? 'ZipArchive' : 'PclZip') . ").\n";
} finally {
    foreach ($paths as $path) if (is_file($path)) unlink($path);
}
