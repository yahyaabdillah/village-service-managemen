<?php

namespace App\Services;

use App\Models\DocumentTemplate;
use App\Models\ServiceType;
use App\Models\VillageProfile;
use FPDF;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfessionalLetterTemplateService
{
    /** @return array<string, string> */
    private const TITLES = [
        'surat-keterangan-domisili' => 'SURAT KETERANGAN DOMISILI',
        'surat-keterangan-usaha' => 'SURAT KETERANGAN USAHA',
        'surat-keterangan-tidak-mampu' => 'SURAT KETERANGAN TIDAK MAMPU',
        'surat-pengantar-ktp-kk' => 'SURAT PENGANTAR KTP/KK',
        'pengaduan-masyarakat' => 'TANDA TERIMA PENGADUAN MASYARAKAT',
    ];

    public function syncDefaultTemplates(?VillageProfile $profile = null): void
    {
        $profile ??= VillageProfile::where('is_active', true)->first();

        ServiceType::query()->orderBy('sort_order')->each(function (ServiceType $service) use ($profile) {
            $this->createTemplateForService($service, $profile);
        });
    }

    public function createTemplateForService(ServiceType $service, ?VillageProfile $profile = null): DocumentTemplate
    {
        $profile ??= VillageProfile::where('is_active', true)->first();
        $slug = $service->slug;
        $title = self::TITLES[$slug] ?? Str::upper($service->name);
        $path = 'document-templates/defaults/'.$slug.'.pdf';

        Storage::disk('private')->put($path, $this->buildPdf($service, $profile, $title));

        $template = DocumentTemplate::updateOrCreate(
            ['service_type_id' => $service->id, 'name' => 'Template Resmi '.$service->name],
            [
                'description' => 'Template resmi siap pakai dengan kop surat, logo, nomor surat, tabel data warga, isi surat, tanda tangan, dan area stempel.',
                'template_file_path' => $path,
                'original_file_name' => 'template-resmi-'.$slug.'.pdf',
                'page_count' => 1,
                'is_active' => true,
                'status' => 'active',
                'version' => 1,
                'is_default' => true,
                'validated_at' => now(),
            ]
        );

        $template->fields()->delete();
        foreach ($this->fields($slug) as $field) {
            $template->fields()->create($field);
        }

        return $template;
    }

    private function buildPdf(ServiceType $service, ?VillageProfile $profile, string $title): string
    {
        $pdf = new FPDF('P', 'mm', 'A4');
        $pdf->AddPage();
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(22, 16, 22);

        $this->letterhead($pdf, $profile);

        $pdf->SetY(57);
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 7, $title, 0, 1, 'C');
        $pdf->SetFont('Arial', '', 11);
        $pdf->Cell(0, 6, 'Nomor: ', 0, 1, 'C');
        $pdf->SetY(82);

        $body = $this->body($service->slug, $profile?->village_name ?: 'Desa Ngringo');
        $pdf->MultiCell(0, 7, $body, 0, 'J');

        $pdf->SetY(108);

        $this->dataTable($pdf);

        $pdf->SetY(166);
        $pdf->MultiCell(0, 7, 'Demikian surat keterangan ini dibuat dengan sebenarnya agar dapat dipergunakan sebagaimana mestinya.', 0, 'J');

        $this->signatureBlock($pdf, $profile);
        $this->footer($pdf);

        return $pdf->Output('S');
    }

    private function letterhead(FPDF $pdf, ?VillageProfile $profile): void
    {
        $logo = $profile?->letterhead_logo_path ? public_path($profile->letterhead_logo_path) : null;
        $hasLogo = $logo && is_file($logo);
        if ($hasLogo) {
            $pdf->Image($logo, 22, 12, 20, 26);
        }

        $textX = $hasLogo ? 45 : 22;
        $textWidth = $hasLogo ? 143 : 166;

        $pdf->SetXY($textX, 12);
        $pdf->SetFont('Arial', 'B', 13);
        $pdf->Cell($textWidth, 6, 'PEMERINTAH KABUPATEN '.Str::upper($profile?->regency ? str_replace('Kabupaten ', '', $profile->regency) : 'KARANGANYAR'), 0, 1, 'C');
        $pdf->SetX($textX);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell($textWidth, 6, Str::upper($profile?->district ?: 'KECAMATAN JATEN'), 0, 1, 'C');
        $pdf->SetX($textX);
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell($textWidth, 7, Str::upper($profile?->village_name ?: 'DESA NGRINGO'), 0, 1, 'C');
        $pdf->SetX($textX);
        $pdf->SetFont('Arial', '', 9);
        $address = $profile?->address ?: 'Alamat kantor desa setempat';
        $contact = trim(implode(' | ', array_filter(
            [$profile?->phone, $profile?->email, $profile?->website],
            fn ($value) => $value && $value !== '-'
        )));
        $pdf->Cell($textWidth, 5, $address, 0, 1, 'C');
        if ($contact !== '') {
            $pdf->SetX($textX);
            $pdf->Cell($textWidth, 5, $contact, 0, 1, 'C');
        }

        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.7);
        $pdf->Line(22, 43, 188, 43);
        $pdf->SetLineWidth(0.2);
        $pdf->Line(22, 45, 188, 45);
    }

    private function dataTable(FPDF $pdf): void
    {
        $rows = [
            ['Nama', 'applicant_name'],
            ['NIK', 'nik'],
            ['Alamat', 'address'],
            ['RT/RW', 'rt_rw'],
            ['No. HP', 'phone'],
            ['Keperluan', 'keperluan'],
        ];

        $pdf->SetFont('Arial', '', 11);
        foreach ($rows as [$label]) {
            $pdf->Cell(38, 8, $label, 0, 0);
            $pdf->Cell(5, 8, ':', 0, 0);
            $pdf->Cell(0, 8, '', 0, 1);
        }
    }

    private function signatureBlock(FPDF $pdf, ?VillageProfile $profile): void
    {
        $signerTitle = $profile?->default_signer_title ?: 'Kepala Desa';
        $signerName = $profile?->default_signer_name ?: ($profile?->village_head_name ?: 'Kepala Desa Ngringo');
        $pdf->SetY(200);
        $pdf->SetX(118);
        $pdf->Cell(70, 6, $signerTitle === $signerName ? '' : $signerTitle, 0, 1, 'C');
        $pdf->SetX(118);
        $pdf->SetTextColor(150, 150, 150);
        $pdf->Cell(70, 24, '[ Area tanda tangan dan stempel ]', 0, 1, 'C');
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetX(118);
        $pdf->SetFont('Arial', 'BU', 11);
        $pdf->Cell(70, 7, $signerName, 0, 1, 'C');
        if ($profile?->village_head_nip) {
            $pdf->SetX(118);
            $pdf->SetFont('Arial', '', 10);
            $pdf->Cell(70, 5, 'NIP. '.$profile->village_head_nip, 0, 1, 'C');
        }
    }

    private function footer(FPDF $pdf): void
    {
        $pdf->SetY(-18);
        $pdf->SetFont('Arial', 'I', 8);
        $pdf->SetTextColor(95, 95, 95);
        $pdf->Cell(0, 5, 'Dokumen ini dihasilkan oleh Sistem Layanan Desa dan dapat diverifikasi melalui kode pengajuan/NIK.', 0, 0, 'C');
        $pdf->SetTextColor(0, 0, 0);
    }

    /** @return array<int, array<string, mixed>> */
    private function fields(string $slug): array
    {
        $fields = [
            ['label' => 'Nomor Surat', 'variable_key' => 'letter_number', 'page_number' => 1, 'x_position' => 54.0, 'y_position' => 21.85, 'width' => 25.0, 'height' => 2.2, 'font_size' => 11, 'text_align' => 'left', 'text_color' => '#000000'],
            ['label' => 'Nama Pemohon', 'variable_key' => 'applicant_name', 'page_number' => 1, 'x_position' => 31.0, 'y_position' => 36.6, 'width' => 55.0, 'height' => 2.7, 'font_size' => 11, 'text_align' => 'left', 'text_color' => '#000000'],
            ['label' => 'NIK', 'variable_key' => 'nik', 'page_number' => 1, 'x_position' => 31.0, 'y_position' => 39.3, 'width' => 55.0, 'height' => 2.7, 'font_size' => 11, 'text_align' => 'left', 'text_color' => '#000000'],
            ['label' => 'Alamat', 'variable_key' => 'address', 'page_number' => 1, 'x_position' => 31.0, 'y_position' => 42.0, 'width' => 55.0, 'height' => 2.6, 'font_size' => 10.5, 'text_align' => 'left', 'text_color' => '#000000'],
            ['label' => 'RT/RW', 'variable_key' => 'rt_rw', 'page_number' => 1, 'x_position' => 31.0, 'y_position' => 44.7, 'width' => 55.0, 'height' => 2.7, 'font_size' => 11, 'text_align' => 'left', 'text_color' => '#000000'],
            ['label' => 'No. HP', 'variable_key' => 'phone', 'page_number' => 1, 'x_position' => 31.0, 'y_position' => 47.35, 'width' => 55.0, 'height' => 2.7, 'font_size' => 11, 'text_align' => 'left', 'text_color' => '#000000'],
            ['label' => 'Keperluan', 'variable_key' => 'keperluan', 'page_number' => 1, 'x_position' => 31.0, 'y_position' => 50.05, 'width' => 55.0, 'height' => 2.7, 'font_size' => 11, 'text_align' => 'left', 'text_color' => '#000000'],
            ['label' => 'Tempat dan Tanggal Surat', 'variable_key' => 'place_date', 'page_number' => 1, 'x_position' => 62.0, 'y_position' => 65.32, 'width' => 34.0, 'height' => 2.2, 'font_size' => 11, 'text_align' => 'left', 'text_color' => '#000000'],
        ];

        return array_map(function (array $field) {
            $field['mapping_config'] = ['version' => 1, 'mode' => 'source', 'key' => $field['variable_key']];

            return $field;
        }, $fields);
    }

    private function body(string $slug, string $villageName): string
    {
        return match ($slug) {
            'surat-keterangan-domisili' => "Yang bertanda tangan di bawah ini menerangkan bahwa nama tersebut benar merupakan warga yang berdomisili di wilayah {$villageName}. Surat keterangan ini diberikan untuk dipergunakan sebagaimana mestinya.",
            'surat-keterangan-usaha' => "Yang bertanda tangan di bawah ini menerangkan bahwa nama tersebut benar berdomisili di wilayah {$villageName} dan sepanjang pengetahuan kami memiliki/mengelola usaha sesuai keterangan pemohon. Surat keterangan ini diberikan untuk dipergunakan sebagaimana mestinya.",
            'surat-keterangan-tidak-mampu' => "Yang bertanda tangan di bawah ini menerangkan bahwa nama tersebut benar warga {$villageName} dan berdasarkan keterangan yang ada termasuk warga yang memerlukan dukungan administrasi sesuai keperluan pemohon.",
            'surat-pengantar-ktp-kk' => 'Yang bertanda tangan di bawah ini memberikan pengantar administrasi kependudukan kepada nama tersebut untuk keperluan pengurusan KTP/KK sesuai ketentuan yang berlaku.',
            'pengaduan-masyarakat' => "Pemerintah {$villageName} telah menerima pengaduan/permohonan dari warga dengan data sebagai berikut. Pengaduan akan ditindaklanjuti sesuai mekanisme pelayanan desa.",
            default => 'Yang bertanda tangan di bawah ini menerangkan data pemohon layanan desa sebagaimana tercantum dalam surat ini.',
        };
    }
}
