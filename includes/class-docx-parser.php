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

    public function __construct($error_handler = null) {
        $this->error_handler = $error_handler;
    }

    public function parse($file_path) {
        if (!file_exists($file_path) || !is_readable($file_path)) {
            $this->log(WTAI_Error_Handler::ERR_FILE_NOT_FOUND, array('file_path' => $file_path));
            throw new Exception(__('The uploaded file could not be read.', 'word-to-article-importer'));
        }
        if (!class_exists('ZipArchive') && !$this->load_pclzip()) {
            $this->log(WTAI_Error_Handler::ERR_MISSING_EXTENSION, array('extension' => 'zip', 'fallback' => 'PclZip'));
            throw new Exception(__('Neither the PHP ZIP extension nor WordPress PclZip is available. DOCX import cannot continue.', 'word-to-article-importer'));
        }
        if (!function_exists('simplexml_load_string')) {
            $this->log(WTAI_Error_Handler::ERR_MISSING_EXTENSION, array('extension' => 'simplexml'));
            throw new Exception(__('The PHP XML extension is required to import DOCX files.', 'word-to-article-importer'));
        }

        try {
            $entries = $this->read_zip_entries($file_path, array(
                'word/document.xml',
                'word/numbering.xml',
                'word/styles.xml',
            ));
            $document_xml = isset($entries['word/document.xml']) ? $entries['word/document.xml'] : false;
            if (false === $document_xml || '' === $document_xml) {
                throw new Exception(__('The DOCX file is missing word/document.xml.', 'word-to-article-importer'));
            }

            $this->numbering = $this->parse_numbering(isset($entries['word/numbering.xml']) ? $entries['word/numbering.xml'] : false);
            $this->styles = $this->parse_styles(isset($entries['word/styles.xml']) ? $entries['word/styles.xml'] : false);
            $xml = simplexml_load_string($document_xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            if (false === $xml) {
                throw new Exception(__('The document XML could not be parsed.', 'word-to-article-importer'));
            }

            $xml->registerXPathNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            return $this->parse_document($xml);
        } catch (Throwable $e) {
            $this->log(WTAI_Error_Handler::ERR_DOCX_PARSE_FAILED, array('file_path' => $file_path, 'error' => $e->getMessage()));
            throw $e;
        } finally {
            $this->numbering = array();
            $this->styles = array();
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
                throw new Exception(__('The DOCX file could not be opened.', 'word-to-article-importer'));
            }

            try {
                foreach ($entry_names as $entry_name) {
                    $entries[$entry_name] = $zip->getFromName($entry_name);
                }
            } finally {
                $zip->close();
            }

            return $entries;
        }

        if (!$this->load_pclzip()) {
            throw new Exception(__('No supported ZIP reader is available on this WordPress installation.', 'word-to-article-importer'));
        }

        $archive = new PclZip($file_path);
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
                        break;
                    }
                }
            }
        }

        return $entries;
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

        $xml = simplexml_load_string($xml_string, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        if (!$xml) {
            return $map;
        }
        $xml->registerXPathNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $abstracts = array();
        foreach ($xml->xpath('//w:abstractNum') as $abstract) {
            $id = (string) $abstract->attributes('w')->abstractNumId;
            $abstracts[$id] = array();
            foreach ($abstract->xpath('./w:lvl') as $level) {
                $ilvl = (string) $level->attributes('w')->ilvl;
                $fmt = $level->xpath('./w:numFmt');
                $format = $fmt ? (string) $fmt[0]->attributes('w')->val : 'bullet';
                $abstracts[$id][(int) $ilvl] = $format;
            }
        }

        foreach ($xml->xpath('//w:num') as $num) {
            $num_id = (int) $num->attributes('w')->numId;
            $abstract_id_node = $num->xpath('./w:abstractNumId');
            $abstract_id = $abstract_id_node ? (string) $abstract_id_node[0]->attributes('w')->val : '';
            $map[$num_id] = isset($abstracts[$abstract_id]) ? $abstracts[$abstract_id] : array();
        }
        return $map;
    }

    private function parse_document($xml) {
        $html = '';
        $list_stack = array();
        $h1_seen = false;


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
                    // Word's first H1 is the document title, which WordPress
                    // already stores separately as the post title. Omit that
                    // first H1 from the body. Any later H1 is a section heading,
                    // so demote it to H2 to maintain a single top-level heading.
                    if ($heading_level === 1) {
                        if (!$h1_seen) {
                            $h1_seen = true;
                            continue;
                        }

                        $heading_level = 2;
                    }

                    $tag = 'h' . $heading_level;
                    $html .= '<' . $tag . '>' . $content . '</' . $tag . ">\n";
                }
                continue;
            }

            if ($list) {
                $level = max(1, (int) $list['level']);
                $html .= $this->append_list_item($list_stack, $level, $content);
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
                $header_nodes = $cell->xpath('./w:tcPr/w:tblHeader');
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

        $xml = simplexml_load_string($xml_string, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        if (!$xml) {
            return $map;
        }

        $xml->registerXPathNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        foreach ($xml->xpath('//w:style[@w:type="paragraph"]') as $style) {
            $style_id = (string) $style->attributes('w')->styleId;
            if ($style_id === '') {
                continue;
            }

            $name_nodes = $style->xpath('./w:name');
            $name = $name_nodes ? (string) $name_nodes[0]->attributes('w')->val : '';
            $outline_nodes = $style->xpath('./w:pPr/w:outlineLvl');
            $num_id_nodes = $style->xpath('./w:pPr/w:numPr/w:numId');
            $ilvl_nodes = $style->xpath('./w:pPr/w:numPr/w:ilvl');

            $map[$style_id] = array(
                'name' => $name,
                'outline_level' => $outline_nodes ? (int) $outline_nodes[0]->attributes('w')->val : null,
                'num_id' => $num_id_nodes ? (int) $num_id_nodes[0]->attributes('w')->val : null,
                'level' => $ilvl_nodes ? (int) $ilvl_nodes[0]->attributes('w')->val : null,
            );
        }

        return $map;
    }

    private function append_list_item(&$stack, $level, $content) {
        $html = '';
        $current = count($stack);

        if ($current === 0) {
            $html .= "<ul>\n<li>" . $content;
            $stack[] = true;
            return $html;
        }

        if ($level > $current) {
            // Keep the previous <li> open so the nested <ul> belongs to it.
            while ($current < $level) {
                $html .= "\n<ul>\n<li>";
                $stack[] = true;
                $current++;
            }
            $html .= $content;
            return $html;
        }

        if ($level < $current) {
            while ($current > $level) {
                $html .= "</li>\n</ul>";
                array_pop($stack);
                $current--;
            }
            $html .= "</li>\n<li>" . $content;
            return $html;
        }

        $html .= "</li>\n<li>" . $content;
        return $html;
    }

    private function close_all_lists($stack) {
        if (!$stack) {
            return '';
        }

        $html = '';
        for ($i = count($stack) - 1; $i >= 0; $i--) {
            $html .= "</li>\n</ul>\n";
        }
        return $html;
    }

    private function get_paragraph_style($paragraph) {
        $nodes = $paragraph->xpath('./w:pPr/w:pStyle');
        if ($nodes) {
            $style_id = (string) $nodes[0]->attributes('w')->val;
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
            $num_id = (int) $num_nodes[0]->attributes('w')->val;
            $level = $level_nodes ? (int) $level_nodes[0]->attributes('w')->val : 0;
            $format = isset($this->numbering[$num_id][$level]) ? $this->numbering[$num_id][$level] : 'bullet';

            return array(
                'level' => $level + 1,
                'type' => $this->is_numbered_format($format) ? 'numbered' : 'bullet',
            );
        }

        // Some DOCX files put numPr on the paragraph style rather than pPr.
        if ($style && isset($this->styles[$style]) && $this->styles[$style]['num_id'] !== null) {
            $num_id = (int) $this->styles[$style]['num_id'];
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
                'type' => stripos($style_name, 'ListNumber') === 0 ? 'numbered' : 'bullet',
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

        // Read the pStyle directly from the paragraph as an independent
        // fallback. Word commonly stores Heading styles as Heading1, Heading2,
        // etc., without a space.
        $raw = $paragraph->asXML();
        if ($raw && preg_match('/<w:pStyle\b[^>]*\bw:val=["\']([^"\']+)["\']/i', $raw, $match)) {
            $candidates[] = $match[1];
        }

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
            $level = (int) $outline[0]->attributes('w')->val + 1;
            if ($level >= 1 && $level <= 6) {
                return $level;
            }
        }

        return 0;
    }

    private function parse_runs($paragraph) {
        $html = '';
        $nodes = $paragraph->xpath('./w:r | ./w:hyperlink/w:r | ./w:ins/w:r | ./w:hyperlink/w:ins/w:r');

        foreach ($nodes as $run) {
            $text = '';
            foreach ($run->xpath('./w:t') as $node) {
                $text .= (string) $node;
            }
            foreach ($run->xpath('./w:tab') as $node) {
                $text .= "\t";
            }
            foreach ($run->xpath('./w:br | ./w:cr') as $node) {
                $text .= "\n";
            }

            if ($text === '') {
                continue;
            }

            $text = esc_html($text);
            $text = str_replace(array("\r\n", "\r", "\n"), '<br>', $text);
            $text = str_replace("\t", '&emsp;', $text);

            $rpr = $run->xpath('./w:rPr');
            $rpr = $rpr ? $rpr[0] : null;

            if ($rpr && $this->is_on($rpr->xpath('./w:b'))) {
                $text = '<strong>' . $text . '</strong>';
            }
            if ($rpr && $this->is_on($rpr->xpath('./w:i'))) {
                $text = '<em>' . $text . '</em>';
            }
            if ($rpr && $this->is_on($rpr->xpath('./w:u'))) {
                $text = '<u>' . $text . '</u>';
            }
            if ($rpr && $this->is_on($rpr->xpath('./w:strike'))) {
                $text = '<s>' . $text . '</s>';
            }

            $html .= $text;
        }

        return $html;
    }

    private function is_on($nodes) {
        if (!$nodes) return false;
        $val = $nodes[0]->attributes('w')->val;
        return $val === null || !in_array((string) $val, array('0', 'false', 'off'), true);
    }

    private function log($code, $context = array()) {
        if ($this->error_handler) {
            $this->error_handler->log_error($code, $context);
        }
    }
}
