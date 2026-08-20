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
        if ((int) $template->service_type_id !== (int) $serviceRequest->service_type_id) {
            throw new RuntimeException('Template tidak sesuai dengan layanan pengajuan.');
        }

        $serviceRequest->loadMissing('serviceType', 'fieldValues');
        $template->loadMissing('fields');
        $templatePath = Storage::disk('private')->path($template->template_file_path);
        if (! is_file($templatePath)) {
            throw new RuntimeException('Template PDF tidak ditemukan di private storage.');
        }

        $pdf = new Fpdi;
        $pageCount = $pdf->setSourceFile($templatePath);
        if ($pageCount < 1) {
            throw new RuntimeException('Template PDF tidak mempunyai halaman.');
        }

        $template->forceFill(['page_count' => $pageCount])->saveQuietly();
        $fieldsByPage = $template->fields->groupBy(fn ($field) => (int) $field->page_number);
        $variables = $this->variables($serviceRequest);

        foreach ($template->fields as $field) {
            if ((int) $field->page_number > $pageCount) {
                throw new RuntimeException("Field '{$field->label}' berada di halaman yang tidak tersedia.");
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

    public function variables(ServiceRequest $serviceRequest): array
    {
        $fields = $serviceRequest->fieldValues->pluck('value', 'field_key')->all();
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
        $lineHeight = $field->height ? ((float) $field->height / 100) * $pageHeight : max(4.0, ((float) $field->font_size * 0.45));
        $align = match ($field->text_align) {
            'center' => 'C',
            'right' => 'R',
            default => 'L',
        };
        [$red, $green, $blue] = $this->parseColor($field->text_color ?: '#000000');
        $style = $field->font_weight === 'bold' ? 'B' : '';

        $pdf->SetFont('Arial', $style, (float) $field->font_size);
        $pdf->SetTextColor($red, $green, $blue);
        $pdf->SetXY($x, $y);
        $pdf->MultiCell(max(1, $width), max(1, $lineHeight), $value, 0, $align);
        $pdf->SetTextColor(0, 0, 0);
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
