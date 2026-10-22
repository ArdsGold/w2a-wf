<?php
/** Small generated DOCX fixtures; no binary files or third-party test framework. */
if (PHP_SAPI !== 'cli') exit;

function wfag_test_archive($entries) {
    $path = sys_get_temp_dir() . '/wfag-test-' . bin2hex(random_bytes(8)) . '.zip';
    $archive = new PharData($path, 0, null, Phar::ZIP);
    foreach ($entries as $name => $content) $archive->addFromString($name, $content);
    unset($archive);
    return $path;
}

function wfag_test_document($body = null) {
    $body = $body === null ? '<w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr><w:r><w:t>Document Title</w:t></w:r></w:p>
        <w:p><w:r><w:rPr><w:b/></w:rPr><w:t>Before</w:t><w:br/><w:t>After</w:t><w:tab/><w:t>Tab</w:t></w:r>
        <w:hyperlink r:id="rId1"><w:r><w:t>Website</w:t></w:r></w:hyperlink>
        <w:hyperlink r:id="rId2"><w:r><w:t>Unsafe</w:t></w:r></w:hyperlink></w:p>
        <w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr></w:pPr><w:r><w:t>Number one</w:t></w:r></w:p>
        <w:p><w:pPr><w:numPr><w:ilvl w:val="1"/><w:numId w:val="2"/></w:numPr></w:pPr><w:r><w:t>Nested bullet</w:t></w:r></w:p>
        <w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr></w:pPr><w:r><w:t>Number two</w:t></w:r></w:p>
        <w:p><w:pPr><w:pStyle w:val="ListBullet"/></w:pPr><w:r><w:t>Bullet root</w:t></w:r></w:p>
        <w:tbl><w:tr><w:trPr><w:tblHeader/></w:trPr><w:tc><w:p><w:r><w:t>Header</w:t></w:r></w:p></w:tc></w:tr>
        <w:tr><w:tc><w:p><w:r><w:t>Cell</w:t></w:r></w:p></w:tc></w:tr></w:tbl>' : $body;
    return array(
        'word/document.xml' => '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><w:body>' . $body . '</w:body></w:document>',
        'word/numbering.xml' => '<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:abstractNum w:abstractNumId="0"><w:lvl w:ilvl="0"><w:numFmt w:val="decimal"/></w:lvl></w:abstractNum><w:abstractNum w:abstractNumId="1"><w:lvl w:ilvl="1"><w:numFmt w:val="bullet"/></w:lvl></w:abstractNum><w:num w:numId="1"><w:abstractNumId w:val="0"/></w:num><w:num w:numId="2"><w:abstractNumId w:val="1"/></w:num></w:numbering>',
        'word/_rels/document.xml.rels' => '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.com/guide" TargetMode="External"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="javascript:alert(1)" TargetMode="External"/></Relationships>',
    );
}
