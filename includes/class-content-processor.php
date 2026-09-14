<?php
/** Content cleanup and transformation. */
if (!defined('ABSPATH')) exit;

class WTAI_Content_Processor {
    private $error_handler;

    public function __construct($error_handler = null) { $this->error_handler = $error_handler; }

    public function process($content, $settings) {
        try {
            if (!empty($settings['convert_numbered_to_dotted'])) {
                $content = preg_replace('/<ol\b([^>]*)>/i', '<ul$1>', $content);
                $content = preg_replace('/<\/ol>/i', '</ul>', $content);
            }
            if (!empty($settings['unbold_except_headings'])) {
                $content = preg_replace_callback('/<h([1-6])\b[^>]*>.*?<\/h\1>/is', function ($m) {
                    return '__WTAI_HEADING_' . base64_encode($m[0]) . '__';
                }, $content, -1, $count);
                $content = preg_replace('/<\/?(?:strong|b)\b[^>]*>/i', '', $content);
                $content = preg_replace_callback('/__WTAI_HEADING_([^_]+)__/', function ($m) {
                    return base64_decode($m[1]);
                }, $content);
            }
            $content = preg_replace('/<p>\s*(?:&nbsp;|\s)*<\/p>/i', '', $content);
            $content = preg_replace('/\n{3,}/', "\n\n", $content);
            return trim($content);
        } catch (Throwable $e) {
            if ($this->error_handler) $this->error_handler->log_error(WTAI_Error_Handler::ERR_CONTENT_PROCESSING_FAILED, array('error' => $e->getMessage()));
            return $content;
        }
    }

    public function extract_text($html) {
        return trim(preg_replace('/\s+/', ' ', html_entity_decode(wp_strip_all_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    public function extract_headings($html) {
        $headings = array();
        preg_match_all('/<h([1-6])\b[^>]*>(.*?)<\/h\1>/is', $html, $matches);
        foreach ($matches[2] as $i => $heading) {
            $headings[] = array('text' => trim(wp_strip_all_tags($heading)), 'level' => (int) $matches[1][$i]);
        }
        return $headings;
    }

    public function extract_list_items($html) {
        $items = array();
        preg_match_all('/<li\b[^>]*>(.*?)<\/li>/is', $html, $matches);
        foreach ($matches[1] as $item) $items[] = trim(wp_strip_all_tags($item));
        return $items;
    }
}
