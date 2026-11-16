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
$GLOBALS['wfag_test_pages'] = array(
    30 => array('title' => 'Our Services', 'url' => 'https://example.com/our-services/'),
    31 => array('title' => 'Drain Cleaning', 'url' => 'https://example.com/services/drain-cleaning/', 'parent' => 30),
    32 => array('title' => 'Emergency Plumbing', 'url' => 'https://example.com/services/plumbing/emergency/', 'parent' => 31),
    33 => array('title' => 'Drain Cleaning', 'url' => 'https://example.com/drain-cleaning-page/'),
    34 => array('title' => 'Draft Plumbing', 'parent' => 30, 'status' => 'draft'),
 );
check(array_keys(WTAI_Internal_Linker::service_targets()) === array(31, 32), 'Published Services page descendants are discovered without a menu or text input.');
$discovery = WTAI_Internal_Linker::service_discovery();
check($discovery['roots'][0] === array('type' => 'page', 'label' => 'Our Services') && array_keys($discovery['targets']) === array_keys(WTAI_Internal_Linker::service_targets()), 'Page discovery reports its root and exactly the targets used by linking.');
$service_output = $linker->insert_links('<p>Drain cleaning clears blockages.</p><p>Emergency plumbing helps.</p>', 19, 2);
$service_ids = array_column($linker->get_report()['placements'], 'id');
sort($service_ids);
check($linker->get_report()['service_inserted'] === 2 && $service_ids === array(31, 32), 'Automatically discovered service pages beat identically titled blog posts.');
$GLOBALS['wfag_test_pages'][31]['title'] = 'Drain-Cleaning Services';
$linker->insert_links('<p>Drain cleaning service is available.</p>', 19, 1);
check($linker->get_report()['service_inserted'] === 1, 'Automatic targets retain punctuation and singular/plural phrase matching.');
$GLOBALS['wfag_test_pages'][40] = array('title' => 'Water Heater Repairs');
$GLOBALS['wfag_test_pages'][41] = array('title' => 'Heater Installation', 'parent' => 40);
$GLOBALS['wfag_test_pages'][42] = array('title' => 'Private Repair', 'password' => 'secret');
$menu_item = function ($id, $parent, $title, $object = 'custom', $object_id = 0, $url = '#') {
    return (object) array('ID' => $id, 'menu_item_parent' => $parent, 'title' => $title, 'object' => $object, 'object_id' => $object_id, 'url' => $url);
};
$GLOBALS['wfag_nav_locations'] = array('primary' => 1);
$GLOBALS['wfag_nav_menus'][1] = array(
    $menu_item(100, 0, 'Our Services'),
    $menu_item(101, 100, 'Heating', 'page', 40),
    $menu_item(102, 101, 'Emergency', 'page', 32),
    $menu_item(103, 100, 'Repair guide', 'post', 11),
    $menu_item(104, 100, 'Private', 'page', 42),
    $menu_item(105, 100, 'External', 'custom', 0, 'https://elsewhere.test/external/'),
    $menu_item(106, 0, 'About', 'page', 33),
);
$targets = WTAI_Internal_Linker::service_targets();
check(array_keys($targets) === array(11, 32, 40, 41), 'Menu descendants, nested submenus, blog targets, and page descendants qualify independently of page-parent relationships.');
$discovery = WTAI_Internal_Linker::service_discovery();
check($discovery['roots'][0] === array('type' => 'menu', 'label' => 'Our Services') && array_keys($discovery['targets']) === array_keys($targets), 'Menu summary preserves the detection rules and eligible targets.');
check(!isset($targets[33], $targets[42]), 'Unrelated menu sections and protected targets are excluded.');
$linker->insert_links('<p>Water heater repairs are available.</p>', 19, 1);
check($linker->get_report()['placements'][0]['id'] === 40, 'A Services submenu page outranks the matching blog post.');
$GLOBALS['wfag_nav_menus'][2] = array($menu_item(200, 0, 'Services'), $menu_item(201, 200, 'Drain cleaning', 'page', 31));
check(!isset(WTAI_Internal_Linker::service_targets()[31]), 'Assigned menus take precedence over unused stored menus.');
$GLOBALS['wfag_nav_locations']['footer'] = 2;
check(isset(WTAI_Internal_Linker::service_targets()[31]), 'Services branches across multiple assigned menus are combined.');
$GLOBALS['wfag_nav_menus'][2][1] = $menu_item(201, 200, 'Drain cleaning', 'custom', 0, '/services/drain-cleaning/');
check(isset(WTAI_Internal_Linker::service_targets()[31]), 'Same-site relative custom menu links resolve to their published posts/pages.');
$GLOBALS['wfag_nav_locations'] = array();
check(isset(WTAI_Internal_Linker::service_targets()[31]), 'Unassigned menus are inspected when no theme location is assigned, supporting Elementor navigation.');
$GLOBALS['wfag_nav_menus'] = array();
$GLOBALS['wfag_block_menus'] = array((object) array('post_content' => json_encode(array(
    array('blockName' => 'core/navigation-submenu', 'attrs' => array('label' => 'Services'), 'innerBlocks' => array(
        array('blockName' => 'core/navigation-link', 'attrs' => array('label' => 'Heating', 'type' => 'page', 'id' => 40)),
        array('blockName' => 'core/navigation-submenu', 'attrs' => array('label' => 'More'), 'innerBlocks' => array(
            array('blockName' => 'core/navigation-link', 'attrs' => array('type' => 'page', 'id' => 32)),
        )),
    )),
))));
check(array_keys(WTAI_Internal_Linker::service_targets()) === array(32, 40, 41), 'Block navigation detects Services branches, nested entries, and page descendants.');
$discovery = WTAI_Internal_Linker::service_discovery();
check($discovery['roots'][0] === array('type' => 'block_menu', 'label' => 'Services') && array_keys($discovery['targets']) === array_keys(WTAI_Internal_Linker::service_targets()), 'Block-menu summary identifies the root without changing the eligible set.');
unset($GLOBALS['wfag_block_menus'], $GLOBALS['wfag_nav_locations'], $GLOBALS['wfag_nav_menus']);
$GLOBALS['wfag_test_pages'] = array(30 => array('title' => 'Services'));
check(WTAI_Internal_Linker::service_targets() === array(), 'An empty Services hierarchy does not prevent imports.');
$linker->insert_links('<p>Drain cleaning is available.</p>', 19, 1);
check($linker->get_report()['service_inserted'] === 0 && $linker->get_report()['inserted'] === 1, 'Normal published-blog linking continues when no service targets are found.');
unset($GLOBALS['wfag_test_pages']);
unset($GLOBALS['wfag_test_posts'], $GLOBALS['wfag_test_seo_meta'][12], $GLOBALS['wfag_test_seo_meta'][11]);
