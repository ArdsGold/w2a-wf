<?php
/** Live Google Places photos. Persist only profile links, place IDs, and ordinal slots. */
if (!defined('ABSPATH')) exit;

class WTAI_GBP_Photos {
    private static function key() {
        return defined('WTAI_GOOGLE_PLACES_API_KEY') ? (string) WTAI_GOOGLE_PLACES_API_KEY : (string) get_option('wtai_google_places_api_key', '');
    }

    public static function has_key() { return self::key() !== ''; }

    private static function place_id($id) { return is_string($id) && preg_match('/^[A-Za-z0-9_-]{10,200}$/D', $id); }

    private static function google_link($url) {
        $parts = wp_parse_url($url);
        $hosts = array('google.com', 'www.google.com', 'maps.google.com', 'maps.app.goo.gl', 'goo.gl', 'g.page', 'www.google.co.uk', 'www.google.com.ph', 'www.google.ca', 'www.google.com.au');
        return $parts && ($parts['scheme'] ?? '') === 'https' && in_array(strtolower($parts['host'] ?? ''), $hosts, true) && !isset($parts['user']) && !isset($parts['pass']) && !isset($parts['port']);
    }

    private static function api($path, $fields = '', $body = null) {
        if (!self::has_key()) throw new RuntimeException(__('Configure a Google Places API key before using GBP photos.', 'wolf-forge-article-generator'));
        $args = array('timeout' => 12, 'redirection' => 0, 'limit_response_size' => 1048576, 'headers' => array('X-Goog-Api-Key' => self::key()));
        if ($fields !== '') $args['headers']['X-Goog-FieldMask'] = $fields;
        if ($body !== null) {
            $args['method'] = 'POST';
            $args['headers']['Content-Type'] = 'application/json';
            $args['body'] = wp_json_encode($body);
        }
        $response = wp_safe_remote_request('https://places.googleapis.com/v1/' . $path, $args);
        if (is_wp_error($response)) throw new RuntimeException(__('Google Places could not be reached. Retry later.', 'wolf-forge-article-generator'));
        $status = wp_remote_retrieve_response_code($response);
        if ($status !== 200) throw new RuntimeException(sprintf(__('Google Places returned HTTP %d. Check the API key, Places API (New), billing, and key restrictions.', 'wolf-forge-article-generator'), $status));
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($data)) throw new RuntimeException(__('Google returned an invalid photo response.', 'wolf-forge-article-generator'));
        return $data;
    }

    public static function profile($url) {
        if (!self::google_link($url)) throw new RuntimeException(__('Paste an HTTPS Google Maps business/profile link.', 'wolf-forge-article-generator'));
        $saved = get_option('wtai_gbp_resolved_profile', array());
        if (($saved['url'] ?? '') === $url && self::place_id($saved['place_id'] ?? '')) return self::details($saved['place_id']);
        $original = $url;
        for ($hop = 0; $hop < 5; $hop++) {
            $host = strtolower(wp_parse_url($url, PHP_URL_HOST));
            if (!in_array($host, array('maps.app.goo.gl', 'goo.gl', 'g.page'), true)) break;
            $response = wp_safe_remote_get($url, array('timeout' => 10, 'redirection' => 0, 'limit_response_size' => 65536));
            if (is_wp_error($response)) throw new RuntimeException(__('The Google profile short link could not be opened.', 'wolf-forge-article-generator'));
            $next = wp_remote_retrieve_header($response, 'location');
            if (!is_string($next) || !self::google_link($next)) throw new RuntimeException(__('Use the full Google Maps business link; this short link did not resolve to a supported Google URL.', 'wolf-forge-article-generator'));
            $url = $next;
        }
        $parts = wp_parse_url($url);
        parse_str($parts['query'] ?? '', $query);
        $id = $query['query_place_id'] ?? ($query['place_id'] ?? '');
        if (!self::place_id($id) && isset($query['q']) && is_string($query['q']) && strpos($query['q'], 'place_id:') === 0) $id = substr($query['q'], 9);
        if (!self::place_id($id) && preg_match('/!1s([A-Za-z0-9_-]{10,200})(?:!|$)/', rawurldecode($url), $match)) $id = $match[1];
        if (!self::place_id($id)) {
            $text = '';
            if (preg_match('~/maps/place/([^/]+)~', $parts['path'] ?? '', $match)) $text = urldecode($match[1]);
            elseif (isset($query['query']) && is_string($query['query'])) $text = $query['query'];
            elseif (isset($query['q']) && is_string($query['q'])) $text = $query['q'];
            if (trim($text) === '') throw new RuntimeException(__('This link does not identify a business. Use a Maps place link with its business name or query_place_id.', 'wolf-forge-article-generator'));
            $body = array('textQuery' => $text, 'pageSize' => 2);
            if (preg_match('/@(-?\d+\.\d+),(-?\d+\.\d+)/', $url, $coordinates)) {
                $lat = (float) $coordinates[1]; $lng = (float) $coordinates[2];
                if (abs($lat) <= 90 && abs($lng) <= 180) $body['locationBias'] = array('circle' => array('center' => array('latitude' => $lat, 'longitude' => $lng), 'radius' => 5000));
            }
            $search = self::api('places:searchText', 'places.id', $body);
            if (count($search['places'] ?? array()) !== 1) throw new RuntimeException(__('The profile search was ambiguous or empty. Use a Google Maps link containing query_place_id.', 'wolf-forge-article-generator'));
            $id = $search['places'][0]['id'] ?? '';
        }
        $profile = self::details($id);
        update_option('wtai_gbp_resolved_profile', array('url' => $original, 'place_id' => $profile['id']), false);
        return $profile;
    }

    private static function details($id) {
        if (!self::place_id($id)) throw new RuntimeException(__('Google did not return a valid place ID.', 'wolf-forge-article-generator'));
        // Request-local reuse only: Google photo references must not be cached in WordPress.
        static $places = array();
        $cache_key = $id . ':' . hash('sha256', self::key());
        if (!isset($places[$cache_key])) {
            try {
                $data = self::api('places/' . rawurlencode($id), 'id,displayName,photos');
                if (($data['id'] ?? '') !== $id) throw new RuntimeException(__('Google returned a different business. Check the profile link.', 'wolf-forge-article-generator'));
                $data['photos'] = is_array($data['photos'] ?? null) ? array_slice($data['photos'], 0, 10) : array();
                $places[$cache_key] = $data;
            } catch (Throwable $error) { $places[$cache_key] = $error; }
        }
        if ($places[$cache_key] instanceof Throwable) throw $places[$cache_key];
        return $places[$cache_key];
    }

    public static function placeholder($place, $slot) {
        return '[wtai_gbp_photo place="' . $place . '" slot="' . (int) $slot . '"]';
    }

    private static function image_url($url) {
        $parts = is_string($url) ? wp_parse_url($url) : false;
        $host = strtolower($parts['host'] ?? '');
        return $parts && ($parts['scheme'] ?? '') === 'https' && ($host === 'googleusercontent.com' || substr($host, -22) === '.googleusercontent.com') && !isset($parts['user']) && !isset($parts['port']);
    }

    public static function shortcode($attributes) {
        $id = $attributes['place'] ?? '';
        $slot = $attributes['slot'] ?? '';
        if (!self::place_id($id) || !is_scalar($slot) || !preg_match('/^[0-9]$/D', (string) $slot)) return '';
        if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
        static $rendered = array();
        $render_key = $id . ':' . $slot . ':' . hash('sha256', self::key());
        if (isset($rendered[$render_key])) return $rendered[$render_key];
        try {
            $profile = self::details($id);
            $photo = $profile['photos'][(int) $slot] ?? array();
            $name = $photo['name'] ?? '';
            if (!is_string($name) || !preg_match('~^places/' . preg_quote($id, '~') . '/photos/[A-Za-z0-9_-]+$~D', $name)) return '';
            $media = self::api($name . '/media?maxWidthPx=1200&skipHttpRedirect=true');
            $url = $media['photoUri'] ?? '';
            if (!self::image_url($url)) return '';
            $source = $photo['googleMapsUri'] ?? '';
            if (!is_string($source) || !self::google_link($source)) $source = 'https://www.google.com/maps/search/?api=1&query=business&query_place_id=' . rawurlencode($id);
            $html = '<a href="' . esc_url($source) . '" target="_blank" rel="noopener"><img class="wtai-gbp-image" loading="lazy" src="' . esc_url($url) . '" alt="' . esc_attr(__('Photo from this Google Business Profile', 'wolf-forge-article-generator')) . '" style="max-width:100%;height:auto"></a>';
            $html .= '<span class="wtai-gbp-attribution" style="display:block;padding:10px;background:#fff;color:#1f1f1f;font:14px sans-serif">';
            $html .= '<a href="' . esc_url($source) . '" target="_blank" rel="noopener"><img src="' . esc_url(WTAI_PLUGIN_URL . 'assets/images/google-maps-logo.svg') . '" alt="Google Maps" style="height:18px;width:auto;vertical-align:middle;margin:0 10px 5px 0"></a>';
            foreach (is_array($photo['authorAttributions'] ?? null) ? $photo['authorAttributions'] : array() as $author) {
                if (self::image_url($author['photoUri'] ?? '')) $html .= '<img src="' . esc_url($author['photoUri']) . '" alt="" style="width:24px;height:24px;vertical-align:middle;margin-right:4px">';
                $label = esc_html(is_string($author['displayName'] ?? null) ? $author['displayName'] : '');
                $uri = $author['uri'] ?? '';
                $html .= (is_string($uri) && self::google_link($uri) ? '<a href="' . esc_url($uri) . '" target="_blank" rel="noopener">' . $label . '</a>' : $label) . ' ';
            }
            $rendered[$render_key] = $html . '</span>';
            return $rendered[$render_key];
        } catch (Throwable $error) {
            $rendered[$render_key] = '';
            return ''; // An outage or removed key must not break article rendering or disclose credentials.
        }
    }

    public static function preview($html) {
        return preg_replace_callback('/\[wtai_gbp_photo place="([A-Za-z0-9_-]{10,200})" slot="([0-9])"\]/', function ($match) { return self::shortcode(array('place' => $match[1], 'slot' => $match[2])); }, $html);
    }
}
