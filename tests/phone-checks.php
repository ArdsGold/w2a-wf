<?php
/** Included by run.php; international phone links preserve visible text. */
if (PHP_SAPI !== 'cli') exit;

check(WTAI_Settings::defaults()['auto_link_phone_numbers'] === true, 'International phone linking is enabled by default.');
check(WTAI_Settings::sanitize('auto_link_phone_numbers', '0') === false, 'The phone linking checkbox can be disabled.');
$phone_formats = array(
    '+1 (202) 555-0123' => '+12025550123',
    '+44 20 7946 0958' => '+442079460958',
    '+ 44 20 7946 0958' => '+442079460958',
    '00 44 20 7946 0958' => '00442079460958',
    '0063 917 123 4567' => '00639171234567',
    '+63.917.123.4567' => '+639171234567',
    '+81 [90] 1234-5678' => '+819012345678',
    '+49' . "\u{202F}" . '151' . "\u{2013}" . '2345' . "\u{00A0}" . '6789' => '+4915123456789',
    '(216) 399-7569' => '2163997569',
    '216-399-7569' => '2163997569',
    '216.399.7569' => '2163997569',
    '216 399 7569' => '2163997569',
    '1 (216) 399-7569' => '12163997569',
    '1-216-399-7569' => '12163997569',
    '+123456789012345' => '+123456789012345',
);
foreach ($phone_formats as $display => $destination) {
    $output = $processor->process('<p>Call ' . $display . ' today.</p>', array());
    check(strpos($output, 'href="tel:' . $destination . '"') !== false, 'Phone destination is normalized while retaining its prefix: ' . $destination);
    $phone_dom = new DOMDocument();
    @$phone_dom->loadHTML('<?xml encoding="UTF-8">' . $output);
    check($phone_dom->getElementsByTagName('a')->item(0)->textContent === $display, 'The visible phone format is preserved.');
}
$disabled_phone = '<p>Call +1 (202) 555-0123.</p>';
check($processor->process($disabled_phone, array('auto_link_phone_numbers' => false)) === $disabled_phone, 'Disabling the option preserves unlinked phone text.');
$two_phones = $processor->process('<p>Call +1 202 555 0123 or 0044 20 7946 0958.</p>', array());
check(substr_count($two_phones, 'href="tel:') === 2, 'Multiple international numbers in one paragraph are linked separately.');
foreach (array(
    '<p>0917 123 4567; 2163997569; 116-399-7569; 216-199-7569; 2026-10-21; +123456; +1234567890123456.</p>',
    '<p data-phone="+12025550123">Attribute only.</p>',
    '<a href="tel:+12025550123">+1 (202) 555-0123</a>',
    '<a href="https://example.com">+1 (202) 555-0123</a>',
    '<code>+12025550123</code><pre>00442079460958</pre><script>var phone="+12025550123";</script><style>/* +12025550123 */</style>',
    '<p>https://example.com/+12025550123</p>',
    '<p>RefABC+12025550123xyz</p>',
    '<p><strong>+44</strong> 20 7946 0958</p>',
) as $unchanged_phone) {
    check($processor->process($unchanged_phone, array()) === $unchanged_phone, 'Unsupported local numbers, invalid candidates, attributes, existing links, code, and split text are left unchanged.');
}
$bold_phone = $processor->process('<p><strong>+1 (202) 555-0123</strong></p>', array());
check(strpos($bold_phone, '<strong><a href="tel:+12025550123">') !== false, 'Inline formatting around a whole phone number is preserved.');
check($processor->process($bold_phone, array()) === $bold_phone, 'Repeated processing never creates nested phone links.');
