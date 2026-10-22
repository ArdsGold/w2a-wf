<?php
/** Included by run.php, using small in-memory Media Library fixtures. */
if (PHP_SAPI !== 'cli') exit;

$GLOBALS['wfag_test_media'] = array(
    1 => array('title' => 'Orchard', 'alt' => 'Orchard trees', 'caption' => '', 'description' => ''),
    2 => array('title' => 'Orchard harvest', 'alt' => 'Orchard', 'caption' => '', 'description' => ''),
    3 => array('title' => 'Orchard', 'alt' => 'Orchard', 'caption' => 'Orchard captionkeyword', 'description' => ''),
    4 => array('title' => 'Orchard', 'alt' => 'Orchard', 'caption' => '', 'description' => 'Orchard descriptionkeyword'),
);
WTAI_Media_Index::invalidate();
$matcher = new WTAI_Image_Matcher();
$body = str_repeat('<p>' . str_repeat('Orchard trees orchard. ', 20) . '</p>', 9);
$output = $matcher->insert_images($body, array('max_images_per_post' => 3), 'Orchard');
$report = $matcher->get_report();
check($report['inserted'] === 3, 'Three relevant images are inserted by default.');
check(array_column($report['placements'], 'target_percent') === array(33.3, 66.7, 100.0), 'Three images target thirds of the article.');
check(array_column($report['placements'], 'actual_percent') === array(33.3, 66.7, 100.0), 'Equal paragraphs receive images at the expected word-count boundaries.');
check(count(array_unique(array_column($report['placements'], 'id'))) === 3, 'Images do not repeat in the article.');
$tight_body = '<p>' . str_repeat('Orchard ', 100) . '</p><p>' . str_repeat('Orchard ', 99) . '</p>';
$matcher->insert_images($tight_body, array('max_images_per_post' => 2), 'Orchard');
check($matcher->get_report()['inserted'] === 1, 'A 99-word gap cannot hold two new images.');
check(strpos($matcher->get_report()['message'], '100 words') !== false, 'Reduced image counts explain the minimum spacing requirement.');
$spaced_body = str_repeat('<p>' . str_repeat('Orchard ', 100) . '</p>', 3);
$matcher->insert_images($spaced_body, array('max_images_per_post' => 3), 'Orchard');
check($matcher->get_report()['inserted'] === 3, 'Exactly 100 words between image positions preserves the full count.');
check(array_column($matcher->get_report()['placements'], 'actual_percent') === array(33.3, 66.7, 100.0), 'Spacing reserves enough room for all three images.');
$clustered_body = '<h2>Orchard</h2>' . str_repeat('<p>' . str_repeat('Orchard ', 20) . '</p>', 4) . '<h2>Ocean</h2><p>' . str_repeat('Ocean waves. ', 300) . '</p><h2>Conclusion</h2><p>Orchard.</p>';
$clustered_output = $matcher->insert_images($clustered_body, array('max_images_per_post' => 3), 'Orchard');
check($matcher->get_report()['inserted'] === 1, 'Images matching only a short section cannot cluster to reach the requested count.');
check(strpos($clustered_output, 'wtai-inserted-image') < strpos($clustered_output, '<h2>Ocean</h2>'), 'Spacing never moves a local image to an unrelated section or beyond Conclusion.');
check(strpos($output, '\n') === false, 'No literal newline escape is emitted.');
$again = $matcher->insert_images($body, array('max_images_per_post' => 3), 'Orchard');
check($output === $again, 'The same article and library produce deterministic output.');
foreach (array('<h2>Conclusion</h2>', '<h3><strong>CONCLUSION:</strong></h3>') as $conclusion) {
    $ending = $conclusion . str_repeat('<p>Orchard trees orchard.</p>', 30) . '<h2>Further reading</h2><p>Orchard.</p>';
    $bounded = $matcher->insert_images($body . $ending, array('max_images_per_post' => 3), 'Orchard');
    check($matcher->get_report()['inserted'] === 3, 'Conclusion cutoff preserves the requested image count.');
    check(array_column($matcher->get_report()['placements'], 'actual_percent') === array(33.3, 66.7, 100.0), 'Spacing uses only the body before Conclusion.');
    check(substr($bounded, strpos($bounded, $conclusion)) === $ending, 'Conclusion and all following sections stay free of new images.');
}
$only_conclusion = '<h2>Conclusion</h2>' . $body;
check($matcher->insert_images($only_conclusion, array('max_images_per_post' => 3), 'Orchard') === $only_conclusion, 'Conclusion-only articles do not receive images.');
$ordinary = $matcher->insert_images('<p>Conclusion is mentioned in this paragraph.</p>' . $body, array('max_images_per_post' => 3), 'Orchard');
check($matcher->get_report()['inserted'] === 3, 'Mentioning Conclusion in body text does not stop image placement.');
$matcher->insert_images($body, array('max_images_per_post' => 4), 'Orchard');
check($matcher->get_report()['inserted'] === 4, 'Title, alt text, caption, and description matches are all eligible.');
foreach (array(1, 2, 3, 4) as $id) {
    check(in_array($id, array_column($matcher->get_report()['placements'], 'id'), true), 'Every metadata source contributes its image.');
}
check($matcher->insert_images($body, array('max_images_per_post' => 0)) === $body, 'Zero images preserves the input.');
$matcher->insert_images('<p>Unmatched zebra habitat.</p>', array('max_images_per_post' => 3));
check($matcher->get_report()['inserted'] === 0, 'Unrelated images are not substituted for missing matches.');
$matcher->insert_images('<p>Orchard.</p>', array('max_images_per_post' => 50), 'Orchard');
check($matcher->get_report()['inserted'] === 1, 'Requested counts are bounded by safe insertion points.');
$dom = new DOMDocument();
@$dom->loadHTML($output);
$xp = new DOMXPath($dom);
check($xp->query('//p//figure | //li//figure | //table//figure')->length === 0, 'Figures never split paragraphs, list items, or tables.');
$list_output = $matcher->insert_images('<h2>Orchard</h2><ul><li>Orchard trees</li></ul><table><tr><td>Orchard harvest</td></tr></table>', array('max_images_per_post' => 2), 'Orchard');
$list_dom = new DOMDocument();
@$list_dom->loadHTML($list_output);
check((new DOMXPath($list_dom))->query('//li//figure | //table//figure | //h2//figure')->length === 0, 'Lists, tables, and headings stay intact.');
$matcher->insert_images('<h2>Orchard</h2>', array('max_images_per_post' => 3));
check($matcher->get_report()['inserted'] === 0, 'Heading-only documents do not receive stacked images.');
$existing = '<figure><img class="wp-image-1" src="https://example.com/1.jpg"></figure>' . $body;
$matcher->insert_images($existing, array('max_images_per_post' => 3), 'Orchard');
check(!in_array(1, array_column($matcher->get_report()['placements'], 'id'), true), 'Existing attachment IDs are not reinserted.');
$search = $matcher->get_media_images('orchard', 1);
check($search['total'] === 4, 'Library search includes all four metadata fields.');
check($matcher->get_media_images('captionkeyword')['total'] === 1 && $matcher->get_media_images('descriptionkeyword')['total'] === 1, 'Captions and descriptions remain searchable supporting metadata.');

$GLOBALS['wfag_test_media'] = array(
    1 => array('title' => 'Orchard', 'alt' => '', 'caption' => 'Orchard', 'description' => ''),
    2 => array('title' => 'Orchard', 'alt' => 'Submarine', 'caption' => 'Orchard', 'description' => ''),
    3 => array('title' => 'Submarine', 'alt' => 'Orchard', 'caption' => 'Orchard', 'description' => ''),
    4 => array('title' => 'Submarine', 'alt' => 'Submarine', 'caption' => 'Orchard', 'description' => 'Orchard'),
);
WTAI_Media_Index::invalidate();
check($matcher->insert_images($body, array('max_images_per_post' => 3), 'Orchard') === $body, 'Missing or unrelated title/alt text is rejected even when captions and descriptions match.');

$GLOBALS['wfag_test_media'] = array(
    1 => array('title' => 'Orchard', 'alt' => 'Orchard', 'caption' => '', 'description' => ''),
    2 => array('title' => 'Ocean', 'alt' => 'Ocean', 'caption' => '', 'description' => ''),
);
WTAI_Media_Index::invalidate();
$section_body = '<h2>Orchard</h2><p>Orchard trees.</p><p>Harvest season.</p><h2>Ocean</h2>' . str_repeat('<p>' . str_repeat('Ocean waves. ', 50) . '</p>', 6);
$section_output = $matcher->insert_images($section_body, array('max_images_per_post' => 2), 'Orchard Ocean');
$placements = $matcher->get_report()['placements'];
check(array_column($placements, 'id') === array(1, 2), 'Images follow their matching section, even when the orchard section is far before the 50% target.');
check(strpos($section_output, 'wp-image-1') < strpos($section_output, '<h2>Ocean</h2>'), 'An orchard image never appears in the ocean section to satisfy a percentage.');
check($placements[0]['terms'] === array('orchard'), 'Preview terms describe the local placement context.');
$matcher->insert_images('<h2>Orchard</h2><p>Harvest season.</p>', array('max_images_per_post' => 1), 'Orchard');
check($matcher->get_report()['inserted'] === 1, 'The active section heading supplies context to its paragraph.');
$matcher->insert_images('<h2>Orchard</h2><h2>Ocean</h2><p>Ocean waves.</p>', array('max_images_per_post' => 1), 'Orchard');
check(array_column($matcher->get_report()['placements'], 'id') === array(2), 'A previous sibling heading cannot supply context to the next section.');
$GLOBALS['wfag_test_media'] = array(1 => $GLOBALS['wfag_test_media'][1]);
WTAI_Media_Index::invalidate();
$matcher->insert_images('<p>Orchard trees.</p>' . str_repeat('<p>Ocean waves.</p>', 8), array('max_images_per_post' => 3), 'Orchard');
check($matcher->get_report()['inserted'] === 1 && $matcher->get_report()['placements'][0]['actual_percent'] < 20, 'A single image stays with its topic instead of being forced to the end of an unrelated article.');

$GLOBALS['wfag_test_media'] = array(5 => array('title' => 'Cart', 'alt' => 'Cart', 'caption' => '', 'description' => ''));
WTAI_Media_Index::invalidate();
check($matcher->insert_images('<p>Art</p>', array('max_images_per_post' => 1), 'Art') === '<p>Art</p>', 'Whole-word matching does not match art to cart.');
$GLOBALS['wfag_test_media'] = array();
for ($id = 10; $id < 22; $id++) $GLOBALS['wfag_test_media'][$id] = array('title' => 'Orchard', 'alt' => 'Orchard', 'caption' => '', 'description' => '');
WTAI_Media_Index::invalidate();
$matcher->insert_images(str_repeat('<p>' . str_repeat('Orchard. ', 100) . '</p>', 12), array('max_images_per_post' => 12), 'Orchard');
check($matcher->get_report()['inserted'] === 12, 'The previous ten-image cap is removed.');
check(WTAI_Settings::sanitize('max_images_per_post', 250) === 250, 'Image preferences accept counts above ten.');
$GLOBALS['wfag_test_media'][10]['missing'] = true;
$matcher->insert_images($body, array('max_images_per_post' => 3), 'Orchard');
check($matcher->get_report()['inserted'] === 3 && !in_array(10, array_column($matcher->get_report()['placements'], 'id'), true), 'Unrenderable image attachments are skipped in favor of valid matches.');
$saved_options = $GLOBALS['wfag_test_options'] ?? array();
$saved_theme_mods = $GLOBALS['wfag_test_theme_mods'] ?? array();
$photo = array('title' => 'Orchard photo', 'alt' => 'Orchard trees', 'caption' => '', 'description' => '');
$GLOBALS['wfag_test_media'] = array(
    100 => $photo,
    101 => array_merge($photo, array('title' => 'OrchardLogo')),
    102 => array_merge($photo, array('alt' => 'Orchard ICONS')),
    103 => array_merge($photo, array('caption' => 'Orchard logomark')),
    104 => array_merge($photo, array('description' => 'Orchard logotype')),
    105 => array_merge($photo, array('filename' => '2026/orchard-logo2.png')),
    106 => array_merge($photo, array('filename' => '2026/OrchardFavicon.ico')),
    107 => array_merge($photo, array('filename' => '2026/XYZLogo.svg')),
    108 => array_merge($photo, array('title' => 'Orchard Silicon Valley')),
    109 => array_merge($photo, array('title' => 'Orchard iconic photograph')),
    110 => $photo, 111 => $photo, 112 => $photo,
);
$GLOBALS['wfag_test_options']['site_icon'] = 110;
$GLOBALS['wfag_test_options']['site_logo'] = 111;
$GLOBALS['wfag_test_theme_mods']['custom_logo'] = 112;
WTAI_Media_Index::invalidate();
$index = WTAI_Media_Index::get();
foreach (range(101, 107) as $id) check(!isset($index['images'][$id]), 'Logo/icon markers in metadata and filenames are excluded: ' . $id);
foreach (array(110, 111, 112) as $id) check(!isset($index['images'][$id]), 'Configured WordPress site graphics are excluded without labels: ' . $id);
check(array_keys($index['images']) === array(100, 108, 109), 'Normal photos and words such as silicon/iconic are retained.');
$matcher->insert_images($body, array('max_images_per_post' => 3), 'Orchard');
check($matcher->get_report()['inserted'] === 3 && !array_diff(array_column($matcher->get_report()['placements'], 'id'), array(100, 108, 109)), 'Eligible photos fill the requested count without using logos or icons.');
unset($GLOBALS['wfag_test_media'][108], $GLOBALS['wfag_test_media'][109]);
WTAI_Media_Index::invalidate();
$matcher->insert_images($body, array('max_images_per_post' => 3), 'Orchard');
check($matcher->get_report()['inserted'] === 1, 'Missing eligible photos result in fewer images rather than logo/icon substitutions.');
foreach (array('site_icon', 'site_logo', 'theme_mods_test_theme') as $option) {
    WTAI_Media_Index::get();
    WTAI_Media_Index::options_changed($option);
    check(get_transient(WTAI_Media_Index::CACHE_KEY) === false, 'Changing WordPress branding invalidates the media index: ' . $option);
}
set_transient('wtai_media_index_v2', array('old' => true), 3600);
WTAI_Media_Index::invalidate();
check(get_transient('wtai_media_index_v2') === false, 'Old image-index caches are removed after the eligibility update.');
$GLOBALS['wfag_test_options'] = $saved_options;
$GLOBALS['wfag_test_theme_mods'] = $saved_theme_mods;
unset($GLOBALS['wfag_test_media']);
WTAI_Media_Index::invalidate();
