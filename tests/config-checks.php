<?php
/** Run standalone: php -n tests/config-checks.php. Never reads or rewrites the real private config. */
if (PHP_SAPI !== 'cli') exit;
define('ABSPATH', __DIR__ . '/');
function get_option($name, $default = false) { return $GLOBALS['config_options'][$name] ?? $default; }
function get_transient($name) { return false; }
function __($text, $domain = '') { return $text; }
$checks = 0;
function config_check($condition, $message) {
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
$root = sys_get_temp_dir() . '/wfag-config-' . bin2hex(random_bytes(6));
mkdir($root);
mkdir($root . '/includes');
$class_file = $root . '/includes/class-google-drive.php';
$config_file = $root . '/config.php';
copy(dirname(__DIR__) . '/includes/class-google-drive.php', $class_file);
try {
    require $class_file;
    $key = new ReflectionMethod('WTAI_Google_Drive', 'key');
    if (PHP_VERSION_ID < 80100) $key->setAccessible(true);
    config_check($key->invoke(null) === '', 'A missing private file leaves Drive unconfigured.');
    file_put_contents($config_file, "<?php return array('google_drive_api_key' => '');");
    config_check($key->invoke(null) === '', 'The empty distributed placeholder is not treated as a key.');
    file_put_contents($config_file, "<?php return array('google_drive_api_key' => '  test-private-bundled-key-12345  ');");
    config_check($key->invoke(null) === 'test-private-bundled-key-12345', 'The plugin-local key is loaded and trimmed.');
    config_check(WTAI_Google_Drive::status()['source'] === 'default', 'A bundled key is reported as the default.');
    config_check(strpos(json_encode(WTAI_Google_Drive::status()), 'test-private-bundled-key') === false, 'Status responses never reveal the bundled key.');
    $GLOBALS['config_options']['wtai_google_drive_api_key'] = 'test-site-override';
    config_check($key->invoke(null) === 'test-site-override', 'A site override takes priority over the bundled key.');
    unset($GLOBALS['config_options']['wtai_google_drive_api_key']);
    config_check($key->invoke(null) === 'test-private-bundled-key-12345', 'Removing a site override restores the bundled default.');
    file_put_contents($config_file, '<?php return 123;');
    config_check($key->invoke(null) === '', 'Unexpected configuration data is ignored safely.');
    file_put_contents($config_file, "<?php return array('google_drive_api_key' => 'test-private-bundled-key-12345');");
    define('WTAI_GOOGLE_DRIVE_API_KEY', 'test-server-default');
    config_check($key->invoke(null) === 'test-server-default', 'The wp-config constant takes priority over the bundled default.');
    $GLOBALS['config_options']['wtai_google_drive_api_key'] = 'test-site-override';
    config_check($key->invoke(null) === 'test-site-override', 'A site override also takes priority over wp-config.');
    echo 'PASS: ' . $checks . " private configuration checks.\n";
} finally {
    if (is_file($config_file)) unlink($config_file);
    unlink($class_file);
    rmdir($root . '/includes');
    rmdir($root);
}
