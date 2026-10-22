<?php
/** SEO metadata for supported plugins. */
if (!defined('ABSPATH')) exit;

class WTAI_SEO {
    public static function is_rank_math_active() {
        return defined('RANK_MATH_VERSION') || class_exists('RankMath');
    }

    public static function is_yoast_active() {
        return defined('WPSEO_VERSION') || class_exists('WPSEO_Options');
    }

    public static function yoast_status() {
        if (!function_exists('get_plugins')) require_once ABSPATH . 'wp-admin/includes/plugin.php';
        $active = self::is_yoast_active();
        $status = array('installed' => $active, 'active' => $active, 'version' => defined('WPSEO_VERSION') ? WPSEO_VERSION : '');
        foreach (get_plugins() as $file => $plugin) {
            $domain = isset($plugin['TextDomain']) ? $plugin['TextDomain'] : '';
            if (!in_array($domain, array('wordpress-seo', 'wordpress-seo-premium'), true)
                && !in_array($file, array('wordpress-seo/wp-seo.php', 'wordpress-seo-premium/wp-seo-premium.php'), true)) {
                continue;
            }
            $status['installed'] = true;
            if (!$status['version'] && !empty($plugin['Version'])) $status['version'] = $plugin['Version'];
        }
        return $status;
    }

    public static function plugin_statuses() {
        $yoast = self::yoast_status();
        $statuses = array(
            'Yoast SEO' => $yoast['active'] ? 'active' : ($yoast['installed'] ? 'inactive' : 'not_installed'),
            'Rank Math SEO' => self::is_rank_math_active() ? 'active' : 'not_installed',
        );
        foreach (get_plugins() as $file => $plugin) {
            if (in_array($plugin['TextDomain'] ?? '', array('seo-by-rank-math', 'rank-math-pro'), true)
                || in_array($file, array('seo-by-rank-math/rank-math.php', 'seo-by-rank-math-pro/rank-math-pro.php'), true)) {
                if ($statuses['Rank Math SEO'] !== 'active') $statuses['Rank Math SEO'] = is_plugin_active($file) ? 'active' : 'inactive';
            }
        }
        return $statuses;
    }

    /**
     * Yoast receives SEO fields; Rank Math receives only its focus keyphrase.
     */
    public function apply($post_id, $title, $content) {
        $title = wp_strip_all_tags($title);
        $description = $this->build_meta_description($content);
        $rank_math = self::is_rank_math_active();
        $yoast = self::is_yoast_active();
        $focus_keyphrase = ($rank_math || $yoast) ? $this->build_focus_keyphrase($title, $content) : '';
        if ($rank_math) {
            if (get_post_meta($post_id, 'rank_math_focus_keyword', true) === '') update_post_meta($post_id, 'rank_math_focus_keyword', $focus_keyphrase);
        }
        if ($yoast) {
            update_post_meta($post_id, '_yoast_wpseo_title', $title);
            update_post_meta($post_id, '_yoast_wpseo_metadesc', $description);
            if (get_post_meta($post_id, '_yoast_wpseo_focuskw', true) === '') update_post_meta($post_id, '_yoast_wpseo_focuskw', $focus_keyphrase);
        }
        if ($rank_math || $yoast) return;
        if (defined('AIOSEO_VERSION') || class_exists('AIOSEO\\Plugin\\AIOSEO')) {
            update_post_meta($post_id, '_aioseo_title', $title);
            update_post_meta($post_id, '_aioseo_description', $description);
        } elseif (defined('SEOPRESS_VERSION')) {
            update_post_meta($post_id, '_seopress_titles_title', $title);
            update_post_meta($post_id, '_seopress_titles_desc', $description);
        }
    }

    /**
     * Select a deterministic focus keyphrase from the title, headings, and body.
     */
    private function build_focus_keyphrase($title, $content) {
        $stop_words = array_flip(array(
            'a','an','and','are','as','at','be','been','being','but','by','can','could','did','do','does','for','from',
            'had','has','have','how','if','in','into','is','it','its','may','more','most','of','on','or','should','so',
            'that','the','their','there','these','they','this','those','to','was','were','what','when','where','which',
            'who','why','will','with','would','you','your'
        ));

        $normalize = function ($text) {
            $text = html_entity_decode(wp_strip_all_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $text = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
            return preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text);
        };

        $tokenize = function ($text) use ($normalize, $stop_words) {
            $words = preg_split('/\s+/u', trim($normalize($text)), -1, PREG_SPLIT_NO_EMPTY);
            return array_values(array_filter($words, function ($word) use ($stop_words) {
                return !isset($stop_words[$word]);
            }));
        };

        $title_words = $tokenize($title);
        if (!$title_words) {
            return '';
        }

        $body_text = $normalize($content);
        $body_words = preg_split('/\s+/u', trim($body_text), -1, PREG_SPLIT_NO_EMPTY);
        $frequencies = array_count_values($body_words);

        $heading_text = '';
        if (preg_match_all('/<h[1-6]\b[^>]*>(.*?)<\/h[1-6]>/is', $content, $matches)) {
            $heading_text = implode(' ', $matches[1]);
        }
        $heading_text = $normalize($heading_text);

        $candidates = array();
        $count = count($title_words);

        // Generate only title-derived phrases. This prevents arbitrary body text
        // from becoming the focus keyphrase and keeps the result topic-focused.
        for ($i = 0; $i < $count; $i++) {
            for ($size = 1; $size <= 4 && $i + $size <= $count; $size++) {
                $phrase_words = array_slice($title_words, $i, $size);
                $phrase = implode(' ', $phrase_words);
                $score = 0;

                // Prefer useful 2-3 word phrases; allow 1 or 4 when clearly stronger.
                $score += $size === 2 ? 24 : ($size === 3 ? 22 : ($size === 4 ? 10 : 4));

                foreach ($phrase_words as $word) {
                    $score += min(8, isset($frequencies[$word]) ? $frequencies[$word] : 0);
                }

                // Strongest signal: the phrase itself occurs in the article title.
                $normalized_title = ' ' . implode(' ', $title_words) . ' ';
                if (strpos($normalized_title, ' ' . $phrase . ' ') !== false) {
                    $score += 30;
                }

                // Headings are stronger topical signals than ordinary body text.
                if ($heading_text !== '' && strpos(' ' . $heading_text . ' ', ' ' . $phrase . ' ') !== false) {
                    $score += 15;
                }

                // Avoid weak phrases made only from numbers.
                if (preg_match('/^[\p{N}\s]+$/u', $phrase)) {
                    $score -= 20;
                }

                // Prefer a phrase with a substantive word over a generic short phrase.
                if ($size === 1 && (function_exists('mb_strlen') ? mb_strlen($phrase, 'UTF-8') < 4 : strlen($phrase) < 4)) {
                    $score -= 6;
                }

                if (!isset($candidates[$phrase]) || $score > $candidates[$phrase]) {
                    $candidates[$phrase] = $score;
                }
            }
        }

        arsort($candidates, SORT_NUMERIC);
        return (string) key($candidates);
    }

    /**
     * Build a maximum-142-character description without cutting a word in half.
     */
    private function build_meta_description($content) {
        $paragraphs = array();
        if (preg_match_all('/<p\\b[^>]*>(.*?)<\\/p>/is', $content, $matches)) {
            foreach ($matches[1] as $paragraph) {
                $paragraph = trim(preg_replace('/\\s+/u', ' ', wp_strip_all_tags($paragraph)));
                if ($paragraph !== '') {
                    $paragraphs[] = $paragraph;
                }
            }
        }

        $text = isset($paragraphs[0]) ? $paragraphs[0] : trim(preg_replace('/\\s+/u', ' ', wp_strip_all_tags($content)));
        $length = preg_match_all('/./us', $text, $chars);
        if ($length === false || $length <= 142) {
            return $text;
        }

        $chars = $chars[0];
        $candidate = implode('', array_slice($chars, 0, 139));
        $candidate = preg_replace('/\\s+\\S*$/u', '', $candidate);
        return rtrim($candidate) . '...';
    }

}
