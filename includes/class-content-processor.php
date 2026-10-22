<?php
/** Content cleanup and transformation. */
if (!defined('ABSPATH')) exit;

class WTAI_Content_Processor {
    private $error_handler;

    public function __construct($error_handler = null) { $this->error_handler = $error_handler; }

    public function prepare_document($content, $filename) {
        $title = trim(pathinfo($filename, PATHINFO_FILENAME));
        $title = preg_replace('/^(?:[0-9]+\.(?![0-9])|[0-9]+\)|\([0-9]+\)|\[[0-9]+\])\s*/u', '', $title);
        $title = trim(preg_replace('/[\s_-]+/u', ' ', $title));
        if ($title === '' || preg_match('/^\d+$/', $title)) {
            $title = preg_match('/<h([1-6])\b[^>]*>(.*?)<\/h\1>/is', $content, $heading) ? $this->extract_text($heading[2]) : '';
        }
        $title = wp_strip_all_tags($title ?: __('Imported Article', 'wolf-forge-article-generator'));
        $first = true;
        $content = preg_replace_callback('/<h1\b[^>]*>(.*?)<\/h1>/is', function ($heading) use (&$first) {
            $remove = $first;
            $first = false;
            return $remove ? '' : '<h2>' . $heading[1] . '</h2>';
        }, $content);
        return array('title' => $title, 'content' => $content);
    }

    public function process($content, $settings) {
        try {
            if (!empty($settings['convert_numbered_to_dotted'])) {
                $content = preg_replace('/<ol\b([^>]*)>/i', '<ul$1>', $content);
                $content = preg_replace('/<\/ol>/i', '</ul>', $content);
            }
            if (!empty($settings['unbold_except_headings'])) {
                $content = preg_replace_callback('/(<h[1-6]\b[^>]*>.*?<\/h[1-6]>)|(.+?)(?=<h[1-6]\b|$)/is', function ($part) {
                    return !empty($part[1]) ? $part[1] : preg_replace('/<\/?(?:strong|b)\b[^>]*>/i', '', $part[0]);
                }, $content);
            }
            $content = preg_replace('/<p>\s*(?:&nbsp;|\s)*<\/p>/i', '', $content);
            $content = preg_replace('/\n{3,}/', "\n\n", $content);
            if ($settings['auto_link_phone_numbers'] ?? true) $content = $this->link_phone_numbers($content);
            return trim($content);
        } catch (Throwable $e) {
            if ($this->error_handler) $this->error_handler->log_error(WTAI_Error_Handler::ERR_CONTENT_PROCESSING_FAILED, array('error' => $e->getMessage()));
            return $content;
        }
    }

    private function link_phone_numbers($content) {
        if (!class_exists('DOMDocument')) return $content;
        $separators = '[\h().\[\]\p{Pd}]';
        $international = '(?:\+\h*[1-9]|00\h*[1-9])(?:' . $separators . '*[0-9]){6,14}';
        // Require visible formatting for local NANP numbers to avoid linking bare numeric IDs.
        $local = '(?:1[\h.\p{Pd}]+)?(?:\([2-9][0-9]{2}\)\h*|[2-9][0-9]{2}[\h.\p{Pd}]+)[2-9][0-9]{2}[\h.\p{Pd}]+[0-9]{4}';
        $pattern = '~(?<![\p{L}\p{N}_+/:@#])(?:' . $international . '|' . $local . ')(?![\p{L}\p{N}_]|' . $separators . '*[0-9])~u';
        if (!preg_match($pattern, $content)) return $content;
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $document->loadHTML('<?xml encoding="UTF-8"><html><body><div id="wtai-phone-root">' . $content . '</div></body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            $root = $document->getElementById('wtai-phone-root');
            if (!$loaded || !$root) return $content;
            $xpath = new DOMXPath($document);
            $nodes = iterator_to_array($xpath->query('.//text()[not(ancestor::a or ancestor::script or ancestor::style or ancestor::code or ancestor::pre or ancestor::textarea or ancestor::kbd or ancestor::samp or ancestor::svg or ancestor::math)]', $root));
            $linked = false;
            foreach ($nodes as $node) {
                if (!preg_match_all($pattern, $node->nodeValue, $matches, PREG_OFFSET_CAPTURE)) continue;
                $fragment = $document->createDocumentFragment();
                $offset = 0;
                foreach ($matches[0] as $match) {
                    $fragment->appendChild($document->createTextNode(substr($node->nodeValue, $offset, $match[1] - $offset)));
                    $link = $document->createElement('a');
                    $link->setAttribute('href', 'tel:' . preg_replace('/[^+0-9]/', '', $match[0]));
                    $link->appendChild($document->createTextNode($match[0]));
                    $fragment->appendChild($link);
                    $offset = $match[1] + strlen($match[0]);
                }
                $fragment->appendChild($document->createTextNode(substr($node->nodeValue, $offset)));
                $node->parentNode->replaceChild($fragment, $node);
                $linked = true;
            }
            if (!$linked) return $content;
            $html = '';
            foreach ($root->childNodes as $node) $html .= $document->saveHTML($node);
            return $html;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    public function extract_text($html) {
        return trim(preg_replace('/\s+/', ' ', html_entity_decode(wp_strip_all_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }
}
