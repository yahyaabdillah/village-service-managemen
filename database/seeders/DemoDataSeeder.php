<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\DocumentTemplate;
use App\Models\FamilyCard;
use App\Models\NotificationLog;
use App\Models\Resident;
use App\Models\ServiceRequest;
use App\Models\ServiceType;
use App\Models\User;
use App\Services\DocumentGenerationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Believable village data for a demonstration: families, residents, requests in every
 * state over the past two weeks (with printed letters for the finished ones), the
 * WhatsApp messages those would have produced, and a few announcements.
 *
 * Not part of DatabaseSeeder — run on purpose:  php artisan db:seed --class=DemoDataSeeder
 * Safe to run twice: it skips what it already created.
 */
class DemoDataSeeder extends Seeder
{
    private const HAMLETS = ['Dusun Ngringo Lor', 'Dusun Ngringo Kidul', 'Dusun Krajan', 'Dusun Sumber', 'Dusun Tegalsari'];

    private const FAMILIES = [
        ['Sutrisno', 'Jl. Merdeka No. 12', '001', '002'], ['Wahyudi', 'Jl. Kenanga No. 4', '002', '002'],
        ['Slamet Riyadi', 'Jl. Melati No. 8', '003', '001'], ['Suparman', 'Gg. Mawar No. 2', '001', '003'],
        ['Hartono', 'Jl. Raya Ngringo No. 21', '004', '002'], ['Joko Susilo', 'Jl. Anggrek No. 15', '002', '004'],
        ['Mulyadi', 'Jl. Kartini No. 9', '005', '001'], ['Sugeng Prasetyo', 'Jl. Pemuda No. 30', '003', '003'],
        ['Bambang Hermawan', 'Jl. Diponegoro No. 7', '001', '004'], ['Ahmad Fauzi', 'Gg. Dahlia No. 5', '004', '001'],
        ['Rahmat Hidayat', 'Jl. Sudirman No. 18', '002', '003'], ['Widodo', 'Jl. Cempaka No. 3', '005', '002'],
    ];

    private const MEMBERS = [
        ['Siti Aminah', 'female', 'Ibu Rumah Tangga'], ['Dewi Lestari', 'female', 'Guru'], ['Agus Setiawan', 'male', 'Buruh Pabrik'],
        ['Rina Wulandari', 'female', 'Pedagang'], ['Eko Prasetyo', 'male', 'Petani'], ['Sri Rahayu', 'female', 'Perawat'],
        ['Andi Saputra', 'male', 'Sopir'], ['Nur Hidayah', 'female', 'Mahasiswa'], ['Yulianto', 'male', 'Wiraswasta'],
        ['Fitri Handayani', 'female', 'Karyawan Swasta'], ['Dimas Pratama', 'male', 'Pelajar'], ['Lina Marlina', 'female', 'Penjahit'],
        ['Hendra Gunawan', 'male', 'Tukang Bangunan'], ['Ratna Sari', 'female', 'Bidan'], ['Taufik Hidayat', 'male', 'Montir'],
        ['Maya Anggraini', 'female', 'Kasir'], ['Rudi Hartono', 'male', 'Satpam'], ['Indah Permata', 'female', 'Guru PAUD'],
        ['Fajar Nugroho', 'male', 'Ojek Daring'], ['Wulan Safitri', 'female', 'Pedagang'], ['Arif Rahman', 'male', 'PNS'],
        ['Puji Astuti', 'female', 'Ibu Rumah Tangga'], ['Galih Permadi', 'male', 'Mahasiswa'], ['Endang Susilowati', 'female', 'Petani'],
        ['Bagus Wicaksono', 'male', 'Karyawan Swasta'], ['Novi Rahmawati', 'female', 'Perias'], ['Heri Kurniawan', 'male', 'Peternak'],
        ['Anisa Fitriani', 'female', 'Apoteker'],
    ];

    private const REJECTIONS = [
        'Foto KTP buram sehingga NIK tidak terbaca. Mohon unggah ulang dengan pencahayaan yang cukup.',
        'Nomor KK yang diisi tidak sesuai dengan data kependudukan desa. Periksa kembali kartu keluarga Anda.',
        'Pemohon tidak terdaftar sebagai warga desa ini. Silakan mengajukan di desa domisili sesuai KTP.',
    ];

    public function run(): void
    {
        mt_srand(2026);
        $admin = User::where('email', 'admin@desa.test')->first();
        $families = $this->families();
        $residents = $this->residents($families);
        $this->announcements();

        if (ServiceRequest::where('request_code', 'like', 'REQ-%-DM%')->exists()) {
            $this->command?->info('Demo requests already present; skipped.');

            return;
        }

        $services = ServiceType::with('fields')->where('is_active', true)->orderBy('sort_order')->get()->values();
        if ($services->isEmpty()) {
            return;
        }

        // Oldest first so the codes and timelines read naturally.
        $plan = [
            'completed', 'completed', 'rejected', 'completed', 'completed', 'processing', 'completed', 'rejected',
            'completed', 'verified', 'completed', 'processing', 'completed', 'verified', 'processing', 'rejected',
            'verified', 'processing', 'verified', 'submitted', 'submitted', 'submitted', 'submitted', 'submitted',
        ];
        $generator = app(DocumentGenerationService::class);
        $letterSeq = 41;

        foreach ($plan as $index => $status) {
            $resident = $residents[$index % count($residents)];
            $service = $services[$index % $services->count()];
            $createdAt = now()->subDays(14)->addHours($index * 13 + mt_rand(0, 5))->setSeconds(0);
            if ($createdAt->isFuture()) {
                $createdAt = now()->subMinutes(30 + $index);
            }
            $code = 'REQ-'.$createdAt->format('Ymd').'-DM'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT);

            Model::withoutEvents(function () use ($resident, $service, $status, $createdAt, $code, $admin, $generator, &$letterSeq, $index) {
                $request = ServiceRequest::create([
                    'request_code' => $code,
                    'service_type_id' => $service->id,
                    'resident_id' => $resident->id,
                    'nik' => $resident->nik,
                    'applicant_name' => $resident->name,
                    'phone' => $resident->phone,
                    'address' => $resident->address,
                    'hamlet' => $resident->hamlet,
                    'rt' => $resident->rt,
                    'rw' => $resident->rw,
                    'status' => 'submitted',
                    'submitted_at' => $createdAt,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);

                foreach ($service->fields as $field) {
                    $request->fieldValues()->create([
                        'service_type_field_id' => $field->id,
                        'field_key' => $field->field_key,
                        'label' => $field->label,
                        'value' => $this->answer($field, $resident),
                    ]);
                }

                $this->history($request, null, 'submitted', 'Pengajuan diterima. Kode pengajuan dikirim ke WhatsApp Anda.', $createdAt, null);
                $this->notify($request, $createdAt, "Pengajuan {$service->name} Anda diterima dengan kode *{$code}*. Simpan kode ini untuk mengecek status.", $index % 9 === 4 ? 'failed' : 'sent');

                $steps = match ($status) {
                    'submitted' => [],
                    'verified' => ['verified'],
                    'processing' => ['verified', 'processing'],
                    'completed' => ['verified', 'processing', 'completed'],
                    'rejected' => ['rejected'],
                };
                $at = $createdAt->copy();
                $from = 'submitted';
                foreach ($steps as $step) {
                    $at = $at->copy()->addHours(mt_rand(3, 30));
                    if ($at->isFuture()) {
                        $at = now()->subMinutes(mt_rand(5, 90));
                    }
                    $note = match ($step) {
                        'verified' => 'Berkas telah diperiksa dan dinyatakan lengkap.',
                        'processing' => 'Surat sedang disiapkan untuk ditandatangani.',
                        'completed' => 'Surat selesai dan dapat diunduh.',
                        'rejected' => self::REJECTIONS[$index % count(self::REJECTIONS)],
                    };
                    $update = ['status' => $step, 'updated_at' => $at];
                    $update[match ($step) { 'verified' => 'verified_at', 'processing' => 'processed_at', 'completed' => 'completed_at', 'rejected' => 'rejected_at' }] = $at;
                    if ($step === 'rejected') {
                        $update['rejection_reason'] = $note;
                    }
                    if ($step === 'completed') {
                        $update['letter_number'] = sprintf('470/%03d/%s/%s', $letterSeq++, $this->romanMonth((int) $at->format('n')), $at->format('Y'));
                    }
                    $request->forceFill($update)->saveQuietly();
                    $this->history($request, $from, $step, $note, $at, $admin?->id);
                    $this->notify($request, $at, 'Status pengajuan *'.$code.'*: '.ServiceRequest::statuses()[$step].'. '.$note);
                    $from = $step;
                }

                if ($status === 'completed') {
                    $template = DocumentTemplate::where('service_type_id', $service->id)->where('is_default', true)->where('is_active', true)->first();
                    if ($template) {
                        try {
                            $document = $generator->generate($request->fresh(), $template);
                            $document->forceFill(['generated_at' => $at, 'generated_by' => $admin?->id, 'created_at' => $at])->saveQuietly();
                        } catch (\Throwable $exception) {
                            $this->command?->warn("Surat untuk {$code} tidak dibuat: ".$exception->getMessage());
                        }
                    }
                }
            });
        }

        $this->command?->info('Demo data seeded: '.count($plan).' requests.');
    }

    /** @return array<int, FamilyCard> */
    private function families(): array
    {
        $cards = [];
        foreach (self::FAMILIES as $i => [$head, $address, $rt, $rw]) {
            $hamlet = self::HAMLETS[$i % count(self::HAMLETS)];
            $cards[] = FamilyCard::firstOrCreate(
                ['family_card_number' => '33131001'.str_pad((string) (1000 + $i), 8, '0', STR_PAD_LEFT)],
                ['head_of_family_name' => $head, 'address' => $address, 'hamlet' => $hamlet, 'rt' => $rt, 'rw' => $rw, 'postal_code' => '57772'],
            );
        }

        return $cards;
    }

    /** @return array<int, Resident> */
    private function residents(array $families): array
    {
        $residents = [];
        $people = self::MEMBERS;
        foreach (self::FAMILIES as $i => [$head]) {
            array_splice($people, $i * 3, 0, [[$head, 'male', ['Petani', 'Wiraswasta', 'Buruh Tani', 'Pedagang', 'PNS'][$i % 5]]]);
        }

        foreach ($people as $i => [$name, $gender, $occupation]) {
            $family = $families[intdiv($i, 3) % count($families)];
            $birth = Carbon::create(1958 + ($i * 7) % 48, 1 + ($i * 5) % 12, 1 + ($i * 11) % 28);
            $residents[] = Resident::firstOrCreate(
                ['nik' => '3313'.$birth->format('dmy').str_pad((string) (1 + $i), 4, '0', STR_PAD_LEFT).($gender === 'female' ? '2' : '1')],
                [
                    'family_card_id' => $family->id,
                    'name' => $name,
                    'gender' => $gender,
                    'birth_place' => ['Karanganyar', 'Surakarta', 'Sukoharjo', 'Sragen', 'Boyolali'][$i % 5],
                    'birth_date' => $birth,
                    'address' => $family->address,
                    'hamlet' => $family->hamlet,
                    'rt' => $family->rt,
                    'rw' => $family->rw,
                    'religion' => ['Islam', 'Islam', 'Islam', 'Kristen', 'Katolik'][$i % 5],
                    'marital_status' => $birth->age < 24 ? 'Belum Kawin' : ['Kawin', 'Kawin', 'Belum Kawin', 'Cerai Mati'][$i % 4],
                    'occupation' => $occupation,
                    'phone' => '+62812'.str_pad((string) (34000000 + $i * 7919), 8, '0', STR_PAD_LEFT),
                    'is_active' => true,
                ],
            );
        }

        return $residents;
    }

    private function announcements(): void
    {
        $items = [
            ['jadwal-pelayanan-kantor-desa', 'Jadwal Pelayanan Kantor Desa', 'Kantor desa melayani warga Senin–Kamis pukul 08.00–15.00 dan Jumat pukul 08.00–11.00. Pengajuan surat secara daring tetap diterima setiap saat dan diproses pada jam kerja berikutnya.', 'Senin–Kamis 08.00–15.00, Jumat 08.00–11.00. Pengajuan daring diterima 24 jam.', 12],
            ['posyandu-balita-oktober', 'Posyandu Balita Bulan Oktober', "Posyandu balita dilaksanakan di setiap dusun pada pekan pertama Oktober:\n\n- Ngringo Lor: Senin, 5 Oktober, Balai RW 02\n- Ngringo Kidul: Selasa, 6 Oktober, rumah Bu Sri\n- Krajan: Rabu, 7 Oktober, Balai Dusun\n\nBawa buku KIA dan datang sebelum pukul 10.00.", 'Jadwal penimbangan dan imunisasi balita di setiap dusun pada pekan pertama Oktober.', 5],
            ['kerja-bakti-menyambut-musim-hujan', 'Kerja Bakti Menyambut Musim Hujan', 'Seluruh warga diajak membersihkan saluran air di lingkungan masing-masing pada hari Minggu, 4 Oktober, mulai pukul 06.30. Ketua RT menyiapkan peralatan; desa menyediakan konsumsi.', 'Minggu, 4 Oktober pukul 06.30 — bersihkan saluran air di lingkungan masing-masing.', 2],
        ];
        foreach ($items as [$slug, $title, $content, $excerpt, $daysAgo]) {
            Announcement::firstOrCreate(['slug' => $slug], [
                'title' => $title, 'content' => $content, 'excerpt' => $excerpt,
                'published_at' => now()->subDays($daysAgo)->setTime(9, 0), 'is_published' => true,
            ]);
        }
    }

    private function answer(object $field, Resident $resident): string
    {
        return match ($field->field_key) {
            'tempat_lahir' => $resident->birth_place,
            'tanggal_lahir' => $resident->birth_date->format('Y-m-d'),
            'jenis_kelamin' => $resident->gender === 'female' ? 'Perempuan' : 'Laki-laki',
            'agama' => $resident->religion,
            'pekerjaan' => $resident->occupation,
            'domisili_sejak' => (string) (2005 + $resident->id % 15),
            'keperluan' => $field->field_type === 'select' ? ($field->options[$resident->id % count($field->options)] ?? '') : ['Persyaratan pembukaan rekening bank', 'Pendaftaran sekolah anak', 'Melamar pekerjaan', 'Pengurusan BPJS', 'Persyaratan pernikahan'][$resident->id % 5],
            'nama_usaha' => ['Warung Makan Barokah', 'Toko Kelontong Sumber Rejeki', 'Bengkel Motor Jaya', 'Laundry Bersih Wangi', 'Konter Pulsa Amanah'][$resident->id % 5],
            'bidang_usaha' => ['Kuliner', 'Perdagangan', 'Jasa', 'Jasa', 'Perdagangan'][$resident->id % 5],
            'alamat_usaha' => $resident->address.', '.$resident->hamlet,
            'usaha_sejak' => (string) (2015 + $resident->id % 9),
            'penghasilan' => (string) (900000 + ($resident->id % 6) * 250000),
            'jumlah_tanggungan' => (string) (1 + $resident->id % 4),
            'nama_kepala_keluarga' => $resident->familyCard?->head_of_family_name ?? '',
            'jenis_permohonan' => $field->options[$resident->id % count($field->options)] ?? '',
            'nomor_kk' => $resident->familyCard?->family_card_number ?? '',
            'perubahan' => $resident->id % 2 ? 'Perubahan alamat setelah pindah rumah dalam satu desa.' : '',
            'kategori' => $field->options[$resident->id % count($field->options)] ?? '',
            'lokasi' => ['Jl. Merdeka depan balai desa', 'Jembatan Dusun Krajan', 'Lampu jalan RT 03 RW 01', 'Saluran air Dusun Sumber'][$resident->id % 4],
            'uraian' => 'Lampu penerangan jalan padam sejak seminggu lalu sehingga jalan gelap di malam hari. Mohon segera diperbaiki.',
            default => $field->field_type === 'select' ? ($field->options[0] ?? '') : ($field->placeholder ?: $field->label),
        };
    }

    private function history(ServiceRequest $request, ?string $from, string $to, string $note, Carbon $at, ?int $actor): void
    {
        $request->statusHistories()->create([
            'from_status' => $from, 'to_status' => $to, 'note' => $note, 'is_public' => true,
            'changed_by' => $actor, 'created_at' => $at,
        ]);
    }

    private function notify(ServiceRequest $request, Carbon $at, string $message, string $status = 'sent'): void
    {
        NotificationLog::create([
            'service_request_id' => $request->id,
            'channel' => 'whatsapp',
            'recipient' => $request->phone,
            'message' => $message,
            'status' => $status,
            'error_message' => $status === 'failed' ? 'Nomor tidak terdaftar di WhatsApp.' : null,
            'sent_at' => $status === 'sent' ? $at : null,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    private function romanMonth(int $month): string
    {
        return ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'][$month - 1];
    }
}
