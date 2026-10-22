<?php
/** Run against a local WordPress installation. Creates and removes its own temporary posts. */
if (PHP_SAPI !== 'cli') exit;
require dirname(__DIR__, 4) . '/wp-load.php';
require_once dirname(__DIR__) . '/word-to-article-importer.php';
require __DIR__ . '/fixtures.php';

$checks = 0;
function verify($condition, $message) {
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
$admins = get_users(array('role' => 'administrator', 'number' => 1));
if (!$admins) throw new RuntimeException('This local integration check requires an administrator.');
wp_set_current_user($admins[0]->ID);
wtai_init()->activate();
$token = 'wfagtest' . bin2hex(random_bytes(6));
$ids = array();
$paths = array();
$capture = function ($id, $post) use (&$ids, $token) {
    if (strpos($post->post_title, $token) !== false) $ids[$id] = $id;
};
add_action('save_post', $capture, 10, 2);
$fail_history = null;
$fail_publish = null;
$google_http = null;
$original_options = array();
foreach (WTAI_Settings::defaults() as $key => $default) {
    $original_options[$key] = get_option('wtai_' . $key, null);
}
foreach (array('google_places_api_key', 'gbp_resolved_profile') as $key) $original_options[$key] = get_option('wtai_' . $key, null);

try {
    WTAI_Settings::save(array('pixabay_fallback' => false));
    $preferences = array('image_source' => 'media_library', 'gbp_profile_url' => '', 'publish_schedule' => 'custom_range', 'schedule_start_date' => '2099-01-01', 'schedule_end_date' => '2099-02-01', 'schedule_interval' => 'week', 'auto_insert_images' => true);
    verify(is_array(WTAI_Settings::save($preferences)), 'Preferences are saved to WordPress options.');
    verify(WTAI_Settings::read()['publish_schedule'] === 'custom_range', 'The selected publish mode is restored.');
    $preferences['publish_schedule'] = 'immediate';
    $preferences['auto_insert_images'] = false;
    WTAI_Settings::save($preferences);
    $remembered = WTAI_Settings::read();
    verify($remembered['schedule_start_date'] === '2099-01-01' && $remembered['schedule_end_date'] === '2099-02-01', 'Switching to immediate publication retains saved schedule dates.');
    verify($remembered['auto_insert_images'] === false && $remembered['schedule_interval'] === 'week', 'Unchecked and inactive options are saved.');
    $effective = WTAI_Settings::for_import($remembered);
    verify($effective['schedule_start_date'] === '' && $effective['schedule_end_date'] === '' && $effective['schedule_interval'] === 'day', 'Remembered inactive scheduling fields do not change an immediate import.');
    WTAI_Settings::save(array('auto_insert_images' => false, 'max_images_per_post' => 25));
    verify(WTAI_Settings::read()['schedule_start_date'] === '2099-01-01', 'Saving the image tab does not overwrite publishing preferences.');
    WTAI_Settings::save(array('publish_schedule' => 'specific_date'));
    verify(WTAI_Settings::read()['max_images_per_post'] === 25, 'Saving general options does not reset image preferences.');
    verify(WTAI_Settings::for_import(array('publish_schedule' => 'immediate'))['max_images_per_post'] === 25, 'Imports inherit saved preferences from the separate image tab.');
    $paths[] = $path = wfag_test_archive(wfag_test_document());
    $file = array('name' => '1. ' . $token . '.docx', 'tmp_name' => $path);
    $settings = WTAI_Settings::for_import(array('publish_schedule' => 'specific_date', 'schedule_start_date' => '2099-01-31', 'schedule_interval' => 'month', 'convert_numbered_to_dotted' => false, 'unbold_except_headings' => false));
    $date = WTAI_Scheduler::calculate($settings, 1)[0];
    $importer = new WTAI_Importer(wtai_init()->get_error_handler());
    WTAI_Settings::save(array('auto_link_phone_numbers' => false));
    verify(WTAI_Settings::read()['auto_link_phone_numbers'] === false, 'Disabling phone links persists in WordPress options.');
    WTAI_Settings::save(array('max_images_per_post' => 3));
    verify(WTAI_Settings::read()['auto_link_phone_numbers'] === false, 'Saving image preferences preserves the phone-link preference.');
    WTAI_Settings::save(array('auto_link_phone_numbers' => true));
    $paths[] = $phone_path = wfag_test_archive(wfag_test_document('<w:p><w:r><w:t>Call +1 (202) 555-0123 or 0063 917 123 4567 or (216) 399-7569. Existing: </w:t></w:r><w:hyperlink r:id="rId1"><w:r><w:t>+44 20 7946 0958</w:t></w:r></w:hyperlink></w:p>'));
    $phone_settings = WTAI_Settings::for_import(array('publish_schedule' => 'specific_date', 'schedule_start_date' => '2099-06-01', 'auto_link_phone_numbers' => true, 'auto_insert_images' => false));
    $phone_result = $importer->import(array('name' => $token . '-phone.docx', 'tmp_name' => $phone_path), $phone_settings, WTAI_Scheduler::calculate($phone_settings, 1)[0]);
    $phone_body = get_post_field('post_content', $phone_result['post_id']);
    verify(strpos($phone_body, 'href="tel:+12025550123"') !== false && strpos($phone_body, 'href="tel:00639171234567"') !== false, 'Phone links survive the real DOCX-to-WordPress import path with both prefixes preserved.');
    verify(strpos($phone_body, 'href="tel:2163997569"') !== false, 'Formatted local US/Canadian numbers survive real DOCX import without adding a country code.');
    verify(strpos($phone_body, 'href="https://example.com/guide">+44 20 7946 0958</a>') !== false && strpos($phone_body, 'tel:+442079460958') === false, 'Existing DOCX hyperlinks are preserved rather than relinked.');
    $phone_settings['auto_link_phone_numbers'] = false;
    $phone_off = $importer->import(array('name' => $token . '-phone-off.docx', 'tmp_name' => $phone_path), $phone_settings, WTAI_Scheduler::calculate($phone_settings, 1)[0]);
    verify(strpos(get_post_field('post_content', $phone_off['post_id']), 'href="tel:') === false && $phone_off['post_id'] !== $phone_result['post_id'], 'The disabled preference leaves numbers unlinked and participates in import identity.');
    $month_settings = WTAI_Settings::for_import(array('publish_schedule' => 'specific_month', 'schedule_month' => '2099-11', 'auto_insert_images' => false));
    WTAI_Settings::save(array('schedule_month' => '2099-11'));
    verify(WTAI_Settings::read()['schedule_month'] === '2099-11', 'Selected month persists in real WordPress options.');
    $month_dates = WTAI_Scheduler::calculate($month_settings, 3);
    $month_preview = WTAI_Scheduler::preview($month_settings, 3);
    verify(array_column($month_preview['dates'], 'date') === array_map(function ($date) { return $date->format('Y-m-d'); }, $month_dates), 'The displayed month preview uses the same dates as real imports.');
    $month_dates[] = WTAI_Scheduler::calculate($month_settings, 31)[30];
    foreach ($month_dates as $index => $month_date) {
        $paths[] = $month_path = wfag_test_archive(wfag_test_document('<w:p><w:r><w:t>Month document ' . $index . '</w:t></w:r></w:p>'));
        $month_result = $importer->import(array('name' => $token . '-month-' . $index . '.docx', 'tmp_name' => $month_path), $month_settings, $month_date);
        $expected_day = array('01', '16', '30', '01')[$index];
        verify($month_result['status'] === 'future' && get_post_field('post_date', $month_result['post_id']) === '2099-11-' . $expected_day . ' 09:00:00', 'Month document ' . ($index + 1) . ' stores its spread or repeated publish date.');
    }
    $range_settings = WTAI_Settings::for_import(array('publish_schedule' => 'custom_range', 'schedule_start_date' => '2099-11-01', 'schedule_end_date' => '2099-11-03', 'schedule_interval' => 'day', 'auto_insert_images' => false));
    $range_dates = WTAI_Scheduler::calculate($range_settings, 4);
    $range_ids = array();
    foreach ($range_dates as $index => $range_date) {
        $paths[] = $range_path = wfag_test_archive(wfag_test_document('<w:p><w:r><w:t>Range document ' . $index . '</w:t></w:r></w:p>'));
        $range_result = $importer->import(array('name' => $token . '-range-' . $index . '.docx', 'tmp_name' => $range_path), $range_settings, $range_date);
        $range_ids[] = $range_result['post_id'];
        verify($range_result['status'] === 'future' && get_post_field('post_date', $range_result['post_id']) === '2099-11-0' . (($index % 3) + 1) . ' 09:00:00', 'Range document ' . ($index + 1) . ' is scheduled at its cycling date.');
    }
    verify(count(array_unique($range_ids)) === 4, 'Distinct documents sharing a range date remain separate posts.');
    $lock_settings = $settings;
    unset($lock_settings['pixabay_fallback']);
    if (empty($lock_settings['max_internal_links_per_post'])) unset($lock_settings['max_internal_links_per_post']);
    if (($lock_settings['image_source'] ?? 'media_library') === 'media_library') unset($lock_settings['image_source'], $lock_settings['gbp_profile_url']);
    $key = hash('sha256', hash_file('sha256', $path) . wp_json_encode($lock_settings) . $date->format('c'));
    $lock = 'wfag_' . substr(hash('sha256', DB_NAME . '.' . $wpdb->posts . $key), 0, 59);
    $other_connection = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
    verify((string) $other_connection->get_var($other_connection->prepare('SELECT GET_LOCK(%s, 0)', $lock)) === '1', 'A second connection can hold the import lock.');
    try {
        $importer->import($file, $settings, $date);
        throw new RuntimeException('A concurrent import must not create another post.');
    } catch (Exception $error) {
        verify(strpos($error->getMessage(), 'already being imported') !== false, 'Concurrent imports are rejected before post creation.');
    } finally {
        $other_connection->get_var($other_connection->prepare('SELECT RELEASE_LOCK(%s)', $lock));
    }
    $first = $importer->import($file, $settings, $date);
    $id = $first['post_id'];
    $ids[$id] = $id;
    verify(get_the_title($id) === $token && get_post_field('post_name', $id) === $token, 'Filename numbering is removed from the saved title and WordPress slug.');
    verify(get_post_status($id) === 'future', 'The article is scheduled in WordPress.');
    verify((string) $other_connection->get_var($other_connection->prepare('SELECT GET_LOCK(%s, 0)', $lock)) === '1', 'Successful imports release their locks.');
    $other_connection->get_var($other_connection->prepare('SELECT RELEASE_LOCK(%s)', $lock));
    verify(get_post_field('post_date', $id) === '2099-01-31 09:00:00', 'WordPress stores the intended local publish time.');
    verify(get_post_field('post_date_gmt', $id) === get_gmt_from_date('2099-01-31 09:00:00'), 'The GMT publish time follows the site timezone.');
    verify(strpos(get_post_field('post_content', $id), '<ol>') !== false, 'Numbered lists survive WordPress sanitation.');
    verify(strpos(get_post_field('post_content', $id), '<a href="https://example.com/guide">') !== false, 'External links survive WordPress sanitation.');
    verify(preg_match('/<h1\b/i', get_post_field('post_content', $id)) === 0 && strpos(get_post_field('post_content', $id), 'Document Title') === false, 'The initial DOCX title is absent from the saved body even when the filename differs.');
    if (WTAI_SEO::is_rank_math_active()) {
        verify(get_post_meta($id, 'rank_math_focus_keyword', true) !== '', 'Active Rank Math receives a generated keyphrase on real imports.');
    }
    if (WTAI_SEO::is_yoast_active()) {
        verify(get_post_meta($id, '_yoast_wpseo_title', true) === get_the_title($id) && get_post_meta($id, '_yoast_wpseo_metadesc', true) !== '' && get_post_meta($id, '_yoast_wpseo_focuskw', true) !== '', 'Active Yoast receives SEO title, description, and generated focus keyphrase on real imports.');
        update_post_meta($id, '_yoast_wpseo_focuskw', 'Keep manual keyphrase');
        (new WTAI_SEO())->apply($id, get_the_title($id), get_post_field('post_content', $id));
        verify(get_post_meta($id, '_yoast_wpseo_focuskw', true) === 'Keep manual keyphrase', 'Existing manual Yoast keyphrases are preserved.');
    }
    verify(get_post_field('post_name', $id) === sanitize_title(get_the_title($id)), 'Imports receive the WordPress post slug used by the SEO editor.');
    $retry = $importer->import($file, $settings, $date);
    verify($retry['post_id'] === $id && $retry['existing'], 'An identical retry reuses the existing post.');
    verify((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}wtai_import_history WHERE post_id = %d", $id)) === 1, 'Retries do not duplicate history.');
    wp_trash_post($id);
    try {
        $importer->import($file, $settings, $date);
        throw new RuntimeException('A trashed article must not silently create a duplicate.');
    } catch (Exception $error) {
        verify(strpos($error->getMessage(), 'Trash') !== false, 'Trashed duplicates require an explicit restore/delete.');
    }
    wp_untrash_post($id);

    // Fail after the draft insert but before publication; the retry must reuse it.
    $file['name'] = $token . '-recover.docx';
    $recovery_settings = $settings;
    $recovery_settings['unbold_except_headings'] = true;
    $fail_history = function ($sql) use ($token) {
        global $wpdb;
        if (strpos($sql, 'INSERT INTO `' . $wpdb->prefix . 'wtai_import_history`') === 0 && strpos($sql, $token . '-recover') !== false) {
            throw new RuntimeException('Injected history failure');
        }
        return $sql;
    };
    add_filter('query', $fail_history);
    try {
        $importer->import($file, $recovery_settings, $date);
        throw new RuntimeException('The failure injection did not run.');
    } catch (Exception $error) {
        verify(strpos($error->getMessage(), 'Draft #') !== false, 'A failed history write retains and identifies its draft.');
    } finally {
        remove_filter('query', $fail_history);
    }
    $draft = (int) $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_title = %s AND post_status = 'draft' ORDER BY ID DESC LIMIT 1", $token . ' recover'));
    verify($draft > 0, 'Failed imports remain unpublished.');
    $recovered = $importer->import($file, $recovery_settings, $date);
    verify($recovered['post_id'] === $draft && $recovered['status'] === 'future', 'A retry resumes the same draft and schedules it.');

    $late_settings = $settings;
    $late_settings['convert_numbered_to_dotted'] = true;
    $file['name'] = $token . '-late-hook.docx';
    $fail_publish = function ($new_status, $old_status, $post) use ($token) {
        if ($new_status === 'future' && strpos($post->post_title, $token) !== false) throw new RuntimeException('Injected post-publication hook failure');
    };
    add_action('transition_post_status', $fail_publish, 999, 3);
    $late = $importer->import($file, $late_settings, $date);
    remove_action('transition_post_status', $fail_publish, 999);
    verify($late['status'] === 'future' && !empty($late['warning']), 'A hook failure after publication reports the article with a warning.');
    verify($importer->import($file, $late_settings, $date)['post_id'] === $late['post_id'], 'A late hook failure cannot produce a duplicate.');

    $image_ids = array();
    foreach (array('forest', 'ocean') as $word) {
        $image_id = wp_insert_attachment(array('post_title' => $token . ' ' . $word, 'post_status' => 'inherit', 'post_mime_type' => 'image/jpeg'));
        $ids[$image_id] = $image_id;
        $image_ids[] = $image_id;
        update_post_meta($image_id, '_wp_attachment_image_alt', $token . ' ' . $word);
        update_post_meta($image_id, '_wp_attached_file', 'wfag-tests/' . $token . '-' . $word . '.jpg');
        update_post_meta($image_id, '_wp_attachment_metadata', array('width' => 1200, 'height' => 800, 'file' => 'wfag-tests/' . $token . '-' . $word . '.jpg', 'sizes' => array()));
    }
    $matcher = new WTAI_Image_Matcher();
    $images = $matcher->insert_images('<p>' . str_repeat($token . ' forest. ', 50) . '</p><p>' . str_repeat($token . ' ocean. ', 50) . '</p>', array('max_images_per_post' => 2), $token);
    verify(substr_count($images, '<figure') === 2, 'Contextual titles and alt text find real media rows.');
    verify(preg_match('~</p>\s*<figure.*?</figure>\s*<p>.*?</p>\s*<figure~s', $images) === 1, 'Native images are distributed across safe paragraph boundaries.');
    verify(strpos($images, 'width="') !== false && strpos($images, 'height="') !== false, 'Native WordPress image dimensions are preserved.');
    $thumbnails = $matcher->result_thumbnails($images . '<figure><img src="https://example.test/unrelated.png"></figure>');
    verify(substr_count($thumbnails, 'class="wtai-result-thumbnail"') === 2 && substr_count($thumbnails, '<li>') === 2, 'Result thumbnails show only images inserted by the plugin, in article order.');
    verify(strpos($thumbnails, esc_url(wp_get_attachment_image_url($image_ids[0], 'full'))) !== false && substr_count($thumbnails, 'target="_blank"') === 2, 'Native thumbnails open the full attachment image in a new tab.');
    verify($matcher->result_thumbnails('<p>No inserted images.</p>') === '', 'An article without inserted images has no empty thumbnail row.');
    verify(strpos($matcher->result_thumbnails('<figure class="wp-block-image wtai-inserted-image"><img class="wp-image-999999999"></figure>'), 'Image unavailable') !== false, 'Missing media is identified without a broken image or import error.');
    $conclusion_tail = '<h2>Conclusion</h2><p>' . $token . ' forest ocean.</p><h2>Next steps</h2><p>' . $token . ' ocean.</p>';
    $bounded_images = $matcher->insert_images('<p>' . str_repeat($token . ' forest. ', 50) . '</p><p>' . str_repeat($token . ' ocean. ', 50) . '</p>' . $conclusion_tail, array('max_images_per_post' => 2), $token);
    verify($matcher->get_report()['inserted'] === 2, 'Conclusion cutoff retains both requested native Media Library images.');
    verify(substr($bounded_images, strpos($bounded_images, '<h2>Conclusion</h2>')) === $conclusion_tail, 'Conclusion and later sections remain unchanged with real WordPress images.');
    $cached = WTAI_Media_Index::get();
    verify(isset($cached['images'][$image_ids[0]]), 'The metadata index caches new image attachments.');
    $graphic_id = wp_insert_attachment(array('post_title' => $token . ' forest logo', 'post_status' => 'inherit', 'post_mime_type' => 'image/jpeg'));
    update_post_meta($graphic_id, '_wp_attachment_image_alt', $token . ' forest');
    verify(!isset(WTAI_Media_Index::get()['images'][$graphic_id]), 'A logo-labeled real attachment is excluded from automatic matching.');
    wp_update_post(array('ID' => $graphic_id, 'post_title' => $token . ' forest photograph'));
    verify(isset(WTAI_Media_Index::get()['images'][$graphic_id]), 'Editing attachment labels invalidates the cache and restores eligible photos.');
    wp_delete_attachment($graphic_id, true);
    unset($ids[$graphic_id]);
    update_post_meta($image_ids[0], '_wp_attachment_image_alt', $token . 'altonly');
    verify(get_transient(WTAI_Media_Index::CACHE_KEY) === false, 'Editing alt text automatically invalidates the keyword index.');
    $forest_body = '<p>' . $token . ' forest.</p>';
    $matcher->insert_images($forest_body, array('max_images_per_post' => 1), $token);
    verify(!in_array($image_ids[0], array_column($matcher->get_report()['placements'], 'id'), true), 'A matching title cannot override unrelated alt text in real WordPress media.');
    $alt_matches = $matcher->get_media_images($token . 'altonly');
    verify(in_array($image_ids[0], array_column($alt_matches['images'], 'id'), true), 'Media search finds words present only in alt text.');
    wp_update_post(array('ID' => $image_ids[1], 'post_excerpt' => $token . 'captiononly', 'post_content' => $token . 'descriptiononly'));
    verify(in_array($image_ids[1], array_column($matcher->get_media_images($token . 'captiononly')['images'], 'id'), true), 'Media search finds captions.');
    verify(in_array($image_ids[1], array_column($matcher->get_media_images($token . 'descriptiononly')['images'], 'id'), true), 'Media search finds descriptions.');

    $body = '<p>' . $token . ' exact duplicate content.</p>';
    $older_page = wp_insert_post(array('post_title' => $token . ' older page', 'post_type' => 'page', 'post_content' => $body, 'post_status' => 'draft'));
    $original = wp_insert_post(array('post_title' => $token . ' original', 'post_content' => $body, 'post_status' => 'draft'));
    $copies = array();
    for ($index = 0; $index < 22; $index++) {
        $copies[] = wp_insert_post(array('post_title' => $token . ' copy ' . $index, 'post_type' => 'post', 'post_status' => 'draft', 'post_content' => $body));
    }
    $not_duplicates = array(
        $older_page,
        wp_insert_post(array('post_title' => $token . ' page copy', 'post_type' => 'page', 'post_status' => 'draft', 'post_content' => $body)),
        wp_insert_post(array('post_title' => $token . ' empty one', 'post_type' => 'page', 'post_status' => 'draft', 'post_content' => '')),
        wp_insert_post(array('post_title' => $token . ' empty two', 'post_type' => 'page', 'post_status' => 'draft', 'post_content' => '')),
        wp_insert_post(array('post_title' => $token . ' slug 2', 'post_name' => $token . '-2', 'post_status' => 'draft', 'post_content' => '<p>' . $token . ' different content.</p>')),
    );
    $page_only_body = '<p>' . $token . ' page and single post pair.</p>';
    $not_duplicates[] = wp_insert_post(array('post_title' => $token . ' single post', 'post_type' => 'post', 'post_status' => 'draft', 'post_content' => $page_only_body));
    $not_duplicates[] = wp_insert_post(array('post_title' => $token . ' paired page', 'post_type' => 'page', 'post_status' => 'draft', 'post_content' => $page_only_body));
    $first_page = WTAI_Duplicate_Pages::listing(1);
    $second_page = WTAI_Duplicate_Pages::listing(2);
    verify(!is_wp_error($first_page) && count($first_page['items']) === 20, 'Duplicate results are paginated at 20 copies.');
    $listed = array_merge($first_page['items'], $second_page['items']);
    $listed_ids = array_map(function ($row) { return (int) $row->ID; }, $listed);
    verify(!in_array($original, $listed_ids, true), 'The original is not listed as a duplicate.');
    verify(count(array_intersect($not_duplicates, $listed_ids)) === 0, 'Pages, page-only matches, empty content, and unique posts are excluded.');
    verify(count(array_intersect($copies, $listed_ids)) === 22, 'Duplicate posts are detected despite different titles.');
    verify(is_wp_error(WTAI_Duplicate_Pages::trash($not_duplicates[1])) && get_post_status($not_duplicates[1]) === 'draft', 'A matching page cannot be trashed through duplicate management.');
    $original_query = $_GET;
    $_GET['tab'] = 'duplicates';
    ob_start();
    wtai_init()->render_admin_page();
    $duplicate_html = ob_get_clean();
    $_GET = $original_query;
    verify(strpos($duplicate_html, 'duplicate_page=2') !== false && strpos($duplicate_html, '%25%23%25') === false, 'Duplicate pagination renders usable page links.');
    $tab_dom = new DOMDocument();
    @$tab_dom->loadHTML($duplicate_html);
    $tab_xpath = new DOMXPath($tab_dom);
    verify($tab_xpath->query('//select[@id="wtai-month-number"]/option')->length === 13 && $tab_xpath->query('//input[@type="month"]')->length === 0, 'Month selection renders named native options instead of browser-dependent month inputs.');
    verify($tab_xpath->query('//input[@id="wtai-schedule-month" and @type="hidden"]')->length === 1 && $tab_xpath->query('//input[@id="wtai-month-year" and @type="number"]')->length === 1, 'The separate year field preserves the existing canonical month preference.');
    verify($tab_xpath->query('//*[@id="wtai-schedule-preview" and @role="status"]')->length === 1, 'The schedule preview has an accessible live status region.');

    verify($tab_xpath->query('//*[@data-wtai-panel="duplicates" and not(@hidden)]//form[contains(@class,"wtai-duplicate-trash-form")]')->length > 0 && $tab_xpath->query('//*[@data-wtai-panel="generate" and @hidden]//*[@id="wtai-import-form"]')->length === 1, 'Direct duplicate links show management actions while keeping the import form in its hidden panel.');
    $matching = array_filter($listed, function ($row) use ($copies) { return in_array((int) $row->ID, $copies, true); });
    verify(count(array_filter($matching, function ($row) use ($original) { return (int) $row->original_id !== $original; })) === 0, 'Every copy links to the oldest original.');
    verify(is_wp_error(WTAI_Duplicate_Pages::trash($original)), 'The original cannot be trashed through duplicate management.');
    $user_id = get_current_user_id();
    wp_set_current_user(0);
    verify(is_wp_error(WTAI_Duplicate_Pages::trash($copies[0])), 'An unauthorized user cannot trash duplicates.');
    wp_set_current_user($user_id);
    verify(get_post_status($copies[0]) === 'draft', 'Denied Trash requests do not mutate posts.');
    verify(WTAI_Duplicate_Pages::trash($copies[0]) === true && get_post_status($copies[0]) === 'trash', 'Trash moves a duplicate into core WordPress Trash.');
    verify(get_post_status($original) === 'draft', 'Trashing a copy leaves the original intact.');
    $after_trash = WTAI_Duplicate_Pages::listing(1);
    verify(!in_array($copies[0], array_map(function ($row) { return (int) $row->ID; }, $after_trash['items']), true), 'Trashed copies disappear from duplicate results.');
    wp_update_post(array('ID' => $copies[1], 'post_content' => '<p>' . $token . ' edited unique content.</p>'));
    verify(is_wp_error(WTAI_Duplicate_Pages::trash($copies[1])) && get_post_status($copies[1]) === 'draft', 'An edited item that is no longer a duplicate cannot be trashed through a stale listing.');

    // Import linking uses standard anchors without a third-party linking plugin.
    $link_targets = array();
    foreach (array('drain cleaning', 'emergency plumbing', 'page only', 'password only', 'future only') as $topic) {
        $target_data = array('post_title' => $token . ' ' . $topic, 'post_type' => $topic === 'page only' ? 'page' : 'post', 'post_status' => $topic === 'future only' ? 'future' : 'publish');
        if ($topic === 'drain cleaning') $target_data['post_title'] = 'Emergency ' . $token . ' drain cleaning in Cleveland';
        if ($topic === 'password only') $target_data['post_password'] = 'temporary-test-password';
        if ($topic === 'future only') $target_data['post_date'] = '2099-01-01 09:00:00';
        $link_targets[$topic] = wp_insert_post($target_data);
    }
    WTAI_Settings::save(array('max_internal_links_per_post' => 2));
    verify(WTAI_Settings::read()['max_internal_links_per_post'] === 2, 'The entered internal-link maximum persists in WordPress options.');
    WTAI_Settings::save(array('max_images_per_post' => 4));
    verify(WTAI_Settings::read()['max_internal_links_per_post'] === 2, 'Saving image preferences retains the internal-link maximum.');
    $link_xml = '';
    foreach (array_keys($link_targets) as $topic) $link_xml .= '<w:p><w:r><w:t>Read about ' . $token . ' ' . $topic . ' today.</w:t></w:r></w:p>';
    $paths[] = $link_path = wfag_test_archive(wfag_test_document($link_xml));
    $link_settings = WTAI_Settings::for_import(array('publish_schedule' => 'specific_date', 'schedule_start_date' => '2099-04-01', 'max_internal_links_per_post' => 2, 'auto_insert_images' => false));
    $link_file = array('name' => $token . '-internal-links.docx', 'tmp_name' => $link_path);
    $link_date = WTAI_Scheduler::calculate($link_settings, 1)[0];
    $link_result = $importer->import($link_file, $link_settings, $link_date);
    $link_body = get_post_field('post_content', $link_result['post_id']);
    verify($link_result['links']['inserted'] === 2 && substr_count($link_body, '<a ') === 2, 'Real DOCX imports save the requested two relevant internal links before scheduling.');
    verify(strpos($link_body, esc_url(get_permalink($link_targets['drain cleaning']))) !== false && strpos($link_body, esc_url(get_permalink($link_targets['emergency plumbing']))) !== false, 'New anchors point to the matching existing published blog posts.');
    verify(strpos($link_body, '>' . $token . ' drain cleaning</a>') !== false, 'Real imports link a title fragment without requiring the complete destination title.');
    verify(strpos($link_body, esc_url(get_permalink($link_targets['page only']))) === false && strpos($link_body, esc_url(get_permalink($link_targets['password only']))) === false && strpos($link_body, esc_url(get_permalink($link_targets['future only']))) === false, 'Imported content never links to pages, protected posts, or future posts.');
    $link_retry = $importer->import($link_file, $link_settings, $link_date);
    verify($link_retry['existing'] && $link_retry['post_id'] === $link_result['post_id'] && get_post_field('post_content', $link_result['post_id']) === $link_body, 'Identical retries reuse the linked article without adding duplicate links.');
    $link_settings['max_internal_links_per_post'] = 0;
    $unlinked_result = $importer->import($link_file, $link_settings, $link_date);
    verify($unlinked_result['post_id'] !== $link_result['post_id'] && strpos(get_post_field('post_content', $unlinked_result['post_id']), '<a ') === false, 'A zero maximum disables insertion and has a distinct identity from enabled linking.');
    $legacy_settings = $link_settings;
    unset($legacy_settings['pixabay_fallback']);
    unset($legacy_settings['max_internal_links_per_post']);
    if (($legacy_settings['image_source'] ?? 'media_library') === 'media_library') unset($legacy_settings['image_source'], $legacy_settings['gbp_profile_url']);
    $legacy_marker = 'wfag:' . hash('sha256', hash_file('sha256', $link_path) . wp_json_encode($legacy_settings) . $link_date->format('c'));
    verify(get_post_field('post_content_filtered', $unlinked_result['post_id']) === $legacy_marker, 'Disabled linking preserves import fingerprints from versions without the new option.');

    // Google responses are mocked: no real key, remote photo storage, or billable request.
    $google_place = 'ChIJ' . $token;
    $google_key = 'wfag-test-not-a-real-google-key-1234';
    $google_requests = 0;
    $google_http = function ($preempt, $args, $url) use ($google_place, &$google_requests) {
        if (strpos($url, 'https://places.googleapis.com/v1/') !== 0) return $preempt;
        $google_requests++;
        if (strpos($url, '/media?') !== false) $payload = array('photoUri' => 'https://lh3.googleusercontent.com/places/wfag-test-photo');
        else {
            $photos = array();
            for ($slot = 0; $slot < 3; $slot++) $photos[] = array('name' => 'places/' . $google_place . '/photos/test' . $slot, 'googleMapsUri' => 'https://www.google.com/maps/photo/test', 'authorAttributions' => array(array('displayName' => 'Test photographer', 'uri' => 'https://www.google.com/maps/contrib/test')));
            $payload = array('id' => $google_place, 'displayName' => array('text' => 'Test Business'), 'photos' => $photos);
        }
        return array('response' => array('code' => 200, 'message' => 'OK'), 'body' => wp_json_encode($payload), 'headers' => array(), 'cookies' => array());
    };
    add_filter('pre_http_request', $google_http, 10, 3);
    update_option('wtai_google_places_api_key', $google_key, false);
    $google_url = 'https://www.google.com/maps/?query_place_id=' . $google_place;
    WTAI_Settings::save(array('image_source' => 'gbp', 'gbp_profile_url' => $google_url));
    verify(WTAI_Settings::read()['image_source'] === 'gbp' && WTAI_Settings::read()['gbp_profile_url'] === $google_url, 'The GBP source and pasted profile link persist in WordPress options.');
    $google_xml = str_repeat('<w:p><w:r><w:t>' . str_repeat('Useful article paragraph with business information. ', 10) . '</w:t></w:r></w:p>', 9) . '<w:p><w:pPr><w:pStyle w:val="Heading2"/></w:pPr><w:r><w:t>Conclusion</w:t></w:r></w:p><w:p><w:r><w:t>Summary paragraph.</w:t></w:r></w:p>';
    $paths[] = $google_path = wfag_test_archive(wfag_test_document($google_xml));
    $google_settings = WTAI_Settings::for_import(array('publish_schedule' => 'specific_date', 'schedule_start_date' => '2099-07-01', 'auto_insert_images' => true, 'max_images_per_post' => 3, 'max_internal_links_per_post' => 0));
    $google_result = $importer->import(array('name' => $token . '-google-photos.docx', 'tmp_name' => $google_path), $google_settings, WTAI_Scheduler::calculate($google_settings, 1)[0]);
    $google_post = $google_result['post_id'];
    $google_body = get_post_field('post_content', $google_post);
    verify($google_result['images']['inserted'] === 3 && substr_count($google_body, '[wtai_gbp_photo ') === 3, 'Real DOCX-to-WordPress imports preserve three live GBP shortcode slots.');
    verify(substr_count($google_result['image_previews'], 'class="wtai-gbp-image"') === 3 && strpos($google_result['image_previews'], 'Test photographer') !== false && strpos($google_result['image_previews'], 'google-maps-logo.svg') !== false, 'GBP result thumbnails include live images and Google/photographer attribution.');
    verify(preg_match('~<a[^>]+href="https://lh3\.googleusercontent\.com/[^\"]+"[^>]+target="_blank"~', $google_result['image_previews']) === 1 && strpos($google_result['image_previews'], $google_key) === false, 'GBP thumbnail links open the full live photo without exposing API credentials.');
    $google_retry = $importer->import(array('name' => $token . '-google-photos.docx', 'tmp_name' => $google_path), $google_settings, WTAI_Scheduler::calculate($google_settings, 1)[0]);
    verify($google_retry['existing'] && substr_count($google_retry['image_previews'], 'class="wtai-gbp-image"') === 3, 'Reusing a completed import still returns its image thumbnails.');
    verify(strrpos($google_body, '[wtai_gbp_photo ') < strpos($google_body, '<h2>Conclusion</h2>'), 'Saved GBP placeholders stay before the Conclusion heading.');
    verify(strpos($google_body, $google_key) === false && strpos($google_body, 'googleusercontent') === false && strpos($google_body, '/photos/test') === false, 'Saved posts contain no Google credentials, expiring image URLs, or photo references.');
    $google_history = $wpdb->get_var($wpdb->prepare("SELECT settings FROM {$wpdb->prefix}wtai_import_history WHERE post_id = %d", $google_post));
    verify(strpos($google_history, $google_key) === false, 'Google credentials never enter import history settings.');
    $google_rendered = apply_filters('the_content', $google_body);
    verify(substr_count($google_rendered, 'class="wtai-gbp-image"') === 3 && strpos($google_rendered, '[wtai_gbp_photo ') === false, 'Normal WordPress content rendering expands the three live Google photo slots.');
    verify(strpos($google_rendered, 'Test photographer') !== false && strpos($google_rendered, 'google-maps-logo.svg') !== false && strpos($google_rendered, $google_key) === false, 'Rendered posts include attribution and the official logo without exposing the Google API key.');
    verify($google_requests === 4, 'One render request retrieves one fresh profile response and three media URLs, with request-local reuse.');
    verify(get_option('wtai_gbp_resolved_profile') === array('url' => $google_url, 'place_id' => $google_place), 'Google integration stores only the profile link and permitted place ID.');
    WTAI_Settings::save(array('image_source' => 'media_library', 'gbp_profile_url' => ''));
    remove_filter('pre_http_request', $google_http, 10);
    $google_http = null;

    // History stays limited to the ten displayed rows; links and Trash use core post permissions.
    $history_post = wp_insert_post(array('post_title' => $token . ' history & title', 'post_type' => 'post', 'post_status' => 'draft'));
    $wpdb->insert($wpdb->prefix . 'wtai_import_history', array('post_id' => $history_post, 'original_filename' => $token . '-history.docx', 'import_date' => '2099-12-31 23:59:59', 'settings' => '{}', 'status' => 'completed'));
    $rows = wtai_init()->get_import_history();
    verify(count($rows) <= 10 && (int) $rows[0]['post_id'] === $history_post, 'History exposes only the ten displayed imports in stable newest-first order.');
    verify($rows[0]['post_title'] === get_the_title($history_post) && $rows[0]['can_trash'], 'History includes the current post title and authorized Trash action.');
    verify(strpos($rows[0]['view_url'], 'preview=true') !== false && strpos($rows[0]['view_url'], 'action=edit') === false, 'View Post opens a draft preview rather than its editor.');
    verify(strpos($rows[0]['edit_url'], 'action=edit') !== false, 'Edit Post has its own editor URL.');
    wp_update_post(array('ID' => $history_post, 'post_status' => 'publish'));
    verify(wtai_init()->get_import_history()[0]['view_url'] === get_permalink($history_post), 'Published history rows use the public permalink.');
    $admin_id = get_current_user_id();
    wp_set_current_user(0);
    verify(wtai_init()->get_import_history() === array() && is_wp_error(wtai_init()->trash_import($history_post)), 'Unauthorized users cannot read history or trash imported posts.');
    wp_set_current_user($admin_id);
    verify(is_wp_error(wtai_init()->trash_import($not_duplicates[4])) && get_post_status($not_duplicates[4]) === 'draft', 'Posts outside import history cannot be trashed through this action.');
    verify(wtai_init()->trash_import($history_post) === true && get_post_status($history_post) === 'trash', 'The history action moves the imported post into reversible WordPress Trash.');
    $trashed_row = wtai_init()->get_import_history()[0];
    verify($trashed_row['post_status'] === 'trash' && !$trashed_row['view_url'] && !$trashed_row['edit_url'] && !$trashed_row['can_trash'], 'Trashed posts retain their history but expose no stale view, edit, or Trash actions.');
    verify(is_wp_error(wtai_init()->trash_import($history_post)), 'Repeated Trash requests do not delete posts permanently.');
    wp_untrash_post($history_post);
    verify(wtai_init()->get_import_history()[0]['can_trash'], 'Restored imports regain their history actions.');

    echo 'PASS: ' . $checks . " WordPress integration checks.\n";
} finally {
    if ($google_http) remove_filter('pre_http_request', $google_http, 10);
    if ($fail_history) remove_filter('query', $fail_history);
    if ($fail_publish) remove_action('transition_post_status', $fail_publish, 999);
    remove_action('save_post', $capture, 10);
    foreach ($ids as $id) {
        wp_delete_post($id, true);
        $wpdb->delete($wpdb->prefix . 'wtai_import_history', array('post_id' => $id), array('%d'));
    }
    foreach ($paths as $path) if (is_file($path)) unlink($path);
    foreach ($original_options as $key => $value) {
        if ($value === null) delete_option('wtai_' . $key);
        else update_option('wtai_' . $key, $value);
    }
}
