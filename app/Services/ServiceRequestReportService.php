<?php

namespace App\Services;

use App\Models\ServiceRequest;
use App\Models\VillageProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use setasign\Fpdi\Fpdi;

/**
 * The printable recap of a filtered request list: same rows the clerk is looking at
 * on screen, laid out as a landscape table with a village letterhead, so it reads as
 * an official report rather than a screenshot of the admin page.
 */
class ServiceRequestReportService
{
    /** @param  array{q?: string, status?: string, service_type_id?: int|string, from?: string, to?: string}  $filters */
    public function build(Builder $query, array $filters): string
    {
        $profile = VillageProfile::where('is_active', true)->first();
        $requests = $query->get();

        $pdf = new Fpdi('L', 'mm', 'A4');
        // A report this small gains nothing from compression, and leaving it off means
        // the rows can be found directly in the output bytes (by a test, or anyone else
        // grepping the file) instead of only after inflating the content stream.
        $pdf->SetCompression(false);
        $pdf->SetAutoPageBreak(true, 16);
        $pdf->SetMargins(12, 12, 12);
        $pdf->AddPage();

        $this->letterhead($pdf, $profile);
        $this->summary($pdf, $filters, $requests->count());
        $this->table($pdf, $requests);
        $this->footer($pdf, $requests->count());

        return $pdf->Output('S');
    }

    private function letterhead(Fpdi $pdf, ?VillageProfile $profile): void
    {
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 7, Str::upper('Pemerintah '.($profile?->village_name ?: 'Desa Ngringo')), 0, 1, 'C');
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 5, trim(($profile?->district ?: 'Kecamatan Jaten').', '.($profile?->regency ?: 'Kabupaten Karanganyar').', '.($profile?->province ?: 'Jawa Tengah')), 0, 1, 'C');
        $pdf->Ln(1);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 7, 'LAPORAN PENGAJUAN LAYANAN SURAT', 0, 1, 'C');
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.5);
        $pdf->Line(12, $pdf->GetY() + 1, 285, $pdf->GetY() + 1);
        $pdf->Ln(5);
    }

    /** @param  array{q?: string, status?: string, service_type_id?: int|string, from?: string, to?: string}  $filters */
    private function summary(Fpdi $pdf, array $filters, int $total): void
    {
        $period = match (true) {
            filled($filters['from'] ?? null) && filled($filters['to'] ?? null) => $this->formatDate($filters['from']).' – '.$this->formatDate($filters['to']),
            filled($filters['from'] ?? null) => 'Sejak '.$this->formatDate($filters['from']),
            filled($filters['to'] ?? null) => 'Sampai '.$this->formatDate($filters['to']),
            default => 'Seluruh periode',
        };

        $lines = [
            ['Periode', $period],
            ['Status', filled($filters['status'] ?? null) ? (ServiceRequest::statuses()[$filters['status']] ?? $filters['status']) : 'Semua status'],
            ['Layanan', $filters['service_type_label'] ?? 'Semua layanan'],
        ];
        if (filled($filters['q'] ?? null)) {
            $lines[] = ['Pencarian', '"'.$filters['q'].'"'];
        }

        $pdf->SetFont('Arial', '', 10);
        foreach ($lines as [$label, $value]) {
            $pdf->Cell(28, 6, $label, 0, 0);
            $pdf->Cell(5, 6, ':', 0, 0);
            $pdf->Cell(0, 6, $value, 0, 1);
        }
        $pdf->Ln(2);
    }

    private function table(Fpdi $pdf, \Illuminate\Support\Collection $requests): void
    {
        $widths = ['no' => 8, 'code' => 38, 'name' => 46, 'nik' => 32, 'service' => 48, 'status' => 32, 'submitted' => 28, 'letter' => 40];
        $headers = ['No', 'Kode Pengajuan', 'Nama Pemohon', 'NIK', 'Layanan', 'Status', 'Tanggal Masuk', 'Nomor Surat'];

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetFillColor(230, 236, 232);
        $i = 0;
        foreach ($widths as $key => $width) {
            $pdf->Cell($width, 7, $headers[$i++], 1, 0, 'C', true);
        }
        $pdf->Ln();

        $pdf->SetFont('Arial', '', 8.5);
        $fill = false;
        foreach ($requests as $index => $request) {
            if ($pdf->GetY() > 180) {
                $pdf->AddPage();
                $pdf->SetFont('Arial', 'B', 9);
                $pdf->SetFillColor(230, 236, 232);
                $i = 0;
                foreach ($widths as $key => $width) {
                    $pdf->Cell($width, 7, $headers[$i++], 1, 0, 'C', true);
                }
                $pdf->Ln();
                $pdf->SetFont('Arial', '', 8.5);
            }

            $pdf->SetFillColor(248, 250, 248);
            $pdf->Cell($widths['no'], 6.5, (string) ($index + 1), 1, 0, 'C', $fill);
            $pdf->Cell($widths['code'], 6.5, $this->ascii($request->request_code), 1, 0, 'L', $fill);
            $pdf->Cell($widths['name'], 6.5, $this->ascii(Str::limit($request->applicant_name, 28, '')), 1, 0, 'L', $fill);
            $pdf->Cell($widths['nik'], 6.5, $this->ascii($request->nik), 1, 0, 'L', $fill);
            $pdf->Cell($widths['service'], 6.5, $this->ascii(Str::limit($request->serviceType?->name ?: '-', 30, '')), 1, 0, 'L', $fill);
            $pdf->Cell($widths['status'], 6.5, $this->ascii($request->publicStatusLabel()), 1, 0, 'L', $fill);
            $pdf->Cell($widths['submitted'], 6.5, optional($request->submitted_at ?? $request->created_at)->translatedFormat('d/m/Y'), 1, 0, 'C', $fill);
            $pdf->Cell($widths['letter'], 6.5, $this->ascii($request->letter_number ?: '-'), 1, 0, 'L', $fill);
            $pdf->Ln();
            $fill = ! $fill;
        }

        if ($requests->isEmpty()) {
            $pdf->SetFont('Arial', 'I', 9);
            $pdf->Cell(array_sum($widths), 8, 'Tidak ada pengajuan yang cocok dengan filter ini.', 1, 1, 'C');
        }
    }

    private function footer(Fpdi $pdf, int $total): void
    {
        $pdf->Ln(3);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(0, 6, "Total: {$total} pengajuan", 0, 1);
        $pdf->SetFont('Arial', 'I', 8);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(0, 5, 'Dicetak pada '.now()->locale('id')->translatedFormat('d F Y, H:i').' WIB dari Sistem Layanan Desa.', 0, 1);
        $pdf->SetTextColor(0, 0, 0);
    }

    private function formatDate(?string $date): string
    {
        if (! $date) {
            return '-';
        }

        try {
            return \Illuminate\Support\Carbon::parse($date)->locale('id')->translatedFormat('d F Y');
        } catch (\Throwable) {
            return $date;
        }
    }

    /** FPDF's core fonts are Latin-1; transliterate rather than print mojibake. */
    private function ascii(?string $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }
        $latin = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $value);

        return $latin === false ? preg_replace('/[^\x20-\x7E]/', '', $value) ?? '' : $latin;
    }
}
