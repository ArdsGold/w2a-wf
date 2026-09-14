<?php
/** Media library matching and insertion. */
if (!defined('ABSPATH')) exit;

class WTAI_Image_Matcher {
    private $error_handler;
    private $matched_images = array();

    public function __construct($error_handler = null) { $this->error_handler = $error_handler; }

    public function insert_images($content, $settings) {
        $this->matched_images = array();
        $max = min(10, max(0, absint(isset($settings['max_images_per_post']) ? $settings['max_images_per_post'] : 3)));
        if ($max < 1 || trim($content) === '') return $content;
        $method = in_array(isset($settings['image_matching_method']) ? $settings['image_matching_method'] : 'title', array('title','description','both'), true)
            ? $settings['image_matching_method'] : 'title';

        $headings = $this->extract_headings($content);
        $insertions = array();
        foreach ($headings as $heading) {
            if (count($insertions) >= $max) break;
            $image = $this->find_matching_image($heading['text'], $method);
            if ($image) $insertions[] = array('needle' => $heading['full'], 'html' => $this->generate_image_html($image));
        }

        if (count($insertions) < $max) {
            $paragraph = $this->extract_first_paragraph($content);
            if ($paragraph) {
                $image = $this->find_matching_image($paragraph['text'], $method);
                if ($image) $insertions[] = array('needle' => $paragraph['full'], 'html' => $this->generate_image_html($image));
            }
        }

        // Replace from the end so offsets remain stable.
        foreach (array_reverse($insertions) as $insertion) {
            $pos = strpos($content, $insertion['needle']);
            if ($pos !== false) {
                $pos += strlen($insertion['needle']);
                $content = substr_replace($content, "\n" . $insertion['html'], $pos, 0);
            }
        }
        return $content;
    }

    private function extract_headings($content) {
        $headings = array();
        preg_match_all('/<h([1-6])\b[^>]*>(.*?)<\/h\1>/is', $content, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $headings[] = array('text' => trim(wp_strip_all_tags($match[2])), 'level' => (int) $match[1], 'full' => $match[0]);
        }
        return $headings;
    }

    private function extract_first_paragraph($content) {
        if (!preg_match('/<p\b[^>]*>(.*?)<\/p>/is', $content, $match)) return false;
        return array('text' => trim(wp_strip_all_tags($match[1])), 'full' => $match[0]);
    }

    private function find_matching_image($search_text, $method) {
        $search_text = trim(html_entity_decode(wp_strip_all_tags($search_text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if (strlen($search_text) < 3) return false;

        $terms = $this->keywords($search_text);
        if (!$terms) return false;

        $query = new WP_Query(array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => 50,
            'post__not_in' => $this->matched_images,
            's' => implode(' ', array_slice($terms, 0, 8)),
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => true,
            'update_post_meta_cache' => true,
            'update_post_term_cache' => false,
        ));

        $best = false;
        $best_score = 0;
        foreach ($query->posts as $post) {
            $title = strtolower((string) $post->post_title);
            $description = strtolower((string) $post->post_content);
            $haystacks = $method === 'title' ? array($title) : ($method === 'description' ? array($description) : array($title, $description));
            $score = 0;
            foreach ($terms as $term) {
                foreach ($haystacks as $haystack) {
                    if (strpos($haystack, $term) !== false) $score += strlen($term) >= 5 ? 3 : 1;
                }
            }
            if ($score > $best_score) {
                $best_score = $score;
                $best = $post;
            }
        }

        if (!$best || $best_score < 1) return false;
        $this->matched_images[] = $best->ID;
        return array(
            'id' => $best->ID,
            'title' => $best->post_title,
            'caption' => $best->post_excerpt,
            'url' => wp_get_attachment_image_url($best->ID, 'large') ?: wp_get_attachment_url($best->ID),
            'alt' => get_post_meta($best->ID, '_wp_attachment_image_alt', true),
        );
    }

    private function keywords($text) {
        $stop = array('about','after','again','also','because','before','being','between','could','from','have','into','more','other','over','that','their','there','these','they','this','through','using','what','when','where','which','with','your');
        $words = preg_split('/[^\p{L}\p{N}]+/u', strtolower($text), -1, PREG_SPLIT_NO_EMPTY);
        $terms = array();
        foreach ($words as $word) {
            if (strlen($word) >= 4 && !in_array($word, $stop, true)) $terms[$word] = true;
        }
        return array_keys($terms);
    }

    private function generate_image_html($image) {
        $alt = !empty($image['alt']) ? $image['alt'] : $image['title'];
        $html = '<figure class="wtai-inserted-image" style="margin:20px 0;text-align:center;">';
        $html .= '<img src="' . esc_url($image['url']) . '" alt="' . esc_attr($alt) . '" class="size-large" loading="lazy" />';
        if (!empty($image['caption'])) $html .= '<figcaption>' . esc_html($image['caption']) . '</figcaption>';
        return $html . '</figure>\n';
    }

    public function get_media_images($search = '', $page = 1, $per_page = 20) {
        $query = new WP_Query(array(
            'post_type' => 'attachment', 'post_mime_type' => 'image', 'post_status' => 'inherit',
            'posts_per_page' => min(50, max(1, absint($per_page))), 'paged' => max(1, absint($page)),
            's' => $search, 'orderby' => 'date', 'order' => 'DESC',
        ));
        $images = array();
        foreach ($query->posts as $post) {
            $images[] = array(
                'id' => $post->ID, 'title' => $post->post_title, 'description' => $post->post_content,
                'caption' => $post->post_excerpt, 'url' => wp_get_attachment_url($post->ID),
                'thumbnail' => wp_get_attachment_image_url($post->ID, 'thumbnail'),
                'alt' => get_post_meta($post->ID, '_wp_attachment_image_alt', true),
            );
        }
        return array('images' => $images, 'total' => (int) $query->found_posts, 'pages' => (int) $query->max_num_pages);
    }
}
