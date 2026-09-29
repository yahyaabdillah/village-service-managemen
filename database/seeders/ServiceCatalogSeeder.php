<?php

namespace Database\Seeders;

use App\Models\ServiceRequirement;
use App\Models\ServiceType;
use App\Models\ServiceTypeField;
use Illuminate\Database\Seeder;

/**
 * The letter types a village office issues, each with the documents a citizen must attach
 * and the questions its letter needs answered. Field sets follow the data that commonly
 * appears on these letters (see SKTM / SKU / domisili formats used by village offices).
 */
class ServiceCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $ktp = ['name' => 'KTP pemohon', 'description' => 'Foto atau pindaian KTP yang masih berlaku.', 'is_required' => true];
        $kk = ['name' => 'Kartu Keluarga', 'description' => 'Foto atau pindaian Kartu Keluarga.', 'is_required' => true];
        $pengantar = ['name' => 'Surat pengantar RT/RW', 'description' => 'Surat pengantar yang sudah ditandatangani ketua RT dan RW.', 'is_required' => false];

        $services = [
            [
                'name' => 'Surat Keterangan Domisili', 'slug' => 'surat-keterangan-domisili',
                'description' => 'Keterangan bahwa pemohon benar berdomisili di desa ini, untuk keperluan administrasi bank, sekolah, pekerjaan, atau instansi lain.',
                'requirements' => [$ktp, $kk, $pengantar],
                'fields' => [
                    ['label' => 'Tempat lahir', 'field_key' => 'tempat_lahir', 'field_type' => 'text', 'is_required' => true, 'placeholder' => 'Karanganyar'],
                    ['label' => 'Tanggal lahir', 'field_key' => 'tanggal_lahir', 'field_type' => 'date', 'is_required' => true],
                    ['label' => 'Jenis kelamin', 'field_key' => 'jenis_kelamin', 'field_type' => 'select', 'is_required' => true, 'options' => ['Laki-laki', 'Perempuan']],
                    ['label' => 'Agama', 'field_key' => 'agama', 'field_type' => 'select', 'is_required' => true, 'options' => ['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu']],
                    ['label' => 'Pekerjaan', 'field_key' => 'pekerjaan', 'field_type' => 'text', 'is_required' => true, 'placeholder' => 'Wiraswasta'],
                    ['label' => 'Berdomisili sejak tahun', 'field_key' => 'domisili_sejak', 'field_type' => 'number', 'is_required' => false, 'placeholder' => '2015'],
                    ['label' => 'Keperluan', 'field_key' => 'keperluan', 'field_type' => 'textarea', 'is_required' => true, 'placeholder' => 'Contoh: persyaratan pembukaan rekening bank'],
                ],
            ],
            [
                'name' => 'Surat Keterangan Usaha', 'slug' => 'surat-keterangan-usaha',
                'description' => 'Keterangan bahwa pemohon menjalankan usaha di wilayah desa, biasanya untuk pengajuan kredit atau perizinan.',
                'requirements' => [$ktp, $kk, ['name' => 'Foto tempat usaha', 'description' => 'Foto papan nama atau lokasi usaha.', 'is_required' => false]],
                'fields' => [
                    ['label' => 'Nama usaha', 'field_key' => 'nama_usaha', 'field_type' => 'text', 'is_required' => true, 'placeholder' => 'Warung Makan Barokah'],
                    ['label' => 'Bidang usaha', 'field_key' => 'bidang_usaha', 'field_type' => 'select', 'is_required' => true, 'options' => ['Perdagangan', 'Kuliner', 'Jasa', 'Pertanian', 'Peternakan', 'Kerajinan', 'Lainnya']],
                    ['label' => 'Alamat tempat usaha', 'field_key' => 'alamat_usaha', 'field_type' => 'textarea', 'is_required' => true],
                    ['label' => 'Usaha berjalan sejak tahun', 'field_key' => 'usaha_sejak', 'field_type' => 'number', 'is_required' => true, 'placeholder' => '2019'],
                    ['label' => 'Keperluan', 'field_key' => 'keperluan', 'field_type' => 'textarea', 'is_required' => true, 'placeholder' => 'Contoh: pengajuan KUR di bank'],
                ],
            ],
            [
                'name' => 'Surat Keterangan Tidak Mampu', 'slug' => 'surat-keterangan-tidak-mampu',
                'description' => 'Keterangan kondisi ekonomi keluarga untuk keringanan biaya sekolah, rumah sakit, beasiswa, atau bantuan sosial.',
                'requirements' => [$ktp, $kk, $pengantar],
                'fields' => [
                    ['label' => 'Tempat lahir', 'field_key' => 'tempat_lahir', 'field_type' => 'text', 'is_required' => true],
                    ['label' => 'Tanggal lahir', 'field_key' => 'tanggal_lahir', 'field_type' => 'date', 'is_required' => true],
                    ['label' => 'Jenis kelamin', 'field_key' => 'jenis_kelamin', 'field_type' => 'select', 'is_required' => true, 'options' => ['Laki-laki', 'Perempuan']],
                    ['label' => 'Pekerjaan', 'field_key' => 'pekerjaan', 'field_type' => 'text', 'is_required' => true],
                    ['label' => 'Penghasilan per bulan (Rp)', 'field_key' => 'penghasilan', 'field_type' => 'number', 'is_required' => false, 'placeholder' => '1500000'],
                    ['label' => 'Jumlah tanggungan keluarga', 'field_key' => 'jumlah_tanggungan', 'field_type' => 'number', 'is_required' => false, 'placeholder' => '3'],
                    ['label' => 'Nama orang tua / kepala keluarga', 'field_key' => 'nama_kepala_keluarga', 'field_type' => 'text', 'is_required' => false, 'help_text' => 'Isi bila surat untuk anak yang masih dalam tanggungan.'],
                    ['label' => 'Keperluan', 'field_key' => 'keperluan', 'field_type' => 'select', 'is_required' => true, 'options' => ['Keringanan biaya sekolah', 'Beasiswa', 'Biaya rumah sakit / BPJS', 'Bantuan sosial', 'Lainnya']],
                ],
            ],
            [
                'name' => 'Surat Pengantar KTP/KK', 'slug' => 'surat-pengantar-ktp-kk',
                'description' => 'Pengantar dari desa untuk mengurus KTP atau Kartu Keluarga di kecamatan atau Dukcapil.',
                'requirements' => [
                    ['name' => 'Kartu Keluarga atau akta kelahiran', 'description' => 'Untuk KTP pertama, akta kelahiran dapat menggantikan KTP.', 'is_required' => true],
                    ['name' => 'KTP lama', 'description' => 'Wajib untuk perpanjangan atau perubahan data.', 'is_required' => false],
                ],
                'fields' => [
                    ['label' => 'Jenis permohonan', 'field_key' => 'jenis_permohonan', 'field_type' => 'select', 'is_required' => true, 'options' => ['KTP baru', 'Perpanjangan KTP', 'KTP hilang / rusak', 'Perubahan data KTP', 'KK baru', 'Perubahan data KK']],
                    ['label' => 'Nomor KK', 'field_key' => 'nomor_kk', 'field_type' => 'text', 'is_required' => true, 'placeholder' => '16 digit'],
                    ['label' => 'Perubahan yang diajukan', 'field_key' => 'perubahan', 'field_type' => 'textarea', 'is_required' => false, 'help_text' => 'Isi bila mengajukan perubahan data, misalnya alamat atau status perkawinan.'],
                ],
            ],
            [
                'name' => 'Pengaduan Masyarakat', 'slug' => 'pengaduan-masyarakat',
                'description' => 'Sampaikan keluhan atau laporan tentang layanan dan fasilitas desa.',
                'requirements' => [['name' => 'Foto pendukung', 'description' => 'Foto kondisi yang dilaporkan, bila ada.', 'is_required' => false]],
                'fields' => [
                    ['label' => 'Kategori pengaduan', 'field_key' => 'kategori', 'field_type' => 'select', 'is_required' => true, 'options' => ['Infrastruktur', 'Kebersihan', 'Pelayanan', 'Keamanan', 'Lainnya']],
                    ['label' => 'Lokasi kejadian', 'field_key' => 'lokasi', 'field_type' => 'text', 'is_required' => true, 'placeholder' => 'Jl. Merdeka depan balai desa'],
                    ['label' => 'Uraian pengaduan', 'field_key' => 'uraian', 'field_type' => 'textarea', 'is_required' => true],
                ],
            ],
        ];

        foreach ($services as $i => $service) {
            $type = ServiceType::firstOrCreate(['slug' => $service['slug']], ['name' => $service['name'], 'description' => $service['description'], 'is_active' => true, 'sort_order' => $i + 1]);
            foreach ($service['requirements'] as $j => $requirement) {
                ServiceRequirement::updateOrCreate(
                    ['service_type_id' => $type->id, 'name' => $requirement['name']],
                    ['description' => $requirement['description'], 'is_required' => $requirement['is_required'], 'allowed_file_types' => ['pdf', 'jpg', 'jpeg', 'png'], 'max_file_size_kb' => 5120, 'sort_order' => $j + 1],
                );
            }
            foreach ($service['fields'] as $j => $field) {
                ServiceTypeField::firstOrCreate(
                    ['service_type_id' => $type->id, 'field_key' => $field['field_key']],
                    [...$field, 'is_active' => true, 'sort_order' => $j + 1],
                );
            }
        }
    }
}
