<?php

namespace App\Services;

use App\Models\DocumentTemplate;
use App\Models\GeneratedDocument;
use App\Models\ServiceRequest;
use App\Models\VillageProfile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use setasign\Fpdi\Fpdi;

class DocumentGenerationService
{
    public function __construct(private DocumentMappingResolver $mappingResolver) {}

    public function generate(ServiceRequest $serviceRequest, DocumentTemplate $template, ?string $reason = null): GeneratedDocument
    {
        $content = $this->render($serviceRequest, $template);

        $path = 'generated-documents/'.$serviceRequest->request_code.'/'.Str::uuid().'.pdf';
        if (! Storage::disk('private')->put($path, $content)) {
            throw new RuntimeException('Dokumen gagal disimpan ke private storage.');
        }

        try {
            $serviceRequest->generatedDocuments()->where('is_active', true)->update([
                'is_active' => false,
                'status' => 'superseded',
            ]);

            $document = GeneratedDocument::create([
                'service_request_id' => $serviceRequest->id,
                'document_template_id' => $template->id,
                'source' => 'generated',
                'file_path' => $path,
                'original_file_name' => 'generated-'.$serviceRequest->request_code.'.pdf',
                'file_type' => 'pdf',
                'mime_type' => 'application/pdf',
                'file_size' => strlen($content),
                'checksum' => hash('sha256', $content),
                'status' => 'valid',
                'is_active' => true,
                'generation_reason' => $reason,
                'generated_by' => auth()->id(),
                'generated_at' => now(),
            ]);

            $serviceRequest->update([
                'document_template_id' => $template->id,
                'document_source' => 'generated',
                'generated_document_path' => $path,
            ]);

            return $document;
        } catch (\Throwable $exception) {
            Storage::disk('private')->delete($path);
            throw $exception;
        }
    }

    /**
     * Lay the request's data over the template and return the PDF bytes without
     * storing anything. Used for the real letter and for the builder's sample.
     *
     * @param  array<string, mixed>|null  $variables  overrides the request's own data (sample mode)
     */
    public function render(ServiceRequest $serviceRequest, DocumentTemplate $template, ?array $variables = null): string
    {
        if ((int) $template->service_type_id !== (int) $serviceRequest->service_type_id) {
            throw new RuntimeException('Template tidak sesuai dengan layanan pengajuan.');
        }

        $template->loadMissing('fields');
        $templatePath = Storage::disk('private')->path($template->template_file_path);
        if (! is_file($templatePath)) {
            throw new RuntimeException('File PDF template tidak ditemukan. Unggah ulang template ini.');
        }

        $pdf = new Fpdi;
        $pageCount = $pdf->setSourceFile($templatePath);
        if ($pageCount < 1) {
            throw new RuntimeException('Template PDF tidak mempunyai halaman.');
        }

        if ($template->exists && (int) $template->page_count !== (int) $pageCount) {
            $template->forceFill(['page_count' => $pageCount])->saveQuietly();
        }
        $fieldsByPage = $template->fields->groupBy(fn ($field) => (int) $field->page_number);
        $variables ??= $this->variables($serviceRequest);

        foreach ($template->fields as $field) {
            if ((int) $field->page_number > $pageCount) {
                throw new RuntimeException("Teks '{$field->label}' berada di halaman yang tidak ada pada PDF.");
            }
        }

        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            $templateId = $pdf->importPage($pageNumber);
            $size = $pdf->getTemplateSize($templateId);
            $orientation = ($size['width'] ?? 0) > ($size['height'] ?? 0) ? 'L' : 'P';
            $pdf->AddPage($orientation, [$size['width'], $size['height']]);
            $pdf->useTemplate($templateId, 0, 0, $size['width'], $size['height'], true);

            foreach ($fieldsByPage->get($pageNumber, collect()) as $field) {
                $value = $this->mappingResolver->resolve($field->mapping_config, $field->variable_key, $variables);
                $this->writeField($pdf, $field, $value, $size['width'], $size['height']);
            }
        }

        $content = $pdf->Output('S');
        if (! str_starts_with($content, '%PDF-') || strlen($content) < 100) {
            throw new RuntimeException('Hasil generate bukan PDF yang valid.');
        }

        return $content;
    }

    public function variables(ServiceRequest $serviceRequest): array
    {
        $serviceRequest->loadMissing('serviceType', 'fieldValues');
        // Every question the service asks is a printable key, even when this applicant
        // left it blank or it was added after they applied; a template must not fail on it.
        $fields = collect($serviceRequest->serviceType?->fields()->pluck('field_key') ?? [])
            ->mapWithKeys(fn ($key) => [$key => null])
            ->merge($serviceRequest->fieldValues->pluck('value', 'field_key'))
            ->all();
        $profile = VillageProfile::where('is_active', true)->first();
        $letterDateSource = $serviceRequest->completed_at ?: now();
        $letterDate = $this->formatIndonesianDate($letterDateSource);
        $letterPlace = $profile?->village_name
            ? str_replace(['Desa ', 'desa '], '', $profile->village_name)
            : 'Ngringo';

        return array_merge([
            'request_code' => $serviceRequest->request_code,
            'letter_number' => $serviceRequest->letter_number,
            'service_name' => $serviceRequest->serviceType?->name,
            'applicant_name' => $serviceRequest->applicant_name,
            'nik' => $serviceRequest->nik,
            'phone' => $serviceRequest->phone,
            'address' => $serviceRequest->address,
            'hamlet' => $serviceRequest->hamlet,
            'rt' => $serviceRequest->rt,
            'rw' => $serviceRequest->rw,
            'rt_rw' => trim(($serviceRequest->rt ?: '-').'/'.($serviceRequest->rw ?: '-')),
            'letter_date' => $letterDate,
            '__raw_letter_date' => Carbon::parse($letterDateSource)->toIso8601String(),
            'place_date' => $letterPlace.', '.$letterDate,
            'submitted_date' => optional($serviceRequest->submitted_at)->format('d/m/Y'),
            '__raw_submitted_date' => optional($serviceRequest->submitted_at)?->toIso8601String(),
            'completed_date' => optional($serviceRequest->completed_at)->format('d/m/Y'),
            '__raw_completed_date' => optional($serviceRequest->completed_at)?->toIso8601String(),
            'officer_name' => auth()->user()?->name,
            'village_name' => $profile?->village_name,
            'district' => $profile?->district,
            'regency' => $profile?->regency,
            'province' => $profile?->province,
            'village_head_name' => $profile?->village_head_name,
            'signer_name' => $profile?->default_signer_name,
            'signer_title' => $profile?->default_signer_title,
        ], $fields);
    }

    private function formatIndonesianDate(mixed $date): string
    {
        $carbon = $date instanceof \DateTimeInterface ? Carbon::instance($date) : Carbon::parse($date);

        return $carbon->locale('id')->translatedFormat('d F Y');
    }

    private function writeField(Fpdi $pdf, object $field, string $value, float $pageWidth, float $pageHeight): void
    {
        $x = ((float) $field->x_position / 100) * $pageWidth;
        $y = ((float) $field->y_position / 100) * $pageHeight;
        $width = $field->width ? ((float) $field->width / 100) * $pageWidth : ($pageWidth - $x);
        $boxHeight = $field->height ? ((float) $field->height / 100) * $pageHeight : null;
        $fontSize = (float) $field->font_size;
        // A line is 1.25× the font size (points → mm). The box height drawn on the canvas
        // only limits how many lines fit; it is not the line height itself.
        $lineHeight = $fontSize * 0.3528 * 1.25;
        $align = match ($field->text_align) {
            'center' => 'C',
            'right' => 'R',
            default => 'L',
        };
        [$red, $green, $blue] = $this->parseColor($field->text_color ?: '#000000');
        $style = $field->font_weight === 'bold' ? 'B' : '';

        $pdf->SetFont('Arial', $style, $fontSize);
        $pdf->SetTextColor($red, $green, $blue);
        $pdf->SetXY($x, $y);
        $pdf->MultiCell(max(1, $width), max(1, $lineHeight), $this->encode($value, $boxHeight, $lineHeight), 0, $align);
        $pdf->SetTextColor(0, 0, 0);
    }

    /**
     * FPDF's core fonts are Latin-1: transliterate what they cannot show (curly quotes,
     * dashes, accented names) instead of printing mojibake, and keep only the lines the
     * box can hold so a long answer never runs over the text beneath it.
     */
    private function encode(string $value, ?float $boxHeight, float $lineHeight): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $latin = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $value);
        if ($latin === false) {
            $latin = preg_replace('/[^\x20-\x7E\n]/', '', $value) ?? '';
        }

        if ($boxHeight !== null) {
            $maxLines = max(1, (int) floor(($boxHeight + 0.5) / $lineHeight));
            $lines = explode("\n", $latin);
            if (count($lines) > $maxLines) {
                $latin = implode("\n", array_slice($lines, 0, $maxLines));
            }
        }

        return $latin;
    }

    private function parseColor(string $color): array
    {
        $hex = ltrim($color, '#');
        if (! preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return [0, 0, 0];
        }

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }
}
