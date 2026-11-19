<?php

namespace App\Support\Photoshop;

use Phar;
use PharData;
use RuntimeException;

final class PhotoshopWorkbookWriter
{
    /**
     * @param  array<string, array<int, array<int, string>>>  $sheets
     */
    public function write(string $absolutePath, array $sheets): string
    {
        foreach (array_keys($sheets) as $name) {
            if (strlen($name) > 31 || preg_match('/[\\[\\]:*?\/\\\\]/', $name)) {
                throw new RuntimeException("Invalid Excel sheet name: {$name}");
            }
        }

        $directory = dirname($absolutePath);

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException("Cannot create workbook directory: {$directory}");
        }

        $zipPath = $absolutePath.'.zip';
        $archive = new PharData($zipPath, 0, null, Phar::ZIP);
        $archive->addFromString('[Content_Types].xml', $this->contentTypes(count($sheets)));
        $archive->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $archive->addFromString('xl/workbook.xml', $this->workbookXml(array_keys($sheets)));
        $archive->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationships(count($sheets)));

        foreach (array_values($sheets) as $index => $rows) {
            $archive->addFromString('xl/worksheets/sheet'.($index + 1).'.xml', $this->worksheetXml($rows));
        }

        unset($archive);

        if (! rename($zipPath, $absolutePath)) {
            throw new RuntimeException("Cannot save Photoshop workbook: {$absolutePath}");
        }

        return $absolutePath;
    }

    private function contentTypes(int $sheetCount): string
    {
        $overrides = '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';

        for ($index = 1; $index <= $sheetCount; $index++) {
            $overrides .= '<Override PartName="/xl/worksheets/sheet'.$index.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'.$overrides.'</Types>';
    }

    /** @param array<int, string> $names */
    private function workbookXml(array $names): string
    {
        $sheets = '';

        foreach ($names as $index => $name) {
            $sheets .= '<sheet name="'.$this->escape($name).'" sheetId="'.($index + 1).'" r:id="rId'.($index + 1).'"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'.$sheets.'</sheets></workbook>';
    }

    private function workbookRelationships(int $sheetCount): string
    {
        $relationships = '';

        for ($index = 1; $index <= $sheetCount; $index++) {
            $relationships .= '<Relationship Id="rId'.$index.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$index.'.xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$relationships.'</Relationships>';
    }

    /** @param array<int, array<int, string>> $rows */
    private function worksheetXml(array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        foreach ($rows as $rowIndex => $row) {
            $rowNumber = $rowIndex + 1;
            $xml .= '<row r="'.$rowNumber.'">';

            foreach ($row as $columnIndex => $value) {
                $column = $this->columnName($columnIndex + 1);
                $xml .= '<c r="'.$column.$rowNumber.'" t="inlineStr"><is><t xml:space="preserve">'.$this->escape($value).'</t></is></c>';
            }

            $xml .= '</row>';
        }

        return $xml.'</sheetData></worksheet>';
    }

    private function columnName(int $number): string
    {
        $name = '';

        while ($number > 0) {
            $number--;
            $name = chr(65 + $number % 26).$name;
            $number = intdiv($number, 26);
        }

        return $name;
    }

    private function escape(string $value): string
    {
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value) ?? $value;

        return htmlspecialchars($clean, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
