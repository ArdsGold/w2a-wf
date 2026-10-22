<?php
/**
 * DOCX parser.
 */

if (!defined('ABSPATH')) {
    exit;
}

class WTAI_Docx_Parser {
    private $error_handler;
    private $numbering = array();
    private $styles = array();
    private $relationships = array();
    const WORD_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    const MAX_XML_BYTES = 8388608;
    const MAX_TOTAL_XML_BYTES = 16777216;
    const MAX_ENTRIES = 4096;

    public function __construct($error_handler = null) {
        $this->error_handler = $error_handler;
    }

    public function parse($file_path) {
        if (!file_exists($file_path) || !is_readable($file_path)) {
            $this->log(WTAI_Error_Handler::ERR_FILE_NOT_FOUND, array('file_path' => $file_path));
            throw new Exception(__('The uploaded file could not be read.', 'wolf-forge-article-generator'));
        }
        if (!class_exists('ZipArchive') && !$this->load_pclzip()) {
            $this->log(WTAI_Error_Handler::ERR_MISSING_EXTENSION, array('extension' => 'zip', 'fallback' => 'PclZip'));
            throw new Exception(__('Neither the PHP ZIP extension nor WordPress PclZip is available. DOCX import cannot continue.', 'wolf-forge-article-generator'));
        }
        if (!function_exists('simplexml_load_string')) {
            $this->log(WTAI_Error_Handler::ERR_MISSING_EXTENSION, array('extension' => 'simplexml'));
            throw new Exception(__('The PHP XML extension is required to import DOCX files.', 'wolf-forge-article-generator'));
        }

        try {
            $entries = $this->read_zip_entries($file_path, array(
                'word/document.xml',
                'word/numbering.xml',
                'word/styles.xml',
                'word/_rels/document.xml.rels',
            ));
            $document_xml = isset($entries['word/document.xml']) ? $entries['word/document.xml'] : false;
            if (false === $document_xml || '' === $document_xml) {
                throw new Exception(__('The DOCX file is missing word/document.xml.', 'wolf-forge-article-generator'));
            }

            $this->numbering = $this->parse_numbering(isset($entries['word/numbering.xml']) ? $entries['word/numbering.xml'] : false);
            $this->styles = $this->parse_styles(isset($entries['word/styles.xml']) ? $entries['word/styles.xml'] : false);
            $this->relationships = $this->parse_relationships($entries['word/_rels/document.xml.rels']);
            $xml = $this->load_xml($document_xml);
            if (false === $xml) {
                throw new Exception(__('The document XML could not be parsed.', 'wolf-forge-article-generator'));
            }

            $xml->registerXPathNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            return $this->parse_document($xml);
        } catch (Throwable $e) {
            $this->log(WTAI_Error_Handler::ERR_DOCX_PARSE_FAILED, array('file_path' => $file_path, 'error' => $e->getMessage()));
            throw $e;
        } finally {
            $this->numbering = array();
            $this->styles = array();
            $this->relationships = array();
        }
    }

    /**
     * Read multiple files from a DOCX ZIP archive in one pass.
     */
    private function read_zip_entries($file_path, $entry_names) {
        $entries = array_fill_keys($entry_names, false);

        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if (true !== $zip->open($file_path)) {
                throw new Exception(__('The DOCX file could not be opened.', 'wolf-forge-article-generator'));
            }

            try {
                if ($zip->numFiles > self::MAX_ENTRIES) {
                    throw new Exception(__('The DOCX contains too many archive entries.', 'wolf-forge-article-generator'));
                }
                $total = 0;
                foreach ($entry_names as $entry_name) {
                    $stat = $zip->statName($entry_name);
                    if ($stat) {
                        $total += $stat['size'];
                        $this->check_xml_size($stat['size'], $total);
                    }
                }
                foreach ($entry_names as $entry_name) {
                    $entries[$entry_name] = $zip->getFromName($entry_name, self::MAX_XML_BYTES + 1);
                    if ($entries[$entry_name] !== false) $this->check_xml_size(strlen($entries[$entry_name]), 0);
                }
            } finally {
                $zip->close();
            }

            return $entries;
        }

        if (!$this->load_pclzip()) {
            throw new Exception(__('No supported ZIP reader is available on this WordPress installation.', 'wolf-forge-article-generator'));
        }

        $archive = new PclZip($file_path);
        $listing = $archive->listContent();
        if (!is_array($listing) || count($listing) > self::MAX_ENTRIES) {
            throw new Exception(__('The DOCX archive is invalid or contains too many entries.', 'wolf-forge-article-generator'));
        }
        $total = 0;
        foreach ($listing as $entry) {
            if (in_array($entry['filename'], $entry_names, true)) {
                $total += $entry['size'];
                $this->check_xml_size($entry['size'], $total);
            }
        }
        foreach ($entry_names as $entry_name) {
            $result = $archive->extract(
                PCLZIP_OPT_BY_NAME,
                $entry_name,
                PCLZIP_OPT_EXTRACT_AS_STRING
            );

            if (is_array($result)) {
                foreach ($result as $entry) {
                    if (isset($entry['filename']) && $entry['filename'] === $entry_name) {
                        $entries[$entry_name] = isset($entry['content']) ? $entry['content'] : '';
                        $this->check_xml_size(strlen($entries[$entry_name]), 0);
                        break;
                    }
                }
            }
        }

        return $entries;
    }

    private function check_xml_size($size, $total) {
        if ($size > self::MAX_XML_BYTES || $total > self::MAX_TOTAL_XML_BYTES) {
            throw new Exception(__('DOCX XML exceeds the supported limit (8 MB per entry, 16 MB total).', 'wolf-forge-article-generator'));
        }
    }

    private function load_pclzip() {
        if (class_exists('PclZip')) {
            return true;
        }

        $pclzip = ABSPATH . 'wp-admin/includes/class-pclzip.php';
        if (file_exists($pclzip)) {
            require_once $pclzip;
        }

        return class_exists('PclZip');
    }

    private function parse_numbering($xml_string) {
        $map = array();
        if (!$xml_string) {
            return $map;
        }

        $xml = $this->load_xml($xml_string);
        if ($xml === false) {
            return $map;
        }
        $xml->registerXPathNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $abstracts = array();
        foreach ($xml->xpath('//w:abstractNum') as $abstract) {
            $id = (string) $abstract->attributes(self::WORD_NS)->abstractNumId;
            $abstracts[$id] = array();
            foreach ($abstract->xpath('./w:lvl') as $level) {
                $ilvl = (string) $level->attributes(self::WORD_NS)->ilvl;
                $fmt = $level->xpath('./w:numFmt');
                $format = $fmt ? (string) $fmt[0]->attributes(self::WORD_NS)->val : 'bullet';
                $abstracts[$id][(int) $ilvl] = $format;
            }
        }

        foreach ($xml->xpath('//w:num') as $num) {
            $num_id = (int) $num->attributes(self::WORD_NS)->numId;
            $abstract_id_node = $num->xpath('./w:abstractNumId');
            $abstract_id = $abstract_id_node ? (string) $abstract_id_node[0]->attributes(self::WORD_NS)->val : '';
            $map[$num_id] = isset($abstracts[$abstract_id]) ? $abstracts[$abstract_id] : array();
        }
        return $map;
    }

    private function parse_document($xml) {
        $html = '';
        $list_stack = array();


        // Process the body in document order. Looking only for //w:p misses
        // tables and can also lose the relationship between list/heading
        // blocks when a table occurs between them.
        $body_nodes = $xml->xpath('//w:body/*');

        foreach ($body_nodes as $block) {
            $name = $block->getName();

            if ($name === 'tbl') {
                if ($list_stack) {
                    $html .= $this->close_all_lists($list_stack);
                    $list_stack = array();
                }

                $table = $this->parse_table($block);
                if ($table !== '') {
                    $html .= $table . "\n";
                }
                continue;
            }

            if ($name !== 'p') {
                continue;
            }

            $style = $this->get_paragraph_style($block);
            $heading_level = $this->get_heading_level($block, $style);
            $list = $this->get_list_info($block, $style);
            $content = $this->parse_runs($block);

            if ($heading_level > 0) {
                $html .= $this->close_all_lists($list_stack);
                $list_stack = array();

                if ($content !== '') {
                    $tag = 'h' . $heading_level;
                    $html .= '<' . $tag . '>' . $content . '</' . $tag . ">\n";
                }
                continue;
            }

            if ($list) {
                $level = max(1, (int) $list['level']);
                $html .= $this->append_list_item($list_stack, $level, $content, $list['type']);
                continue;
            }

            if ($list_stack) {
                $html .= $this->close_all_lists($list_stack);
                $list_stack = array();
            }

            if (trim(wp_strip_all_tags($content)) !== '') {
                $html .= '<p>' . $content . "</p>\n";
            }
        }

        if ($list_stack) {
            $html .= $this->close_all_lists($list_stack);
        }

        return $html;
    }

    /**
     * Convert a Word table into semantic HTML.
     */
    private function parse_table($table) {
        $rows = $table->xpath('./w:tr');
        if (!$rows) {
            return '';
        }

        $html = '<table><tbody>';

        foreach ($rows as $row) {
            $html .= '<tr>';

            $cells = $row->xpath('./w:tc');
            foreach ($cells as $cell) {
                $is_header = false;
                $header_nodes = $row->xpath('./w:trPr/w:tblHeader');
                if ($header_nodes) {
                    $is_header = true;
                }

                $cell_parts = array();
                foreach ($cell->xpath('./w:p') as $paragraph) {
                    $content = $this->parse_runs($paragraph);
                    if ($content !== '') {
                        $cell_parts[] = $content;
                    }
                }

                $cell_html = implode('<br>', $cell_parts);
                $tag = $is_header ? 'th' : 'td';
                $html .= '<' . $tag . '>' . $cell_html . '</' . $tag . '>';
            }

            $html .= '</tr>';
        }

        return $html . '</tbody></table>';
    }

    /**
     * Build a reliable paragraph-style map from styles.xml. Word normally
     * stores heading IDs as Heading1/Heading2 (without a space), so checking
     * only the visible style name "Heading 1" is not sufficient.
     */
    private function parse_styles($xml_string) {
        $map = array();
        if (!$xml_string) {
            return $map;
        }

        $xml = $this->load_xml($xml_string);
        if ($xml === false) {
            return $map;
        }

        $xml->registerXPathNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        foreach ($xml->xpath('//w:style[@w:type="paragraph"]') as $style) {
            $style_id = (string) $style->attributes(self::WORD_NS)->styleId;
            if ($style_id === '') {
                continue;
            }

            $name_nodes = $style->xpath('./w:name');
            $name = $name_nodes ? (string) $name_nodes[0]->attributes(self::WORD_NS)->val : '';
            $outline_nodes = $style->xpath('./w:pPr/w:outlineLvl');
            $num_id_nodes = $style->xpath('./w:pPr/w:numPr/w:numId');
            $ilvl_nodes = $style->xpath('./w:pPr/w:numPr/w:ilvl');

            $map[$style_id] = array(
                'name' => $name,
                'outline_level' => $outline_nodes ? (int) $outline_nodes[0]->attributes(self::WORD_NS)->val : null,
                'num_id' => $num_id_nodes ? (int) $num_id_nodes[0]->attributes(self::WORD_NS)->val : null,
                'level' => $ilvl_nodes ? (int) $ilvl_nodes[0]->attributes(self::WORD_NS)->val : null,
            );
        }

        return $map;
    }

    private function append_list_item(&$stack, $level, $content, $type) {
        $tag = $type === 'numbered' ? 'ol' : 'ul';
        $level = min(9, max(1, $level));
        if (!$stack) {
            $stack[] = $tag;
            return '<' . $tag . ">\n<li>" . $content;
        }
        $html = '';
        while (count($stack) > $level) {
            $html .= "</li>\n</" . array_pop($stack) . '>';
        }
        if (count($stack) < $level) {
            while (count($stack) < $level) {
                $html .= "\n<" . $tag . ">\n<li>";
                $stack[] = $tag;
            }
            return $html . $content;
        }
        if (end($stack) !== $tag) {
            $html .= "</li>\n</" . array_pop($stack) . ">\n<" . $tag . ">\n<li>";
            $stack[] = $tag;
            return $html . $content;
        }
        return $html . "</li>\n<li>" . $content;
    }

    private function close_all_lists($stack) {
        $html = '';
        while ($stack) $html .= "</li>\n</" . array_pop($stack) . ">\n";
        return $html;
    }

    private function get_paragraph_style($paragraph) {
        $nodes = $paragraph->xpath('./w:pPr/w:pStyle');
        if ($nodes) {
            $style_id = (string) $nodes[0]->attributes(self::WORD_NS)->val;
            if ($style_id !== '') {
                return $style_id;
            }
        }

        // Fallback for DOCX files with unusual namespace metadata.
        $raw = $paragraph->asXML();
        if ($raw && preg_match('/<w:pStyle\b[^>]*\bw:val=["\']([^"\']+)["\']/i', $raw, $match)) {
            return $match[1];
        }

        return '';
    }

    private function get_list_info($paragraph, $style = '') {
        $num_nodes = $paragraph->xpath('./w:pPr/w:numPr/w:numId');
        $level_nodes = $paragraph->xpath('./w:pPr/w:numPr/w:ilvl');

        // Direct paragraph numbering always wins over the style definition.
        if ($num_nodes) {
            $num_id = (int) $num_nodes[0]->attributes(self::WORD_NS)->val;
            if ($num_id === 0) return false;
            $level = $level_nodes ? (int) $level_nodes[0]->attributes(self::WORD_NS)->val : 0;
            $format = isset($this->numbering[$num_id][$level]) ? $this->numbering[$num_id][$level] : 'bullet';

            return array(
                'level' => $level + 1,
                'type' => $this->is_numbered_format($format) ? 'numbered' : 'bullet',
            );
        }

        // Some DOCX files put numPr on the paragraph style rather than pPr.
        if ($style && isset($this->styles[$style]) && $this->styles[$style]['num_id'] !== null) {
            $num_id = (int) $this->styles[$style]['num_id'];
            if ($num_id === 0) return false;
            $level = $this->styles[$style]['level'] !== null ? (int) $this->styles[$style]['level'] : 0;
            $format = isset($this->numbering[$num_id][$level]) ? $this->numbering[$num_id][$level] : 'bullet';

            return array(
                'level' => $level + 1,
                'type' => $this->is_numbered_format($format) ? 'numbered' : 'bullet',
            );
        }

        // Fallback for Word's standard list style IDs/names.
        if ($style && preg_match('/^List(?:Bullet|Number)([1-9])?$/i', $style, $matches)) {
            $level = !empty($matches[1]) ? (int) $matches[1] : 1;
            return array(
                'level' => $level,
                'type' => stripos($style, 'ListNumber') === 0 ? 'numbered' : 'bullet',
            );
        }

        $style_name = $style && isset($this->styles[$style]['name']) ? $this->styles[$style]['name'] : '';
        if ($style_name && preg_match('/^List\s*(?:Bullet|Number)\s*([1-9])?$/i', $style_name, $matches)) {
            $level = !empty($matches[1]) ? (int) $matches[1] : 1;
            return array(
                'level' => $level,
                'type' => preg_match('/^List\s*Number/i', $style_name) ? 'numbered' : 'bullet',
            );
        }

        return false;
    }

    private function is_numbered_format($format) {
        static $numbered = array(
            'decimal' => true, 'decimalZero' => true,
            'lowerLetter' => true, 'upperLetter' => true,
            'lowerRoman' => true, 'upperRoman' => true,
            'lowerAlpha' => true, 'upperAlpha' => true,
        );

        return isset($numbered[$format]);
    }

    private function get_heading_level($paragraph, $style_name) {
        $candidates = array();

        if ($style_name) {
            $candidates[] = $style_name;
            if (isset($this->styles[$style_name]['name'])) {
                $candidates[] = $this->styles[$style_name]['name'];
            }
        }

        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);

            // Handles Heading1, Heading 1, HEADING1, etc.
            if (preg_match('/^Heading\s*([1-6])$/i', $candidate, $m)) {
                return (int) $m[1];
            }

            if (strcasecmp($candidate, 'Title') === 0) {
                return 1;
            }

            if (strcasecmp($candidate, 'Subtitle') === 0) {
                return 2;
            }
        }

        // A heading can inherit its outline level through styles.xml.
        if ($style_name && isset($this->styles[$style_name]['outline_level']) && $this->styles[$style_name]['outline_level'] !== null) {
            $level = (int) $this->styles[$style_name]['outline_level'] + 1;
            if ($level >= 1 && $level <= 6) {
                return $level;
            }
        }

        // Or it can be set directly on the paragraph.
        $outline = $paragraph->xpath('./w:pPr/w:outlineLvl');
        if ($outline) {
            $level = (int) $outline[0]->attributes(self::WORD_NS)->val + 1;
            if ($level >= 1 && $level <= 6) {
                return $level;
            }
        }

        return 0;
    }

    private function parse_runs($container) {
        $html = '';
        foreach ($container->children(self::WORD_NS) as $node) {
            switch ($node->getName()) {
                case 'r':
                    $text = '';
                    foreach ($node->children(self::WORD_NS) as $part) {
                        switch ($part->getName()) {
                            case 't': $text .= esc_html((string) $part); break;
                            case 'tab': $text .= '&emsp;'; break;
                            case 'br':
                            case 'cr': $text .= '<br>'; break;
                        }
                    }
                    foreach (array('b' => 'strong', 'i' => 'em', 'u' => 'u', 'strike' => 's') as $property => $tag) {
                        if ($this->is_on($node->xpath('./w:rPr/w:' . $property))) {
                            $text = '<' . $tag . '>' . $text . '</' . $tag . '>';
                        }
                    }
                    $html .= $text;
                    break;
                case 'hyperlink':
                    $text = $this->parse_runs($node);
                    $id = (string) $node->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')->id;
                    $url = isset($this->relationships[$id]) ? $this->relationships[$id] : '';
                    $html .= $url ? '<a href="' . esc_url($url, array('http', 'https', 'mailto', 'tel')) . '">' . $text . '</a>' : $text;
                    break;
                case 'ins':
                    $html .= $this->parse_runs($node);
                    break;
            }
        }
        return $html;
    }

    private function parse_relationships($xml_string) {
        $links = array();
        if (!$xml_string) return $links;
        $xml = $this->load_xml($xml_string);
        if ($xml === false) return $links;
        foreach ($xml->children() as $relationship) {
            $attributes = $relationship->attributes();
            if ((string) $attributes->TargetMode !== 'External' || substr((string) $attributes->Type, -10) !== '/hyperlink') continue;
            $url = esc_url_raw((string) $attributes->Target, array('http', 'https', 'mailto', 'tel'));
            if ($url) $links[(string) $attributes->Id] = $url;
        }
        return $links;
    }

    private function load_xml($xml_string) {
        if (preg_match('/<!\s*(?:DOCTYPE|ENTITY)\b/i', $xml_string)) {
            throw new Exception(__('XML document type and entity declarations are not supported.', 'wolf-forge-article-generator'));
        }
        return simplexml_load_string($xml_string, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    }

    private function is_on($nodes) {
        if (!$nodes) return false;
        $val = $nodes[0]->attributes(self::WORD_NS)->val;
        return $val === null || !in_array((string) $val, array('0', 'false', 'off', 'none'), true);
    }

    private function log($code, $context = array()) {
        if ($this->error_handler) {
            $this->error_handler->log_error($code, $context);
        }
    }
}
