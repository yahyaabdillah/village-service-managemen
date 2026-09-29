<?php

namespace App\Services;

use App\Models\ServiceType;

class DocumentVariableRegistry
{
    /**
     * Data that every letter can print, with the example shown on the builder canvas
     * and in the sample PDF. Answers to the service's own form questions are appended
     * per service.
     */
    private const BUILT_INS = [
        'letter_number' => ['label' => 'Nomor surat', 'group' => 'Surat', 'sample' => '470/012/IX/2026'],
        'letter_date' => ['label' => 'Tanggal surat', 'group' => 'Surat', 'sample' => '30 September 2026'],
        'place_date' => ['label' => 'Tempat dan tanggal surat', 'group' => 'Surat', 'sample' => 'Ngringo, 30 September 2026'],
        'service_name' => ['label' => 'Nama layanan', 'group' => 'Surat', 'sample' => 'Surat Keterangan Domisili'],
        'officer_name' => ['label' => 'Nama petugas', 'group' => 'Surat', 'sample' => 'Petugas Desa'],
        'applicant_name' => ['label' => 'Nama pemohon', 'group' => 'Pemohon', 'sample' => 'Siti Rahmawati'],
        'nik' => ['label' => 'NIK', 'group' => 'Pemohon', 'sample' => '3313011507900002'],
        'phone' => ['label' => 'Nomor HP', 'group' => 'Pemohon', 'sample' => '+6281234567890'],
        'address' => ['label' => 'Alamat', 'group' => 'Pemohon', 'sample' => 'Jl. Melati No. 12'],
        'hamlet' => ['label' => 'Dusun', 'group' => 'Pemohon', 'sample' => 'Krajan'],
        'rt' => ['label' => 'RT', 'group' => 'Pemohon', 'sample' => '01'],
        'rw' => ['label' => 'RW', 'group' => 'Pemohon', 'sample' => '02'],
        'rt_rw' => ['label' => 'RT/RW', 'group' => 'Pemohon', 'sample' => '01/02'],
        'request_code' => ['label' => 'Kode pengajuan', 'group' => 'Pengajuan', 'sample' => 'REQ-20260930-AB12'],
        'submitted_date' => ['label' => 'Tanggal pengajuan', 'group' => 'Pengajuan', 'sample' => '28/09/2026'],
        'completed_date' => ['label' => 'Tanggal selesai', 'group' => 'Pengajuan', 'sample' => '30/09/2026'],
        'village_name' => ['label' => 'Nama desa', 'group' => 'Desa', 'sample' => 'Desa Ngringo'],
        'district' => ['label' => 'Kecamatan', 'group' => 'Desa', 'sample' => 'Kecamatan Jaten'],
        'regency' => ['label' => 'Kabupaten', 'group' => 'Desa', 'sample' => 'Kabupaten Karanganyar'],
        'province' => ['label' => 'Provinsi', 'group' => 'Desa', 'sample' => 'Jawa Tengah'],
        'village_head_name' => ['label' => 'Nama kepala desa', 'group' => 'Desa', 'sample' => 'Kepala Desa'],
        'signer_name' => ['label' => 'Nama penandatangan', 'group' => 'Desa', 'sample' => 'Kepala Desa'],
        'signer_title' => ['label' => 'Jabatan penandatangan', 'group' => 'Desa', 'sample' => 'Kepala Desa'],
    ];

    /** Keys whose value is a date, so a date format may be applied. */
    public const DATE_KEYS = ['letter_date', 'submitted_date', 'completed_date'];

    public function for(ServiceType $serviceType): array
    {
        $variables = collect(self::BUILT_INS)->map(fn (array $meta, string $key) => [
            'key' => $key,
            'label' => $meta['label'],
            'group' => $meta['group'],
            'sample' => $meta['sample'],
            'source' => 'builtin',
            'is_active' => true,
        ]);

        $dynamic = $serviceType->fields()->orderBy('sort_order')->get()->map(fn ($field) => [
            'key' => $field->field_key,
            'label' => $field->label,
            'group' => 'Isian formulir',
            'sample' => $this->sampleFor($field),
            'source' => 'form',
            'is_active' => (bool) $field->is_active,
        ]);

        return $variables->concat($dynamic)->values()->all();
    }

    public function keys(ServiceType $serviceType): array
    {
        return array_column($this->for($serviceType), 'key');
    }

    /** Example value per key, used for the sample PDF. */
    public function samples(ServiceType $serviceType): array
    {
        return collect($this->for($serviceType))->pluck('sample', 'key')->all();
    }

    public function normalize(string $key): string
    {
        return match ($key) {
            'current_date', 'tanggal_surat' => 'letter_date',
            'tempat_tanggal_surat' => 'place_date',
            default => $key,
        };
    }

    private function sampleFor(object $field): string
    {
        return match ($field->field_type) {
            'date' => '15/08/2026',
            'number' => '3',
            'email' => 'warga@contoh.id',
            'select' => (string) (($field->options ?: [])[0] ?? $field->label),
            default => $field->placeholder ?: 'Contoh '.strtolower($field->label),
        };
    }
}
