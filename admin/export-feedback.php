<?php
declare(strict_types=1);
require_once __DIR__.'/../auth.php';

if (!class_exists(ZipArchive::class) || !class_exists(XMLWriter::class)) {
    http_response_code(500);
    exit('The PHP zip and xmlwriter extensions are required for Excel export.');
}

$columns = [
    'Submission ID' => 'id',
    'Client name' => 'client_name',
    'POC Name' => 'poc_name',
    'Submitted at' => 'submitted_at',
    'Average of five ratings' => 'average_score',
    'Overall, how would you rate your experience with Strata Staff?' => 'relationship_score',
    'How well do you feel Strata Staff understands your business needs and expectations?' => 'business_needs_score',
    'How satisfied are you with the level of communication and support you receive from your Strata Staff?' => 'communication_support_score',
    'Do you feel comfortable raising concerns, questions, or feedback with Strata Staff?' => 'concerns_comfort_score',
    'How confident are you in continuing your partnership with Strata Staff?' => 'partnership_confidence_score',
    'Is there anything Strata Staff could improve to better support you or your team?' => 'improvement_feedback',
    'At this point, how would you describe your relationship with Strata Staff?' => 'relationship_description',
    'Previous form: meeting notes' => 'meeting_notes',
    'Previous form: concerns' => 'action_items',
    'Previous form: comments' => 'client_comments',
    'Previous form: additional ratings' => 'additional_responses',
];

function excelColumn(int $index): string
{
    $name = '';
    while ($index > 0) {
        $index--;
        $name = chr(65 + $index % 26).$name;
        $index = intdiv($index, 26);
    }
    return $name;
}

function excelCell(XMLWriter $xml, string $reference, ?string $value): void
{
    if ($value === null || $value === '') return;
    // Inline strings are data in Excel, so client text cannot become a formula.
    $value = preg_replace('/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $value) ?? '';
    $xml->startElement('c');
    $xml->writeAttribute('r', $reference);
    $xml->writeAttribute('t', 'inlineStr');
    $xml->startElement('is');
    $xml->startElement('t');
    $xml->writeAttribute('xml:space', 'preserve');
    $xml->text($value);
    $xml->endElement();
    $xml->endElement();
    $xml->endElement();
}

$rows = db()->query('SELECT s.*, c.client_name, f.submitted_at,
    COALESCE((s.relationship_score + s.business_needs_score + s.communication_support_score + s.concerns_comfort_score + s.partnership_confidence_score) / 5, s.relationship_score) AS average_score
    FROM feedback_submissions s
    JOIN clients c ON c.id = s.client_id
    JOIN feedback_forms f ON f.id = s.form_id
    ORDER BY s.created_at DESC, s.id DESC');

$xml = new XMLWriter();
$xml->openMemory();
$xml->startDocument('1.0', 'UTF-8');
$xml->startElement('worksheet');
$xml->writeAttribute('xmlns', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
$xml->startElement('sheetData');
$xml->startElement('row');
$xml->writeAttribute('r', '1');
$column = 1;
foreach (array_keys($columns) as $heading) {
    excelCell($xml, excelColumn($column++).'1', $heading);
}
$xml->endElement();
$rowNumber = 2;
foreach ($rows as $row) {
    if ($rowNumber > 1048576) break; // Excel worksheet limit.
    $xml->startElement('row');
    $xml->writeAttribute('r', (string)$rowNumber);
    $column = 1;
    foreach ($columns as $field) {
        $value = $row[$field];
        if ($field === 'poc_name') $value = $row['poc_name'] ?: $row['staff_name'];
        if ($field === 'submitted_at') $value = $row['submitted_at'] ?: $row['created_at'];
        if ($field === 'average_score') $value = number_format((float)$value, 1);
        excelCell($xml, excelColumn($column++).$rowNumber, $value === null ? null : (string)$value);
    }
    $xml->endElement();
    $rowNumber++;
}
$xml->endElement();
$xml->endElement();
$xml->endDocument();

$temporary = tempnam(sys_get_temp_dir(), 'feedback-export-');
if ($temporary === false) { http_response_code(500); exit('Could not prepare export.'); }
$zip = new ZipArchive();
if ($zip->open($temporary, ZipArchive::OVERWRITE) !== true) {
    unlink($temporary);
    http_response_code(500);
    exit('Could not prepare export.');
}
$zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
$zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
$zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="All feedback" sheetId="1" r:id="rId1"/></sheets></workbook>');
$zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
$zip->addFromString('xl/worksheets/sheet1.xml', $xml->outputMemory());
$zip->close();

$filename = 'client-feedback-'.date('Y-m-d').'.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Content-Length: '.filesize($temporary));
header('Cache-Control: private, no-store');
readfile($temporary);
unlink($temporary);
