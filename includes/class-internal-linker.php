<?php
/** Deterministic title/keyphrase links to published blog posts. */
if (!defined('ABSPATH')) exit;

class WTAI_Internal_Linker {
    private $report = array();

    public function get_report() { return $this->report; }

    public function insert_links($content, $source_id, $maximum) {
        $maximum = max(0, (int) $maximum);
        $this->report = array('requested' => $maximum, 'inserted' => 0, 'placements' => array(), 'message' => '');
        if (!$maximum || trim($content) === '') return $content;
        if (!class_exists('DOMDocument')) {
            $this->report['message'] = __('PHP DOM is required for automatic internal links.', 'wolf-forge-article-generator');
            return $content;
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument('1.0', 'UTF-8');
            $loaded = $document->loadHTML('<?xml encoding="UTF-8"><html><body><div id="wtai-link-root">' . $content . '</div></body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            $root = $document->getElementById('wtai-link-root');
            if (!$loaded || !$root) throw new RuntimeException('Article HTML could not be parsed.');
            $used = array((int) $source_id => true);
            foreach ($root->getElementsByTagName('a') as $link) {
                $url = $link->getAttribute('href');
                if (strpos($url, '//') === 0) $url = wp_parse_url(home_url('/'), PHP_URL_SCHEME) . ':' . $url;
                elseif (isset($url[0]) && $url[0] === '/') {
                    $home = wp_parse_url(home_url('/'));
                    $url = $home['scheme'] . '://' . $home['host'] . (isset($home['port']) ? ':' . $home['port'] : '') . $url;
                }
                $id = url_to_postid($url);
                if ($id) $used[(int) $id] = true;
            }
            $xpath = new DOMXPath($document);
            $nodes = iterator_to_array($xpath->query('.//text()[(ancestor::p or ancestor::li or ancestor::td or ancestor::th) and not(ancestor::a or ancestor::h1 or ancestor::h2 or ancestor::h3 or ancestor::h4 or ancestor::h5 or ancestor::h6 or ancestor::figure or ancestor::figcaption or ancestor::code or ancestor::pre or ancestor::script or ancestor::style or ancestor::textarea or ancestor::kbd or ancestor::samp or ancestor::svg or ancestor::math)]', $root));
            $text = implode("\n", array_map(function ($node) { return $node->nodeValue; }, $nodes));
            $candidates = $this->candidates($text, $source_id, $used);
            $blocks = array();
            foreach ($candidates as $candidate) {
                if (count($this->report['placements']) >= $maximum) break;
                if (isset($used[$candidate['id']])) continue;
                foreach ($nodes as $node) {
                    $block = $node->parentNode;
                    while ($block && !in_array(strtolower($block->nodeName), array('p', 'li', 'td', 'th'), true)) $block = $block->parentNode;
                    if (!$block || isset($blocks[spl_object_id($block)])) continue;
                    if (!preg_match($candidate['pattern'], $node->nodeValue, $match, PREG_OFFSET_CAPTURE)) continue;
                    // Recheck after querying: a target might have been unpublished during an import.
                    $target = get_post($candidate['id']);
                    if (!$target || $target->post_type !== 'post' || $target->post_status !== 'publish' || $target->post_password !== '') break;
                    $fragment = $document->createDocumentFragment();
                    $fragment->appendChild($document->createTextNode(substr($node->nodeValue, 0, $match[0][1])));
                    $link = $document->createElement('a');
                    $link->setAttribute('href', $candidate['url']);
                    $link->appendChild($document->createTextNode($match[0][0]));
                    $fragment->appendChild($link);
                    $fragment->appendChild($document->createTextNode(substr($node->nodeValue, $match[0][1] + strlen($match[0][0]))));
                    $node->parentNode->replaceChild($fragment, $node);
                    $blocks[spl_object_id($block)] = $block;
                    $used[$candidate['id']] = true;
                    $this->report['placements'][] = array('id' => $candidate['id'], 'anchor' => $match[0][0], 'url' => $candidate['url']);
                    break;
                }
            }
            $this->report['inserted'] = count($this->report['placements']);
            if ($this->report['inserted'] < $maximum) $this->report['message'] = __('Fewer unambiguous title/keyphrase matches were available in separate paragraphs. Existing links and protected content were preserved.', 'wolf-forge-article-generator');
            if (!$this->report['inserted']) return $content;
            $html = '';
            foreach ($root->childNodes as $node) $html .= $document->saveHTML($node);
            return $html;
        } catch (Throwable $error) {
            $this->report['inserted'] = 0;
            $this->report['placements'] = array();
            $this->report['message'] = __('Internal linking could not complete. The article was kept without new internal links.', 'wolf-forge-article-generator');
            return $content;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function candidates($text, $source_id, $used) {
        if (trim($text) === '') return array();
        $candidates = array();
        $page = 1;
        $home = wp_parse_url(home_url('/'));
        // ponytail: scan fresh published rows per import; cache a catalog if profiling shows a bottleneck.
        do {
            $query = new WP_Query(array('post_type' => 'post', 'post_status' => 'publish', 'has_password' => false, 'post__not_in' => array((int) $source_id), 'posts_per_page' => 200, 'paged' => $page++, 'orderby' => 'ID', 'order' => 'ASC', 'no_found_rows' => true, 'update_post_meta_cache' => true, 'update_post_term_cache' => false));
            foreach ($query->posts as $post) {
                if (isset($used[$post->ID]) || $post->post_type !== 'post' || $post->post_status !== 'publish' || $post->post_password !== '') continue;
                $url = get_permalink($post->ID);
                $parts = $url ? wp_parse_url($url) : false;
                if (!$parts || !in_array($parts['scheme'] ?? '', array('http', 'https'), true) || strcasecmp($parts['host'] ?? '', $home['host'] ?? '') !== 0 || ($parts['port'] ?? null) !== ($home['port'] ?? null)) continue;
                $title = trim(html_entity_decode(wp_strip_all_tags($post->post_title), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $phrases = array($title, get_post_meta($post->ID, '_yoast_wpseo_focuskw', true));
                $rank_math = get_post_meta($post->ID, 'rank_math_focus_keyword', true);
                if (is_string($rank_math)) $phrases = array_merge($phrases, explode(',', $rank_math));
                $phrases = array_map(function ($phrase) { return trim(html_entity_decode(wp_strip_all_tags($phrase), ENT_QUOTES | ENT_HTML5, 'UTF-8')); }, array_filter($phrases, 'is_string'));
                $partial_phrases = $this->title_phrases($title);
                foreach (array_unique(array_merge($phrases, $partial_phrases)) as $phrase) {
                    $words = preg_match_all('/\p{L}[\p{L}\p{N}]*/u', $phrase);
                    if ($words < 2) continue;
                    $pattern = '~(?<![\p{L}\p{N}_])' . implode('\s+', array_map(function ($part) { return preg_quote($part, '~'); }, preg_split('/\s+/u', $phrase))) . '(?![\p{L}\p{N}_])~iu';
                    if (preg_match($pattern, $text)) $candidates[] = array('id' => (int) $post->ID, 'url' => $url, 'pattern' => $pattern, 'words' => $words, 'phrase' => $phrase, 'partial' => !in_array($phrase, $phrases, true));
                }
            }
        } while (count($query->posts) === 200);
        // A shared title fragment is too ambiguous to pick a destination arbitrarily.
        $destinations = array();
        foreach ($candidates as $candidate) {
            $key = preg_replace('/\s+/u', ' ', $candidate['phrase']);
            $key = function_exists('mb_strtolower') ? mb_strtolower($key, 'UTF-8') : strtolower($key);
            $destinations[$key][$candidate['id']] = true;
        }
        $candidates = array_filter($candidates, function ($candidate) use ($destinations) {
            $key = preg_replace('/\s+/u', ' ', $candidate['phrase']);
            $key = function_exists('mb_strtolower') ? mb_strtolower($key, 'UTF-8') : strtolower($key);
            return !$candidate['partial'] || count($destinations[$key]) === 1;
        });
        usort($candidates, function ($a, $b) { return ($b['words'] <=> $a['words']) ?: ($a['partial'] <=> $b['partial']) ?: (strlen($b['phrase']) <=> strlen($a['phrase'])) ?: ($a['id'] <=> $b['id']) ?: strcmp($a['phrase'], $b['phrase']); });
        return $candidates;
    }

    private function title_phrases($title) {
        // English connective/question words must not become standalone topic anchors.
        $ignored = array_fill_keys(explode(' ', 'a an the and or but for nor so yet at by in of on to up as is it be are was were been being with from into onto over under about after before through during without within how what when where why which who whom whose can could should would will may might must do does did have has had i we you he she they me us them my our your his her its their this that these those best top guide tips'), true);
        $phrases = array();
        preg_match_all('/\p{L}[\p{L}\p{N}]*(?:\s+\p{L}[\p{L}\p{N}]*)+/u', $title, $runs);
        foreach ($runs[0] as $run) {
            $words = preg_split('/\s+/u', $run);
            for ($start = 0; $start < count($words) - 1; $start++) {
                for ($length = 2; $length <= 5 && $start + $length <= count($words); $length++) {
                    $slice = array_slice($words, $start, $length);
                    if (isset($ignored[strtolower($slice[0])]) || isset($ignored[strtolower($slice[$length - 1])])) continue;
                    $meaningful = array_filter($slice, function ($word) use ($ignored) { return !isset($ignored[strtolower($word)]); });
                    if (count($meaningful) >= 2) $phrases[] = implode(' ', $slice);
                }
            }
        }
        return array_unique($phrases);
    }
}
