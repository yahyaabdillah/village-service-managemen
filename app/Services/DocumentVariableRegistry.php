<?php

namespace App\Services;

use App\Models\ServiceType;

class DocumentVariableRegistry
{
    private const BUILT_INS = [
        'request_code' => ['label' => 'Kode Pengajuan', 'group' => 'Pengajuan'],
        'letter_number' => ['label' => 'Nomor Surat', 'group' => 'Sistem'],
        'service_name' => ['label' => 'Nama Layanan', 'group' => 'Pengajuan'],
        'applicant_name' => ['label' => 'Nama Pemohon', 'group' => 'Pemohon'],
        'nik' => ['label' => 'NIK', 'group' => 'Pemohon'],
        'phone' => ['label' => 'Nomor HP', 'group' => 'Pemohon'],
        'address' => ['label' => 'Alamat', 'group' => 'Pemohon'],
        'hamlet' => ['label' => 'Dusun', 'group' => 'Pemohon'],
        'rt' => ['label' => 'RT', 'group' => 'Pemohon'],
        'rw' => ['label' => 'RW', 'group' => 'Pemohon'],
        'rt_rw' => ['label' => 'RT/RW', 'group' => 'Pemohon'],
        'letter_date' => ['label' => 'Tanggal Surat', 'group' => 'Sistem'],
        'place_date' => ['label' => 'Tempat dan Tanggal Surat', 'group' => 'Sistem'],
        'submitted_date' => ['label' => 'Tanggal Pengajuan', 'group' => 'Sistem'],
        'completed_date' => ['label' => 'Tanggal Selesai', 'group' => 'Sistem'],
        'officer_name' => ['label' => 'Nama Petugas', 'group' => 'Sistem'],
        'village_name' => ['label' => 'Nama Desa', 'group' => 'Desa'],
        'district' => ['label' => 'Kecamatan', 'group' => 'Desa'],
        'regency' => ['label' => 'Kabupaten', 'group' => 'Desa'],
        'province' => ['label' => 'Provinsi', 'group' => 'Desa'],
        'village_head_name' => ['label' => 'Nama Kepala Desa', 'group' => 'Desa'],
        'signer_name' => ['label' => 'Nama Penandatangan', 'group' => 'Desa'],
        'signer_title' => ['label' => 'Jabatan Penandatangan', 'group' => 'Desa'],
    ];

    public function for(ServiceType $serviceType): array
    {
        $variables = collect(self::BUILT_INS)->map(fn (array $meta, string $key) => [
            'key' => $key,
            'label' => $meta['label'],
            'group' => $meta['group'],
            'source' => 'builtin',
            'is_active' => true,
        ]);

        $dynamic = $serviceType->fields()->get()->map(fn ($field) => [
            'key' => $field->field_key,
            'label' => $field->label,
            'group' => 'Form Layanan',
            'source' => 'form',
            'is_active' => (bool) $field->is_active,
        ]);

        return $variables->concat($dynamic)->values()->all();
    }

    public function keys(ServiceType $serviceType): array
    {
        return array_column($this->for($serviceType), 'key');
    }

    public function normalize(string $key): string
    {
        return match ($key) {
            'current_date', 'tanggal_surat' => 'letter_date',
            'tempat_tanggal_surat' => 'place_date',
            default => $key,
        };
    }
}
