<?php
/** Included by run.php; native title/keyphrase selection and HTML preservation. */
if (PHP_SAPI !== 'cli') exit;

$GLOBALS['wfag_test_posts'] = array(
    10 => array('title' => 'Drain Cleaning', 'url' => 'https://example.com/drain-cleaning/'),
    11 => array('title' => 'Emergency Plumbing', 'url' => 'https://example.com/emergency-plumbing/'),
    12 => array('title' => 'Water Heater Repairs', 'url' => 'https://example.com/water-heater/'),
    13 => array('title' => 'Hidden Plumbing', 'status' => 'draft'),
    14 => array('title' => 'Scheduled Plumbing', 'status' => 'future'),
    15 => array('title' => 'Private Plumbing', 'status' => 'private'),
    16 => array('title' => 'Page Plumbing', 'type' => 'page'),
    17 => array('title' => 'Password Plumbing', 'password' => 'secret'),
    18 => array('title' => 'External Plumbing', 'url' => 'https://elsewhere.test/external/'),
    19 => array('title' => 'Self Plumbing'),
);
$linker = new WTAI_Internal_Linker();
$body = '<p>Drain cleaning clears blockages.</p><p>Emergency plumbing helps.</p><p>Water heater repairs restore hot water.</p>';
check(WTAI_Settings::defaults()['max_internal_links_per_post'] === 5 && WTAI_Settings::sanitize('max_internal_links_per_post', -3) === 0, 'Internal links default to five and accept a nonnegative maximum.');
check($linker->insert_links($body, 19, 0) === $body, 'A zero maximum preserves content exactly.');
$output = $linker->insert_links($body, 19, 3);
check($linker->get_report()['inserted'] === 3, 'Relevant published post titles produce the requested count when matches exist.');
check(strpos($output, '>Drain cleaning</a>') !== false, 'Anchor capitalization and visible article text are preserved.');
check($linker->insert_links($output, 19, 3) === $output && $linker->get_report()['inserted'] === 0, 'Repeated processing preserves existing links and never repeats destinations.');
$linker->insert_links($body, 19, 1);
check($linker->get_report()['inserted'] === 1, 'The entered maximum is respected.');
$linker->insert_links($body, 19, 20);
check($linker->get_report()['inserted'] === 3 && $linker->get_report()['message'] !== '', 'Insufficient relevant matches produce fewer links and an explanatory report.');
$unsafe = '<p>Hidden plumbing. Scheduled plumbing. Private plumbing. Page plumbing. Password plumbing. External plumbing. Self plumbing.</p>';
check($linker->insert_links($unsafe, 19, 10) === $unsafe, 'Draft, future, private, page, password-protected, external, and source targets are excluded.');
$existing = '<p><a href="/drain-cleaning/">Existing destination</a></p><p>Drain cleaning can help.</p>';
check($linker->insert_links($existing, 19, 3) === $existing, 'Existing root-relative links exclude their destination from new links.');
$existing = '<p><a href="//example.com/drain-cleaning/">Existing destination</a></p><p>Drain cleaning.</p>';
check($linker->insert_links($existing, 19, 3) === $existing, 'Existing protocol-relative links are also respected.');
$protected = '<h2>Drain cleaning</h2><p><a href="https://external.test/">Emergency plumbing</a></p><figure><p>Water heater repairs</p><figcaption>Drain cleaning</figcaption></figure><pre><p>Drain cleaning</p></pre><p><code>Drain cleaning</code></p><script>Drain cleaning</script>';
check($linker->insert_links($protected, 19, 10) === $protected, 'Existing anchors, headings, image figures, captions, code, scripts, and preformatted text stay unchanged.');
$linker->insert_links('<p>Drain cleaning and emergency plumbing.</p>', 19, 3);
check($linker->get_report()['inserted'] === 1, 'Only one new link is added per paragraph.');
$linker->insert_links('<ul><li>Drain cleaning.</li><li>Emergency plumbing.</li></ul><table><tr><td>Water heater repairs.</td></tr></table>', 19, 3);
check($linker->get_report()['inserted'] === 3, 'Separate list items and table cells are safe linking blocks.');
check($linker->insert_links('<p>PreDrain cleaningSuffix.</p>', 19, 3) === '<p>PreDrain cleaningSuffix.</p>', 'Phrase matches never link inside longer words.');
check($linker->insert_links('<p><strong>Drain</strong> cleaning.</p>', 19, 3) === '<p><strong>Drain</strong> cleaning.</p>', 'Anchors split across formatting nodes are conservatively skipped.');
$bold = $linker->insert_links('<p><strong>Drain cleaning</strong>.</p>', 19, 3);
check(strpos($bold, '<strong><a href=') !== false, 'Formatting around a complete anchor is preserved.');
$GLOBALS['wfag_test_seo_meta'][12]['_yoast_wpseo_focuskw'] = 'Hot water systems';
$GLOBALS['wfag_test_seo_meta'][11]['rank_math_focus_keyword'] = 'Emergency repairs, Burst pipe repair';
$seo_body = '<p>Hot water systems need maintenance.</p><p>Burst pipe repair helps.</p>';
$linker->insert_links($seo_body, 19, 3);
check($linker->get_report()['inserted'] === 2, 'Yoast and comma-separated Rank Math keyphrases supply exact relevant anchors.');
$GLOBALS['wfag_test_posts'][20] = array('title' => 'Heating &amp; Cooling');
$encoded = $linker->insert_links('<p>Heating &amp; cooling is useful.</p>', 19, 1);
check($linker->get_report()['inserted'] === 1 && strpos($encoded, '>Heating &amp; cooling</a>') !== false, 'Entity-encoded titles match decoded article text safely.');
check($linker->insert_links('<p>Unrelated landscape maintenance.</p>', 19, 3) === '<p>Unrelated landscape maintenance.</p>', 'Unrelated articles are never padded with arbitrary links.');
$GLOBALS['wfag_test_posts'][21] = array('title' => 'Plumbing');
check($linker->insert_links('<p>Plumbing.</p>', 19, 3) === '<p>Plumbing.</p>', 'Single-word titles are skipped in favor of specific multiword anchors.');
$first = $linker->insert_links($body, 19, 3);
check($linker->insert_links($body, 19, 3) === $first, 'The same article and targets produce deterministic links.');
// A full first page must not hide valid targets later in the catalog.
$saved_posts = $GLOBALS['wfag_test_posts'];
$GLOBALS['wfag_test_posts'] = array();
for ($id = 100; $id <= 300; $id++) $GLOBALS['wfag_test_posts'][$id] = array('title' => $id === 300 ? 'Drain Cleaning' : 'Unmatched subject ' . $id);
$linker->insert_links('<p>Drain cleaning.</p>', 19, 1);
check($linker->get_report()['placements'][0]['id'] === 300, 'Target discovery continues past the first 200 published posts.');
$GLOBALS['wfag_test_posts'] = $saved_posts;
$GLOBALS['wfag_test_posts'] = array(
    1001 => array('title' => 'Emergency Drain Cleaning in Cleveland'),
    1002 => array('title' => 'How to Choose Reliable Water Heater Repair Services'),
);
$partial = $linker->insert_links('<p>Drain cleaning clears blockages.</p>', 19, 3);
check($linker->get_report()['inserted'] === 1 && strpos($partial, '>Drain cleaning</a>') !== false, 'A meaningful title fragment links without requiring the complete destination title.');
$longer = $linker->insert_links('<p>Emergency drain cleaning helps.</p>', 19, 3);
check(strpos($longer, '>Emergency drain cleaning</a>') !== false, 'The longest matching title phrase takes priority over its shorter fragments.');
$linker->insert_links('<p>Water heater repair restores hot water.</p>', 19, 1);
check($linker->get_report()['placements'][0]['id'] === 1002, 'Topic phrases inside a question-style title qualify.');
check($linker->insert_links('<p>Cleveland.</p><p>How to choose.</p><p>In Cleveland.</p>', 19, 3) === '<p>Cleveland.</p><p>How to choose.</p><p>In Cleveland.</p>', 'Single words and generic connective/question fragments do not become anchors.');
check($linker->insert_links('<p>PreDrain cleaningSuffix.</p>', 19, 1) === '<p>PreDrain cleaningSuffix.</p>', 'Partial-title matches also enforce whole-word boundaries.');
$GLOBALS['wfag_test_posts'][1003] = array('title' => 'Residential DRAIN CLEANING in Akron');
check($linker->insert_links('<p>Drain cleaning helps.</p>', 19, 3) === '<p>Drain cleaning helps.</p>', 'A case-insensitive fragment shared by different destinations is skipped as ambiguous.');
$GLOBALS['wfag_test_seo_meta'][1003]['_yoast_wpseo_focuskw'] = 'Drain cleaning';
$linker->insert_links('<p>Drain cleaning helps.</p>', 19, 3);
check($linker->get_report()['inserted'] === 1 && $linker->get_report()['placements'][0]['id'] === 1003, 'An explicit SEO keyphrase remains authoritative when title fragments are ambiguous.');
unset($GLOBALS['wfag_test_seo_meta'][1003]);
$GLOBALS['wfag_test_posts'] = array(1004 => array('title' => 'Drain / Cleaning Guide'));
check($linker->insert_links('<p>Drain cleaning helps.</p>', 19, 1) === '<p>Drain cleaning helps.</p>', 'Title fragments do not cross punctuation-separated topics.');
$GLOBALS['wfag_test_posts'] = array(1005 => array('title' => 'Emergency Réparation Chaudière à Paris'));
$linker->insert_links('<p>Réparation chaudière.</p>', 19, 1);
check($linker->get_report()['inserted'] === 1, 'Contiguous Unicode title phrases match without transliterating visible anchor text.');
$GLOBALS['wfag_test_posts'] = $saved_posts;
unset($GLOBALS['wfag_test_posts'], $GLOBALS['wfag_test_seo_meta'][12], $GLOBALS['wfag_test_seo_meta'][11]);
