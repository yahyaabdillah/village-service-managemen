<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $serviceId = DB::table('service_types')
            ->where('slug', 'surat-pengantar-ktp-kk')
            ->value('id');

        if (! $serviceId) {
            return;
        }

        DB::table('service_requirements')
            ->where('service_type_id', $serviceId)
            ->where('name', 'KTP Pemohon')
            ->whereNull('deleted_at')
            ->update([
                'name' => 'Kartu Keluarga (KK) atau Akta Kelahiran',
                'description' => 'Unggah KK atau akta kelahiran sebagai dokumen pendukung. KTP tidak wajib untuk pengajuan KTP pertama.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        $serviceId = DB::table('service_types')
            ->where('slug', 'surat-pengantar-ktp-kk')
            ->value('id');

        if (! $serviceId) {
            return;
        }

        DB::table('service_requirements')
            ->where('service_type_id', $serviceId)
            ->where('name', 'Kartu Keluarga (KK) atau Akta Kelahiran')
            ->whereNull('deleted_at')
            ->update([
                'name' => 'KTP Pemohon',
                'description' => 'Foto/scan KTP pemohon.',
                'updated_at' => now(),
            ]);
    }
};
