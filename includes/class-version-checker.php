<?php
/** Read the public repository's version header; never download or execute an update. */
if (!defined('ABSPATH')) exit;

class WTAI_Version_Checker {
    const CACHE_KEY = 'wtai_github_version_v1';
    private const SOURCE = 'https://raw.githubusercontent.com/ArdsGold/w2a-wf/main/word-to-article-importer.php';

    private static function normalized($version) {
        if (!is_string($version) || strlen($version) > 64 || !preg_match('/^[rv]?(\d+(?:\.\d+){1,3})(-[a-z0-9]+(?:[.-][a-z0-9]+)*)?$/iD', trim($version), $match)) return '';
        $numbers = array_map('intval', explode('.', $match[1]));
        while (count($numbers) > 3 && end($numbers) === 0) array_pop($numbers);
        return implode('.', array_pad($numbers, 3, 0)) . strtolower($match[2] ?? '');
    }

    private static function repository_version() {
        $cache_key = self::CACHE_KEY . '_' . substr(hash('sha256', WTAI_VERSION), 0, 12);
        $cached = get_transient($cache_key);
        if (is_array($cached)) return is_string($cached['version'] ?? null) ? $cached['version'] : '';
        $version = '';
        $response = wp_safe_remote_get(self::SOURCE, array('timeout' => 5, 'redirection' => 0, 'limit_response_size' => 8192));
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $body = substr(wp_remote_retrieve_body($response), 0, 8192);
            if (preg_match('/^[ \t\/*#@]*Version:[ \t]*([^\r\n]+)/mi', $body, $match)) {
                $candidate = trim(preg_replace('/\s*(?:\*\/|\?>).*$/', '', $match[1]));
                if (self::normalized($candidate) !== '') $version = $candidate;
            }
        }
        // Failure backoff avoids repeated requests without treating an outage as a mismatch.
        set_transient($cache_key, array('version' => $version), $version === '' ? 900 : 21600);
        return $version;
    }

    public static function status() {
        $remote = self::repository_version();
        $installed = self::normalized(WTAI_VERSION);
        $repository = self::normalized($remote);
        return array(
            'mismatch' => $installed !== '' && $repository !== '' && !version_compare($installed, $repository, '=='),
            'installed' => WTAI_VERSION,
            'repository' => $remote,
        );
    }
}
