<?php
/** Standalone checks with mocked HTTP; no WordPress/database or GitHub access. */
if (PHP_SAPI !== 'cli') exit;
define('ABSPATH', __DIR__ . '/');
define('WTAI_VERSION', 'r4.0.0');
$cached = false;
$requests = array();
$expiry = 0;
$response = array();
function get_transient($key) { $GLOBALS['cache_key'] = $key; return $GLOBALS['cached']; }
function set_transient($key, $value, $seconds) { $GLOBALS['cached'] = $value; $GLOBALS['expiry'] = $seconds; }
function wp_safe_remote_get($url, $args) { $GLOBALS['requests'][] = array($url, $args); return $GLOBALS['response']; }
function is_wp_error($value) { return $value instanceof RuntimeException; }
function wp_remote_retrieve_response_code($value) { return $value['response']['code']; }
function wp_remote_retrieve_body($value) { return $value['body']; }
require dirname(__DIR__) . '/includes/class-version-checker.php';
$checks = 0;
function version_check($condition, $message) {
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
function header_response($version, $status = 200) {
    $GLOBALS['cached'] = false;
    $GLOBALS['response'] = array('response' => array('code' => $status), 'body' => "<?php\n/**\n * Plugin Name: Wolf Forge Article Generator\n * Version: $version\n */\nthrow new Exception('remote PHP must never execute');");
    return WTAI_Version_Checker::status();
}
foreach (array('r4.0.0', '4.0.0', 'v4.0.0', 'R4.0.0', '4.0', '4.0.0.0') as $version) {
    $result = header_response($version);
    version_check(!$result['mismatch'] && $result['repository'] === $version, 'Equivalent versions remain hidden: ' . $version);
}
foreach (array('r4.1.0', 'r3.9.0', '4.0.1', '4.0.0-beta.1') as $version) {
    $result = header_response($version);
    version_check($result['mismatch'] && $result['installed'] === WTAI_VERSION, 'Both newer and older versions are mismatches: ' . $version);
}
version_check($expiry === 21600, 'Successful checks cache only the repository version for six hours.');
version_check($GLOBALS['cache_key'] === WTAI_Version_Checker::CACHE_KEY . '_' . substr(hash('sha256', WTAI_VERSION), 0, 12), 'Changing the running plugin version uses a fresh repository check.');
$before = count($requests);
version_check(WTAI_Version_Checker::status()['mismatch'] && count($requests) === $before, 'A cached mismatch does not repeat the network request.');
$cached = array('version' => 'r4.0.0');
version_check(!WTAI_Version_Checker::status()['mismatch'] && count($requests) === $before, 'The running version is compared each time, including after a plugin upgrade.');
foreach (array('', '<script>alert(1)</script>', 'latest', '4', '4.0.0 broken', '4.0.0;phpinfo()', 'r4.0.0<script>') as $version) {
    $result = header_response($version);
    version_check(!$result['mismatch'] && $result['repository'] === '', 'Invalid/untrusted versions never create a notice.');
}
foreach (array(301, 403, 404, 429, 500) as $status) {
    version_check(!header_response('r5.0.0', $status)['mismatch'], 'HTTP failure/redirect does not produce a mismatch.');
    version_check($expiry === 900, 'Failed checks back off for 15 minutes.');
}
$cached = false;
$response = new RuntimeException('Connection failed');
version_check(!WTAI_Version_Checker::status()['mismatch'], 'A network failure stays silent.');
$before = count($requests);
WTAI_Version_Checker::status();
version_check(count($requests) === $before, 'Failure backoff avoids repeated network requests.');
$cached = false;
$response = array('response' => array('code' => 200), 'body' => '<html>GitHub error page</html>');
version_check(!WTAI_Version_Checker::status()['mismatch'], 'HTML without a version header is ignored.');
version_check(header_response('r4.2.0 */')['repository'] === 'r4.2.0', 'Inline closing comment syntax is parsed correctly.');
$request = end($requests);
version_check($request[0] === 'https://raw.githubusercontent.com/ArdsGold/w2a-wf/main/word-to-article-importer.php', 'Only the fixed public repository plugin header is requested.');
version_check($request[1]['timeout'] === 5 && $request[1]['redirection'] === 0 && $request[1]['limit_response_size'] === 8192, 'Remote requests have bounded time, size, and no redirects.');
echo "PASS: $checks version-checker checks (mocked HTTP).\n";
