<?php
/** Deterministic title/keyphrase links to automatically discovered service targets and blog posts. */
if (!defined('ABSPATH')) exit;

class WTAI_Internal_Linker {
    private $report = array();

    public function get_report() { return $this->report; }

    public function insert_links($content, $source_id, $maximum) {
        $maximum = max(0, (int) $maximum);
        $this->report = array('requested' => $maximum, 'inserted' => 0, 'service_inserted' => 0, 'placements' => array(), 'message' => '');
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
                    $target_type = $candidate['type'] ?? 'post';
                    if (!$target || $target->post_type !== $target_type || $target->post_status !== 'publish' || $target->post_password !== '') break;
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
                    $this->report['placements'][] = array('id' => $candidate['id'], 'anchor' => $match[0][0], 'url' => $candidate['url'], 'service' => !empty($candidate['service']));
                    if (!empty($candidate['service'])) $this->report['service_inserted']++;
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

    /** Menu structure is independent of the WordPress page-parent hierarchy. */
    public static function service_targets() {
        return self::service_discovery()['targets'];
    }

    /** The dashboard and linker share the same discovery rules and eligible targets. */
    public static function service_discovery() {
        $pages = self::published_pages();
        $ids = $seeds = array();
        $roots = array();
        $menus = array_values(array_filter(get_nav_menu_locations()));
        // Elementor can use a WordPress menu without assigning a theme location.
        if (!$menus) foreach (wp_get_nav_menus() as $menu) $menus[] = (int) $menu->term_id;
        foreach (array_unique($menus) as $menu) {
            $items = wp_get_nav_menu_items($menu) ?: array();
            $children = $pending = array();
            foreach ($items as $item) {
                $children[(int) $item->menu_item_parent][] = $item;
                $id = self::navigation_target($item->object_id ?? 0, $item->object ?? '', $item->url ?? '');
                $post = $id ? get_post($id) : null;
                if (self::service_label($item->title) || ($post && self::service_label($post->post_title))) {
                    $roots['menu:' . $menu . ':' . $item->ID] = array('type' => 'menu', 'label' => self::clean_title($item->title ?: $post->post_title));
                    $pending[] = (int) $item->ID;
                    if ($post && $post->post_type === 'page') $seeds[$id] = true;
                }
            }
            $visited = array();
            while ($pending) {
                $parent = array_pop($pending);
                if (isset($visited[$parent])) continue;
                $visited[$parent] = true;
                foreach ($children[$parent] ?? array() as $item) {
                    $pending[] = (int) $item->ID;
                    $id = self::navigation_target($item->object_id ?? 0, $item->object ?? '', $item->url ?? '');
                    if ($id) { $ids[$id] = true; $seeds[$id] = true; }
                }
            }
        }
        // Block themes store navigation menus as published wp_navigation posts.
        foreach (get_posts(array('post_type' => 'wp_navigation', 'post_status' => 'publish', 'has_password' => false, 'numberposts' => -1)) as $navigation) {
            self::navigation_blocks(parse_blocks($navigation->post_content), $ids, $seeds, $roots);
        }
        foreach (array_keys($seeds) as $parent) foreach (self::descendants($parent, $pages) as $page) $ids[(int) $page->ID] = true;
        if (!$ids) {
            foreach ($pages as $page) {
                if ($page->post_password !== '' || !self::service_label($page->post_title)) continue;
                $roots['page:' . $page->ID] = array('type' => 'page', 'label' => self::clean_title($page->post_title));
                foreach (self::descendants($page->ID, $pages) as $child) $ids[(int) $child->ID] = true;
            }
        }
        $targets = array();
        foreach (array_keys($ids) as $id) {
            $post = get_post($id);
            if ($post && in_array($post->post_type, array('page', 'post'), true) && $post->post_status === 'publish' && $post->post_password === '') $targets[(int) $id] = $post;
        }
        ksort($targets, SORT_NUMERIC);
        return array('roots' => array_values($roots), 'targets' => $targets);
    }

    private static function service_label($label) {
        return (bool) preg_match('~(?<![\p{L}\p{N}_])services?(?![\p{L}\p{N}_])~iu', self::clean_title($label));
    }

    private static function navigation_target($id, $type, $url) {
        if (in_array($type, array('page', 'post'), true) && $id) $id = absint($id);
        else {
            $url = (string) $url;
            $home = wp_parse_url(home_url('/'));
            if (strpos($url, '//') === 0) $url = $home['scheme'] . ':' . $url;
            elseif (isset($url[0]) && $url[0] === '/') $url = $home['scheme'] . '://' . $home['host'] . (isset($home['port']) ? ':' . $home['port'] : '') . $url;
            $parts = wp_parse_url($url);
            if (!$parts || isset($parts['fragment']) || !in_array($parts['scheme'] ?? '', array('http', 'https'), true) || strcasecmp($parts['host'] ?? '', $home['host'] ?? '') !== 0 || ($parts['port'] ?? null) !== ($home['port'] ?? null)) return 0;
            $id = (int) url_to_postid($url);
        }
        $post = $id ? get_post($id) : null;
        return $post && in_array($post->post_type, array('page', 'post'), true) && $post->post_status === 'publish' && $post->post_password === '' ? $id : 0;
    }

    private static function navigation_blocks($blocks, &$ids, &$seeds, &$roots, $inside = false) {
        foreach ($blocks as $block) {
            $attrs = $block['attrs'] ?? array();
            $id = self::navigation_target($attrs['id'] ?? 0, $attrs['type'] ?? '', $attrs['url'] ?? '');
            $post = $id ? get_post($id) : null;
            $root = in_array($block['blockName'], array('core/navigation-link', 'core/navigation-submenu'), true)
                && (self::service_label($attrs['label'] ?? '') || ($post && self::service_label($post->post_title)));
            if ($root) $roots[] = array('type' => 'block_menu', 'label' => self::clean_title(($attrs['label'] ?? '') ?: $post->post_title));
            if ($inside && $id) { $ids[$id] = true; $seeds[$id] = true; }
            if ($root && $post && $post->post_type === 'page') $seeds[$id] = true;
            self::navigation_blocks($block['innerBlocks'] ?? array(), $ids, $seeds, $roots, $inside || $root);
        }
    }

    private function candidates($text, $source_id, $used) {
        if (trim($text) === '') return array();
        $candidates = array();
        $home = wp_parse_url(home_url('/'));
        $service_targets = self::service_targets();
        $this->report['service_targets'] = count($service_targets);
        foreach ($service_targets as $target) {
            if (!isset($used[$target->ID])) $this->append_candidates($candidates, $target, $text, $home, true);
        }
        $page = 1;
        // ponytail: scan fresh published rows per import; cache a catalog if profiling shows a bottleneck.
        do {
            $query = new WP_Query(array('post_type' => 'post', 'post_status' => 'publish', 'has_password' => false, 'post__not_in' => array((int) $source_id), 'posts_per_page' => 200, 'paged' => $page++, 'orderby' => 'ID', 'order' => 'ASC', 'no_found_rows' => true, 'update_post_meta_cache' => true, 'update_post_term_cache' => false));
            foreach ($query->posts as $post) {
                if (isset($used[$post->ID]) || $post->post_type !== 'post' || $post->post_status !== 'publish' || $post->post_password !== '') continue;
                $this->append_candidates($candidates, $post, $text, $home, false);
            }
        } while (count($query->posts) === 200);
        // A shared title fragment is too ambiguous to pick a destination arbitrarily.
        $destinations = array();
        foreach ($candidates as $candidate) {
            $key = preg_replace('/\s+/u', ' ', $candidate['phrase']);
            $key = function_exists('mb_strtolower') ? mb_strtolower($key, 'UTF-8') : strtolower($key);
            $group = !empty($candidate['service']) ? 'service' : 'post';
            $destinations[$key][$group][$candidate['id']] = true;
        }
        $candidates = array_filter($candidates, function ($candidate) use ($destinations) {
            $key = preg_replace('/\s+/u', ' ', $candidate['phrase']);
            $key = function_exists('mb_strtolower') ? mb_strtolower($key, 'UTF-8') : strtolower($key);
            $group = !empty($candidate['service']) ? 'service' : 'post';
            return (!$candidate['partial'] && $group === 'post') || count($destinations[$key][$group] ?? array()) === 1;
        });
        usort($candidates, function ($a, $b) { return ((int) empty($a['service']) <=> (int) empty($b['service'])) ?: ($b['words'] <=> $a['words']) ?: ($a['partial'] <=> $b['partial']) ?: (strlen($b['phrase']) <=> strlen($a['phrase'])) ?: ($a['id'] <=> $b['id']) ?: strcmp($a['phrase'], $b['phrase']); });
        return $candidates;
    }

    private function append_candidates(&$candidates, $post, $text, $home, $service) {
        $url = get_permalink($post->ID);
        $parts = $url ? wp_parse_url($url) : false;
        if (!$parts || !in_array($parts['scheme'] ?? '', array('http', 'https'), true) || strcasecmp($parts['host'] ?? '', $home['host'] ?? '') !== 0 || ($parts['port'] ?? null) !== ($home['port'] ?? null)) return;
        $title = self::clean_title($post->post_title ?? '');
        $phrases = array($title, get_post_meta($post->ID, '_yoast_wpseo_focuskw', true));
        $rank_math = get_post_meta($post->ID, 'rank_math_focus_keyword', true);
        if (is_string($rank_math)) $phrases = array_merge($phrases, explode(',', $rank_math));
        $phrases = array_map(function ($phrase) { return trim(html_entity_decode(wp_strip_all_tags($phrase), ENT_QUOTES | ENT_HTML5, 'UTF-8')); }, array_filter($phrases, 'is_string'));
        $partial_phrases = $this->title_phrases($title);
        foreach (array_unique(array_merge($phrases, $partial_phrases)) as $phrase) {
            $words = count(self::words($phrase));
            if ($words < 2) continue;
            $pattern = self::phrase_pattern($phrase, $service);
            if ($pattern !== '' && preg_match($pattern, $text)) {
                $candidates[] = array(
                    'id' => (int) $post->ID,
                    'url' => $url,
                    'pattern' => $pattern,
                    'words' => $words,
                    'phrase' => $phrase,
                    'partial' => !in_array($phrase, $phrases, true),
                    'type' => $post->post_type,
                    'service' => $service,
                );
            }
        }
    }

    private static function phrase_pattern($phrase, $allow_variations) {
        $words = self::words($phrase);
        if (count($words) < 2) return '';
        $last = count($words) - 1;
        $parts = array();
        foreach ($words as $index => $word) {
            $part = preg_quote($word, '~');
            if ($allow_variations && $index === $last) $part = self::plural_pattern($word);
            $parts[] = $part;
        }
        return '~(?<![\p{L}\p{N}_])' . implode('[\s\p{P}\p{S}]+', $parts) . '(?![\p{L}\p{N}_])~iu';
    }

    private static function plural_pattern($word) {
        if (preg_match('/[^aeiou]y$/i', $word)) {
            $root = preg_quote(substr($word, 0, -1), '~');
            return $root . '(?:y|ies)';
        }
        if (preg_match('/ies$/i', $word)) {
            return preg_quote(substr($word, 0, -3), '~') . '(?:y|ies)';
        }
        if (preg_match('/(?:ches|shes|xes|zes|sses)$/i', $word)) {
            return '(?:' . preg_quote($word, '~') . '|' . preg_quote(substr($word, 0, -2), '~') . ')';
        }
        if (preg_match('/s$/i', $word) && !preg_match('/(?:ss|us|is)$/i', $word)) {
            return '(?:' . preg_quote($word, '~') . '|' . preg_quote(substr($word, 0, -1), '~') . ')';
        }
        return '(?:' . preg_quote($word, '~') . '|' . preg_quote($word . 's', '~') . '|' . preg_quote($word . 'es', '~') . ')';
    }

    private static function words($text) {
        $text = self::clean_title($text);
        preg_match_all('/\p{L}[\p{L}\p{N}]*/u', $text, $matches);
        return $matches[0] ?? array();
    }

    private static function clean_title($title) {
        return trim(html_entity_decode(wp_strip_all_tags((string) $title), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private static function published_pages() {
        return get_pages(array('post_status' => 'publish', 'sort_column' => 'ID', 'sort_order' => 'ASC'));
    }

    private static function descendants($parent_id, $pages) {
        $children = array();
        foreach ((array) $pages as $page) {
            $children[(int) ($page->post_parent ?? 0)][] = $page;
        }
        $result = array();
        $pending = array((int) $parent_id);
        while ($pending) {
            $current = array_pop($pending);
            foreach ($children[$current] ?? array() as $child) {
                if ((int) $child->ID === (int) $parent_id || isset($result[(int) $child->ID])) continue;
                if ($child->post_status !== 'publish' || $child->post_password !== '') continue;
                $result[(int) $child->ID] = $child;
                $pending[] = (int) $child->ID;
            }
        }
        return array_values($result);
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
