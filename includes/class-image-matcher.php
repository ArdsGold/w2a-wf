<?php
/** Deterministic media scoring and word-percentage placement. */
if (!defined('ABSPATH')) exit;

class WTAI_Image_Matcher {
    private const MIN_IMAGE_WORD_GAP = 100;
    private $error_handler;
    private $report = array();

    public function __construct($error_handler = null) { $this->error_handler = $error_handler; }
    public function get_report() { return $this->report; }

    /** Transient admin markup only: Google URLs and photo metadata never enter post storage. */
    public function result_thumbnails($content) {
        if (!class_exists('DOMDocument')) return '';
        try {
            list($document, $root) = $this->fragment($content);
            $items = '';
            $label = __('Open full image in a new tab', 'wolf-forge-article-generator');
            foreach ($root->getElementsByTagName('figure') as $figure) {
                if (!preg_match('/(?:^|\s)wtai-inserted-image(?:\s|$)/', $figure->getAttribute('class'))) continue;
                $html = '';
                $image = $figure->getElementsByTagName('img')->item(0);
                if ($image && preg_match('/\bwp-image-(\d+)\b/', $image->getAttribute('class'), $match)) {
                    $id = (int) $match[1];
                    $url = wp_get_attachment_image_url($id, 'full');
                    $thumbnail = wp_get_attachment_image($id, 'thumbnail', false, array('loading' => 'lazy', 'class' => 'wtai-result-thumbnail'));
                    if ($url && $thumbnail) $html = '<a href="' . esc_url($url) . '" target="_blank" rel="noopener" title="' . esc_attr($label) . '" aria-label="' . esc_attr($label) . '">' . $thumbnail . '</a>';
                } elseif (preg_match('/\[wtai_gbp_photo place="([A-Za-z0-9_-]{10,200})" slot="([0-9])"\]/', $figure->textContent, $match)) {
                    $google = WTAI_GBP_Photos::shortcode(array('place' => $match[1], 'slot' => $match[2]));
                    if ($google !== '') {
                        list($google_document, $google_root) = $this->fragment($google);
                        $photo = $google_root->getElementsByTagName('img')->item(0);
                        if ($photo && $photo->parentNode instanceof DOMElement && $photo->parentNode->tagName === 'a') {
                            $photo->parentNode->setAttribute('href', $photo->getAttribute('src'));
                            $photo->parentNode->setAttribute('title', $label);
                            $photo->parentNode->setAttribute('aria-label', $label);
                            foreach ($google_root->childNodes as $node) $html .= $google_document->saveHTML($node);
                        }
                    }
                }
                if ($html === '') $html = '<span class="wtai-result-thumbnail-unavailable">' . esc_html__('Image unavailable', 'wolf-forge-article-generator') . '</span>';
                $items .= '<li>' . $html . '</li>';
            }
            return $items === '' ? '' : wp_kses_post('<ul class="wtai-result-images" aria-label="' . esc_attr__('Inserted images', 'wolf-forge-article-generator') . '">' . $items . '</ul>');
        } catch (Throwable $error) {
            return ''; // Preview failures must not change the import outcome.
        }
    }

    public function insert_images($content, $settings, $title = '', $allow_downloads = false) {
        $requested = max(0, (int) ($settings['max_images_per_post'] ?? 3));
        $this->report = array('requested' => $requested, 'inserted' => 0, 'placements' => array(), 'message' => '');
        if (!$requested || trim($content) === '') return $content;
        if (!class_exists('DOMDocument')) {
            $this->report['message'] = __('PHP DOM is required for image placement. The article was kept without new images.', 'wolf-forge-article-generator');
            return $content;
        }
        try {
            list($document, $root) = $this->fragment($content);
            $anchors = array();
            $body_text = array();
            $total = 0;
            $headings = array();
            $excluded = array();
            $source = $settings['image_source'] ?? 'media_library';
            $google = null;
            $google_message = '';
            foreach ($root->getElementsByTagName('img') as $image) {
                if (preg_match('/\bwp-image-(\d+)\b/', $image->getAttribute('class'), $match)) $excluded[(int) $match[1]] = true;
            }
            foreach ($root->childNodes as $node) {
                if ($node instanceof DOMElement && strtolower($node->tagName) === 'figure') continue;
                if ($node instanceof DOMElement && preg_match('/^h([1-6])$/i', $node->tagName, $level)) {
                    // Spread the full requested count over the eligible body, before Conclusion.
                    if (preg_match('/^\s*Conclusion\s*[:.?!]?\s*$/iu', $node->textContent)) break;
                    foreach (array_keys($headings) as $depth) if ($depth >= (int) $level[1]) unset($headings[$depth]);
                    $headings[(int) $level[1]] = $node->textContent;
                }
                $body_text[] = $node->textContent;
                $words = preg_match_all('/[\p{L}\p{N}]+/u', $node->textContent);
                $total += $words;
                if ($words && $node instanceof DOMElement && in_array(strtolower($node->tagName), array('p', 'ul', 'ol', 'table', 'blockquote', 'pre', 'div'), true)) {
                    $local_terms = array();
                    WTAI_Media_Index::add_terms($local_terms, $node->textContent);
                    WTAI_Media_Index::add_terms($local_terms, implode(' ', $headings), 2);
                    $anchors[] = array('node' => $node, 'words' => $total, 'terms' => $local_terms);
                }
            }
            if (!$anchors) {
                $this->report['message'] = __('No safe paragraph or block boundaries were available for images.', 'wolf-forge-article-generator');
                return $content;
            }
            if (in_array($source, array('gbp', 'both'), true)) {
                try { $google = WTAI_GBP_Photos::profile($settings['gbp_profile_url'] ?? ''); }
                catch (Throwable $error) { $google_message = $error->getMessage(); }
            }
            $index = $source === 'gbp' ? array('images' => array(), 'df' => array(), 'count' => 0) : WTAI_Media_Index::get();
            $terms = array();
            WTAI_Media_Index::add_terms($terms, implode(' ', $body_text));
            WTAI_Media_Index::add_terms($terms, $title, 4);
            foreach (array('h1', 'h2', 'h3', 'h4', 'h5', 'h6') as $tag) {
                foreach ($root->getElementsByTagName($tag) as $heading) WTAI_Media_Index::add_terms($terms, $heading->textContent, 2);
            }
            $query = WTAI_Media_Index::vector($terms, $index);
            $ranked = array();
            foreach ($index['images'] as $id => $weights) {
                if (isset($excluded[$id])) continue;
                $vector = WTAI_Media_Index::vector($weights, $index);
                $score = $this->similarity($query, $vector);
                if ($score < 0.08) continue;
                $primary = $index['primary'][$id];
                $title_vector = WTAI_Media_Index::vector($primary['title'], $index);
                $alt_vector = WTAI_Media_Index::vector($primary['alt'], $index);
                if ($this->similarity($query, $title_vector) < 0.08 || $this->similarity($query, $alt_vector) < 0.08) continue;
                $ranked[] = array('id' => (int) $id, 'vector' => $vector, 'title_vector' => $title_vector, 'alt_vector' => $alt_vector, 'score' => $score);
            }
            usort($ranked, function ($a, $b) { return ($b['score'] <=> $a['score']) ?: ($a['id'] <=> $b['id']); });
            if ($google) {
                foreach ($google['photos'] as $slot => $photo) {
                    $placeholder = WTAI_GBP_Photos::placeholder($google['id'], $slot);
                    if (strpos($content, $placeholder) !== false) continue;
                    $ranked[] = array('id' => 'gbp:' . $slot, 'source' => 'gbp', 'html' => $placeholder, 'vector' => array(), 'score' => 0, 'title' => sprintf(__('Google Business Profile photo %d', 'wolf-forge-article-generator'), $slot + 1));
                }
            }
            foreach ($anchors as &$anchor) $anchor['vector'] = WTAI_Media_Index::vector($anchor['terms'], $index);
            unset($anchor);
            $spacing_limit = 1 + intdiv($anchors[count($anchors) - 1]['words'] - $anchors[0]['words'], self::MIN_IMAGE_WORD_GAP);
            $capacity = min($requested, count($anchors), $spacing_limit);
            $plan = $this->best_plan($anchors, $ranked, $capacity, $total);
            $web_message = '';
            if ($allow_downloads && !empty($settings['pixabay_fallback']) && $source !== 'gbp' && count($plan) < $capacity) {
                $local_images = $ranked;
                $local_plan = $plan;
                try { $web_message = $this->fill_from_pixabay($anchors, $ranked, $plan, $capacity, $total, $terms, $index, $excluded); }
                catch (Throwable $error) {
                    $ranked = $local_images;
                    $plan = $local_plan;
                    $web_message = __('Pixabay fallback failed. Available local images were preserved.', 'wolf-forge-article-generator');
                }
            }
            $count = count($plan);
            foreach ($plan as $slot => $placement) {
                $position = $placement['position'];
                $image = $ranked[$placement['image']];
                $figure = $document->createElement('figure');
                $figure->setAttribute('class', 'wp-block-image wtai-inserted-image');
                list($image_doc, $image_root) = $this->fragment($image['html']);
                foreach ($image_root->childNodes as $image_node) $figure->appendChild($document->importNode($image_node, true));
                $is_google = ($image['source'] ?? '') === 'gbp';
                $caption = $is_google ? '' : get_post_field('post_excerpt', $image['id']);
                if ($caption !== '') {
                    $caption_node = $document->createElement('figcaption');
                    $caption_node->appendChild($document->createTextNode(html_entity_decode(wp_strip_all_tags($caption), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
                    $figure->appendChild($caption_node);
                }
                $credit = $is_google ? null : get_post_meta($image['id'], '_wtai_pixabay_credit', true);
                if (is_array($credit) && WTAI_Pixabay::allowed_url($credit['page'] ?? '')) {
                    $link = $document->createElement('a', 'Pixabay');
                    $link->setAttribute('href', $credit['page']);
                    $link->setAttribute('rel', 'noopener');
                    $credit_node = $caption !== '' ? $caption_node : $document->createElement('figcaption');
                    if ($caption !== '') $credit_node->appendChild($document->createTextNode(' '));
                    $credit_node->appendChild($document->createTextNode(__('Source: ', 'wolf-forge-article-generator')));
                    $credit_node->appendChild($link);
                    if ($caption === '') $figure->appendChild($credit_node);
                }
                $root->insertBefore($figure, $anchors[$position]['node']->nextSibling);
                $matched = array_intersect_key($anchors[$position]['vector'], $image['vector']);
                arsort($matched, SORT_NUMERIC);
                $this->report['placements'][] = array(
                    'id' => $image['id'], 'title' => $is_google ? $image['title'] : get_the_title($image['id']),
                    'target_percent' => round(100 * ($slot + 1) / $count, 1),
                    'actual_percent' => round(100 * $anchors[$position]['words'] / max(1, $total), 1),
                    'terms' => array_slice(array_keys($matched), 0, 8),
                );
            }
            $this->report['inserted'] = $count;
            if ($count < $requested) $this->report['message'] = __('Fewer images were inserted: matching images and safe positions at least 100 words apart are required before Conclusion.', 'wolf-forge-article-generator');
            if ($source === 'gbp' && $count < $requested) $this->report['message'] = __('Fewer Google photos or safe positions at least 100 words apart were available before Conclusion.', 'wolf-forge-article-generator');
            if ($source !== 'media_library') $this->report['message'] = trim($this->report['message'] . ' ' . $google_message . ' ' . __('GBP photos use the profile photo order, without keyword matching. They load live when the article is viewed.', 'wolf-forge-article-generator'));
            $this->report['message'] = trim($this->report['message'] . ' ' . $web_message);
            if (!$count) return $content;
            $html = '';
            foreach ($root->childNodes as $node) $html .= $document->saveHTML($node);
            return $html;
        } catch (Throwable $error) {
            $this->report['inserted'] = 0;
            $this->report['placements'] = array();
            $this->report['message'] = __('Image matching failed. The article was kept without new images.', 'wolf-forge-article-generator');
            if ($this->error_handler) $this->error_handler->log_error(WTAI_Error_Handler::ERR_IMAGE_MATCHING_FAILED, array('error' => $error->getMessage()));
            return $content;
        }
    }

    private function best_plan($anchors, &$images, $maximum, $total) {
        for ($count = min($maximum, count($images)); $count > 0; $count--) {
            $plan = $this->plan_placements($anchors, $images, $count, $total);
            if (count($plan) === $count) return $plan;
        }
        return array();
    }

    private function fill_from_pixabay($anchors, &$images, &$plan, $capacity, $total, $terms, $index, $excluded) {
        $original_count = count($images);
        $maximum = min($capacity, count($plan) + WTAI_Pixabay::MAX_DOWNLOADS);
        $queries = array();
        // Weighted article terms, then the sections that lack a local image. No AI or stopword list.
        $vocabularies = array($terms);
        $used_positions = array_column($plan, 'position');
        foreach ($anchors as $position => $anchor) if (!in_array($position, $used_positions, true)) $vocabularies[] = $anchor['terms'];
        foreach ($vocabularies as $vocabulary) {
            $weights = WTAI_Media_Index::vector($vocabulary, $index);
            arsort($weights, SORT_NUMERIC);
            $words = array_filter(array_keys($weights), function ($term) { return strlen($term) >= 3; });
            $query = implode(' ', array_slice($words, 0, 3));
            if ($query !== '') $queries[$query] = true;
            if (count($queries) === 3) break;
        }
        $seen = array();
        foreach (array_merge(array_keys($index['images']), array_keys($excluded)) as $id) {
            $provider_id = get_post_meta($id, '_wtai_pixabay_id', true);
            if ($provider_id) $seen[(int) $provider_id] = true;
        }
        $query_vector = WTAI_Media_Index::vector($terms, $index);
        $message = '';
        $trial = $plan;
        foreach (array_keys($queries) as $query) {
            $photos = WTAI_Pixabay::search($query);
            if (is_wp_error($photos)) { $message = $photos->get_error_message(); break; }
            foreach ($photos as $photo) {
                if (isset($seen[$photo['id']])) continue;
                $seen[$photo['id']] = true;
                $metadata = array();
                WTAI_Media_Index::add_terms($metadata, $photo['tags']);
                if (count(array_intersect_key($metadata, $terms)) < 2) continue;
                $vector = WTAI_Media_Index::vector($metadata, $index);
                $score = $this->similarity($query_vector, $vector);
                if ($score < 0.08) continue;
                // A placeholder lets the same planner check usefulness before any file is downloaded.
                $images[] = array('id' => 'pixabay:' . $photo['id'], 'source' => 'pixabay', 'photo' => $photo, 'html' => 'pending', 'vector' => $vector, 'title_vector' => $vector, 'alt_vector' => $vector, 'score' => $score);
            }
            $trial = $this->best_plan($anchors, $images, $maximum, $total);
            if (count($trial) === $maximum) break;
        }
        $downloaded = array();
        $attempts = 0;
        if (count($trial) > count($plan)) {
            foreach ($trial as $placement) {
                $candidate = $images[$placement['image']];
                if (($candidate['source'] ?? '') !== 'pixabay') continue;
                if ($attempts++ >= WTAI_Pixabay::MAX_DOWNLOADS) break;
                $id = WTAI_Pixabay::download($candidate['photo']);
                if (is_wp_error($id)) { $message = $id->get_error_message(); continue; }
                if (isset($excluded[$id])) continue;
                // Respect metadata edited by the user on an attachment recovered from an earlier import.
                $title_terms = $alt_terms = array();
                WTAI_Media_Index::add_terms($title_terms, get_the_title($id));
                WTAI_Media_Index::add_terms($alt_terms, get_post_meta($id, '_wp_attachment_image_alt', true));
                $candidate['id'] = $id;
                $candidate['title_vector'] = WTAI_Media_Index::vector($title_terms, $index);
                $candidate['alt_vector'] = WTAI_Media_Index::vector($alt_terms, $index);
                unset($candidate['html'], $candidate['photo']);
                $downloaded[$id] = $candidate;
            }
        }
        $images = array_merge(array_slice($images, 0, $original_count), array_values($downloaded));
        $plan = $this->best_plan($anchors, $images, $capacity, $total);
        $this->report['pixabay_downloaded'] = count($downloaded);
        if ($downloaded) $message = sprintf(__('Pixabay: %d matching photos saved or reused in Media Library.', 'wolf-forge-article-generator'), count($downloaded)) . ' ' . $message;
        elseif ($message === '') $message = __('Pixabay found no additional matching photos that improved safe placement.', 'wolf-forge-article-generator');
        return trim($message);
    }

    private function plan_placements($anchors, &$images, $count, $total) {
        $matches = array();
        foreach ($images as $key => &$image) {
            foreach ($anchors as $position => $anchor) {
                $is_google = ($image['source'] ?? '') === 'gbp';
                $title_score = $is_google ? 0.08 : $this->similarity($anchor['vector'], $image['title_vector']);
                $alt_score = $is_google ? 0.08 : $this->similarity($anchor['vector'], $image['alt_vector']);
                if ($title_score < 0.08 || $alt_score < 0.08) continue;
                if (!isset($image['html'])) $image['html'] = wp_get_attachment_image($image['id'], 'large', false, array('loading' => 'lazy', 'class' => 'wp-image-' . $image['id'] . ' size-large'));
                if (!$image['html']) break;
                $matches[$key][$position] = $title_score + $alt_score + 0.25 * $image['score'];
            }
        }
        unset($image);
        $plan = array();
        $used = array();
        $previous = -1;
        $last_words = $anchors[count($anchors) - 1]['words'];
        for ($slot = 1; $slot <= $count; $slot++) {
            $target = $total * $slot / $count;
            $best = null;
            $last = count($anchors) - ($count - $slot) - 1;
            foreach ($matches as $key => $positions) {
                if (isset($used[$key])) continue;
                // Place images with earlier section boundaries before their topic ends.
                $deadline = max(array_keys($positions));
                foreach ($positions as $position => $score) {
                    if ($position <= $previous || $position > $last) continue;
                    if ($previous >= 0 && $anchors[$position]['words'] - $anchors[$previous]['words'] < self::MIN_IMAGE_WORD_GAP) continue;
                    // Leave enough article text for the remaining images as well.
                    if ($last_words - $anchors[$position]['words'] < ($count - $slot) * self::MIN_IMAGE_WORD_GAP) continue;
                    $distance = abs($anchors[$position]['words'] - $target);
                    $priority = array($deadline, ($images[$key]['source'] ?? '') === 'pixabay' ? 1 : 0, $distance, -$score, $images[$key]['id']);
                    if (!$best || $priority < $best['priority']) $best = array('position' => $position, 'image' => $key, 'priority' => $priority);
                }
            }
            if (!$best) break;
            $plan[] = $best;
            $used[$best['image']] = true;
            $previous = $best['position'];
        }
        return $plan;
    }

    private function similarity($left, $right) {
        $score = 0;
        foreach ($left as $term => $weight) if (isset($right[$term])) $score += $weight * $right[$term];
        return $score;
    }

    private function fragment($html) {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $document->loadHTML('<?xml encoding="UTF-8"><html><body><div id="wtai-image-root">' . $html . '</div></body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            $root = $document->getElementById('wtai-image-root');
            if (!$loaded || !$root) throw new RuntimeException('Article HTML could not be parsed.');
            return array($document, $root);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    public function get_media_images($search = '', $page = 1, $per_page = 20) {
        $per_page = min(50, max(1, absint($per_page)));
        $args = array('post_type' => 'attachment', 'post_mime_type' => 'image', 'post_status' => 'inherit', 'posts_per_page' => $per_page, 'paged' => max(1, absint($page)), 'orderby' => 'ID', 'order' => 'DESC');
        if (trim($search) !== '') {
            $index = WTAI_Media_Index::get();
            $terms = array();
            WTAI_Media_Index::add_terms($terms, $search);
            $query_vector = WTAI_Media_Index::vector($terms, $index);
            $scores = array();
            foreach ($index['images'] as $id => $vector) {
                $score = $this->similarity($query_vector, WTAI_Media_Index::vector($vector, $index));
                if ($score > 0) $scores[$id] = $score;
            }
            if (!$scores) return array('images' => array(), 'total' => 0, 'pages' => 0, 'page' => $args['paged']);
            arsort($scores, SORT_NUMERIC);
            $args['post__in'] = array_keys($scores);
            $args['orderby'] = 'post__in';
        }
        $query = new WP_Query($args);
        $images = array();
        foreach ($query->posts as $post) {
            $images[] = array(
                'id' => $post->ID, 'title' => $post->post_title, 'description' => wp_strip_all_tags($post->post_content),
                'caption' => wp_strip_all_tags($post->post_excerpt), 'url' => wp_get_attachment_url($post->ID),
                'thumbnail' => wp_get_attachment_image_url($post->ID, 'thumbnail'),
                'alt' => get_post_meta($post->ID, '_wp_attachment_image_alt', true),
                'edit_url' => get_edit_post_link($post->ID, 'raw'),
            );
        }
        return array('images' => $images, 'total' => (int) $query->found_posts, 'pages' => (int) $query->max_num_pages, 'page' => $args['paged']);
    }
}
