<?php

namespace App\Services;

use App\Models\Resident;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use ZipArchive;

class ResidentExcelService
{
    public const COLUMNS = ['nik', 'name', 'gender', 'birth_place', 'birth_date', 'address', 'hamlet', 'rt', 'rw', 'religion', 'marital_status', 'occupation', 'phone', 'is_active'];

    public function template(): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'resident-template-').'.xlsx';
        $zip = new ZipArchive;
        if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Tidak bisa membuat template Excel.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rels());
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheetXml([
            self::COLUMNS,
            ['3201010101010001', 'Budi Santoso', 'male', 'Bandung', '1990-01-01', 'Jl. Desa No. 1', 'Dusun A', '001', '002', 'Islam', 'Kawin', 'Wiraswasta', '08123456789', '1'],
        ]));
        $zip->close();

        return $tmp;
    }

    public function preview(UploadedFile $file): array
    {
        $rows = str_ends_with(strtolower($file->getClientOriginalName()), '.xlsx')
            ? $this->readXlsx($file->getRealPath())
            : $this->readCsv($file->getRealPath());
        $header = $rows ? array_map(fn ($value) => trim((string) $value), array_shift($rows)) : [];
        $missing = array_diff(['nik', 'name', 'gender', 'address'], $header);
        $errors = [];
        if ($missing !== []) {
            $errors[] = 'Kolom wajib hilang: '.implode(', ', $missing);
        }
        $validRows = 0;
        $totalRows = 0;
        foreach ($rows as $index => $row) {
            if (count(array_filter($row, fn ($value) => filled($value))) === 0) {
                continue;
            }
            $totalRows++;
            $record = $this->combineRow($header, $row);
            $rowErrors = [];
            foreach (['nik', 'name', 'gender', 'address'] as $field) {
                if (blank($record[$field] ?? null)) {
                    $rowErrors[] = 'Baris '.($index + 2).": {$field} wajib diisi.";
                }
            }
            if ($rowErrors === []) {
                $validRows++;
            } else {
                array_push($errors, ...$rowErrors);
            }
        }
        if ($totalRows === 0) {
            $errors[] = 'File tidak memiliki baris data.';
        }

        return [
            'valid_rows' => $validRows,
            'total_rows' => $totalRows,
            'errors' => array_slice($errors, 0, 50),
            'can_import' => $errors === [],
        ];
    }

    public function import(UploadedFile $file): int
    {
        $preview = $this->preview($file);
        if (! $preview['can_import']) {
            throw new RuntimeException('File import tidak valid: '.implode(' ', $preview['errors']));
        }

        $rows = str_ends_with(strtolower($file->getClientOriginalName()), '.xlsx')
            ? $this->readXlsx($file->getRealPath())
            : $this->readCsv($file->getRealPath());

        if (count($rows) < 2) {
            throw new RuntimeException('File import kosong.');
        }

        $header = array_map(fn ($value) => trim((string) $value), array_shift($rows));
        $missing = array_diff(['nik', 'name', 'gender', 'address'], $header);
        if ($missing !== []) {
            throw new RuntimeException('Kolom wajib hilang: '.implode(', ', $missing));
        }

        $count = 0;
        DB::transaction(function () use ($rows, $header, &$count): void {
            foreach ($rows as $row) {
                if (count(array_filter($row, fn ($value) => filled($value))) === 0) {
                    continue;
                }
                $record = $this->combineRow($header, $row);
                $payload = collect($record)->only(self::COLUMNS)->map(fn ($value) => is_string($value) ? trim($value) : $value)->all();
                $payload['is_active'] = filter_var($payload['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
                Resident::updateOrCreate(['nik' => $payload['nik']], $payload);
                $count++;
            }
        });

        return $count;
    }

    /** @param array<int, mixed> $header
     * @param  array<int, mixed>  $row
     * @return array<string, mixed>
     */
    private function combineRow(array $header, array $row): array
    {
        if ($header === []) {
            return [];
        }

        return array_combine(
            $header,
            array_slice(array_pad($row, count($header), null), 0, count($header)),
        );
    }

    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException('CSV tidak bisa dibaca.');
        }
        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }

    private function readXlsx(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Excel tidak bisa dibaca.');
        }
        $shared = $this->sharedStrings($zip);
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if ($sheet === false) {
            throw new RuntimeException('Worksheet pertama tidak ditemukan.');
        }
        $xml = simplexml_load_string($sheet);
        $rows = [];
        foreach ($xml->sheetData->row as $row) {
            $values = [];
            foreach ($row->c as $cell) {
                $type = (string) $cell['t'];
                $value = (string) $cell->v;
                if ($type === 's') {
                    $values[] = $shared[(int) $value] ?? '';
                } elseif ($type === 'inlineStr') {
                    $values[] = (string) $cell->is->t;
                } else {
                    $values[] = $value;
                }
            }
            $rows[] = $values;
        }

        return $rows;
    }

    private function sharedStrings(ZipArchive $zip): array
    {
        $xmlText = $zip->getFromName('xl/sharedStrings.xml');
        if ($xmlText === false) {
            return [];
        }
        $xml = simplexml_load_string($xmlText);
        $strings = [];
        foreach ($xml->si as $si) {
            $strings[] = (string) $si->t;
        }

        return $strings;
    }

    /** @param array<int,array<int,string>> $rows */
    private function sheetXml(array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach ($rows as $rowIndex => $row) {
            $r = $rowIndex + 1;
            $xml .= '<row r="'.$r.'">';
            foreach ($row as $colIndex => $value) {
                $cell = $this->columnName($colIndex + 1).$r;
                $xml .= '<c r="'.$cell.'" t="inlineStr"><is><t>'.htmlspecialchars((string) $value, ENT_XML1).'</t></is></c>';
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
            $name = chr(65 + ($number % 26)).$name;
            $number = intdiv($number, 26);
        }

        return $name;
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>';
    }

    private function rels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
    }

    private function workbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Penduduk" sheetId="1" r:id="rId1"/></sheets></workbook>';
    }

    private function workbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>';
    }
}
