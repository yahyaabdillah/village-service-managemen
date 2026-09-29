<?php

namespace App\Support;

use App\Models\Announcement;
use App\Models\FamilyCard;
use App\Models\Resident;
use App\Models\ServiceRequirement;
use App\Models\ServiceType;
use App\Models\ServiceTypeField;
use App\Models\User;
use App\Models\VillageProfile;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

/**
 * What the generic admin screens know about each resource: how it is titled, which
 * columns a clerk needs to scan, how each field is edited, and how values are shown.
 * The controller and the three CRUD views read this instead of guessing from column
 * names.
 */
class CrudSchema
{
    public const GENDERS = ['male' => 'Laki-laki', 'female' => 'Perempuan'];

    public const RELIGIONS = ['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu', 'Kepercayaan'];

    public const MARITAL = ['Belum Kawin', 'Kawin', 'Cerai Hidup', 'Cerai Mati'];

    public const FIELD_TYPES = ['text' => 'Teks singkat', 'textarea' => 'Teks panjang', 'number' => 'Angka', 'date' => 'Tanggal', 'email' => 'Email', 'select' => 'Pilihan'];

    /** @return array<string, mixed> */
    public static function resource(string $key): array
    {
        $all = self::all();
        abort_unless(isset($all[$key]), 404);

        return $all[$key];
    }

    public static function exists(string $key): bool
    {
        return isset(self::all()[$key]);
    }

    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        return [
            'residents' => [
                'model' => Resident::class, 'title' => 'Data Penduduk', 'singular' => 'penduduk',
                'description' => 'Warga yang terdaftar di desa. Data ini menjadi rujukan saat memverifikasi pengajuan.',
                'with' => ['familyCard'], 'search' => ['nik', 'name'],
                'columns' => [
                    'nik' => ['label' => 'NIK', 'type' => 'mono'],
                    'name' => ['label' => 'Nama', 'type' => 'title', 'sub' => fn (Resident $r) => self::GENDERS[$r->gender] ?? $r->gender],
                    'family_card' => ['label' => 'No. KK', 'type' => 'mono', 'value' => fn (Resident $r) => $r->familyCard?->family_card_number],
                    'location' => ['label' => 'Dusun · RT/RW', 'value' => fn (Resident $r) => trim(($r->hamlet ? $r->hamlet.' · ' : '').'RT '.($r->rt ?: '-').'/RW '.($r->rw ?: '-'))],
                    'is_active' => ['label' => 'Status', 'type' => 'boolean', 'labels' => ['Aktif', 'Nonaktif']],
                ],
                'sections' => [
                    ['title' => 'Identitas', 'fields' => ['nik', 'name', 'gender', 'birth_place', 'birth_date', 'family_card_id']],
                    ['title' => 'Alamat', 'fields' => ['address', 'hamlet', 'rt', 'rw']],
                    ['title' => 'Data lain', 'fields' => ['religion', 'marital_status', 'occupation', 'phone', 'is_active']],
                ],
                'fields' => [
                    'nik' => ['label' => 'NIK', 'type' => 'text', 'required' => true, 'help' => '16 digit sesuai KTP.', 'attrs' => ['inputmode' => 'numeric', 'maxlength' => 16]],
                    'name' => ['label' => 'Nama lengkap', 'type' => 'text', 'required' => true],
                    'gender' => ['label' => 'Jenis kelamin', 'type' => 'select', 'required' => true, 'options' => self::GENDERS],
                    'birth_place' => ['label' => 'Tempat lahir', 'type' => 'text'],
                    'birth_date' => ['label' => 'Tanggal lahir', 'type' => 'date'],
                    'family_card_id' => ['label' => 'Kartu keluarga', 'type' => 'select', 'placeholder' => 'Belum terhubung ke KK', 'options' => fn () => FamilyCard::orderBy('head_of_family_name')->get()->mapWithKeys(fn ($f) => [$f->id => $f->family_card_number.' · '.$f->head_of_family_name])->all()],
                    'address' => ['label' => 'Alamat', 'type' => 'textarea', 'required' => true],
                    'hamlet' => ['label' => 'Dusun', 'type' => 'text'],
                    'rt' => ['label' => 'RT', 'type' => 'text', 'attrs' => ['inputmode' => 'numeric', 'maxlength' => 3]],
                    'rw' => ['label' => 'RW', 'type' => 'text', 'attrs' => ['inputmode' => 'numeric', 'maxlength' => 3]],
                    'religion' => ['label' => 'Agama', 'type' => 'select', 'options' => array_combine(self::RELIGIONS, self::RELIGIONS), 'placeholder' => 'Pilih agama'],
                    'marital_status' => ['label' => 'Status perkawinan', 'type' => 'select', 'options' => array_combine(self::MARITAL, self::MARITAL), 'placeholder' => 'Pilih status'],
                    'occupation' => ['label' => 'Pekerjaan', 'type' => 'text'],
                    'phone' => ['label' => 'Nomor HP', 'type' => 'phone'],
                    'is_active' => ['label' => 'Data aktif', 'type' => 'boolean', 'default' => true, 'help' => 'Nonaktifkan bila warga pindah atau meninggal.'],
                ],
            ],
            'family-cards' => [
                'model' => FamilyCard::class, 'title' => 'Kartu Keluarga', 'singular' => 'kartu keluarga',
                'description' => 'Data Kartu Keluarga beserta kepala keluarganya.',
                'withCount' => ['residents'], 'search' => ['family_card_number', 'head_of_family_name'],
                'columns' => [
                    'family_card_number' => ['label' => 'No. KK', 'type' => 'mono'],
                    'head_of_family_name' => ['label' => 'Kepala keluarga', 'type' => 'title'],
                    'location' => ['label' => 'Dusun · RT/RW', 'value' => fn (FamilyCard $f) => trim(($f->hamlet ? $f->hamlet.' · ' : '').'RT '.($f->rt ?: '-').'/RW '.($f->rw ?: '-'))],
                    'residents_count' => ['label' => 'Anggota', 'type' => 'number'],
                ],
                'sections' => [['title' => 'Kartu keluarga', 'fields' => ['family_card_number', 'head_of_family_name', 'address', 'hamlet', 'rt', 'rw', 'postal_code']]],
                'fields' => [
                    'family_card_number' => ['label' => 'Nomor KK', 'type' => 'text', 'required' => true, 'help' => '16 digit.', 'attrs' => ['inputmode' => 'numeric', 'maxlength' => 16]],
                    'head_of_family_name' => ['label' => 'Nama kepala keluarga', 'type' => 'text', 'required' => true],
                    'address' => ['label' => 'Alamat', 'type' => 'textarea', 'required' => true],
                    'hamlet' => ['label' => 'Dusun', 'type' => 'text'],
                    'rt' => ['label' => 'RT', 'type' => 'text', 'attrs' => ['inputmode' => 'numeric', 'maxlength' => 3]],
                    'rw' => ['label' => 'RW', 'type' => 'text', 'attrs' => ['inputmode' => 'numeric', 'maxlength' => 3]],
                    'postal_code' => ['label' => 'Kode pos', 'type' => 'text', 'attrs' => ['inputmode' => 'numeric', 'maxlength' => 5]],
                ],
            ],
            'village-profiles' => [
                'model' => VillageProfile::class, 'title' => 'Profil Desa', 'singular' => 'profil desa',
                'description' => 'Identitas desa dan penandatangan yang tercetak pada setiap surat.',
                'search' => ['village_name'],
                'columns' => [
                    'village_name' => ['label' => 'Desa', 'type' => 'title', 'sub' => fn ($p) => trim(($p->district ?: '').', '.($p->regency ?: ''), ', ')],
                    'village_head_name' => ['label' => 'Kepala desa'],
                    'default_signer_name' => ['label' => 'Penandatangan', 'sub' => fn ($p) => $p->default_signer_title],
                    'is_active' => ['label' => 'Status', 'type' => 'boolean', 'labels' => ['Dipakai', 'Tidak dipakai']],
                ],
                'sections' => [
                    ['title' => 'Identitas desa', 'fields' => ['village_name', 'district', 'regency', 'province', 'address']],
                    ['title' => 'Kontak', 'fields' => ['phone', 'email', 'website']],
                    ['title' => 'Penandatangan surat', 'fields' => ['village_head_name', 'village_head_nip', 'default_signer_name', 'default_signer_title', 'is_active']],
                ],
                'fields' => [
                    'village_name' => ['label' => 'Nama desa', 'type' => 'text', 'required' => true, 'placeholder' => 'Desa Ngringo'],
                    'district' => ['label' => 'Kecamatan', 'type' => 'text'],
                    'regency' => ['label' => 'Kabupaten/Kota', 'type' => 'text'],
                    'province' => ['label' => 'Provinsi', 'type' => 'text'],
                    'address' => ['label' => 'Alamat kantor desa', 'type' => 'textarea'],
                    'phone' => ['label' => 'Telepon kantor', 'type' => 'phone'],
                    'email' => ['label' => 'Email', 'type' => 'email'],
                    'website' => ['label' => 'Situs web', 'type' => 'url', 'placeholder' => 'https://'],
                    'village_head_name' => ['label' => 'Nama kepala desa', 'type' => 'text'],
                    'village_head_nip' => ['label' => 'NIP kepala desa', 'type' => 'text'],
                    'default_signer_name' => ['label' => 'Nama penandatangan surat', 'type' => 'text', 'help' => 'Dipakai pada surat bila tidak ditentukan lain.'],
                    'default_signer_title' => ['label' => 'Jabatan penandatangan', 'type' => 'text', 'placeholder' => 'Kepala Desa'],
                    'is_active' => ['label' => 'Profil yang dipakai', 'type' => 'boolean', 'default' => true, 'help' => 'Hanya satu profil yang aktif pada satu waktu.'],
                ],
            ],
            'announcements' => [
                'model' => Announcement::class, 'title' => 'Pengumuman', 'singular' => 'pengumuman',
                'description' => 'Berita dan pengumuman yang tampil di beranda situs warga.',
                'search' => ['title'],
                'columns' => [
                    'title' => ['label' => 'Judul', 'type' => 'title', 'sub' => fn ($a) => \Illuminate\Support\Str::limit($a->excerpt ?: strip_tags($a->content), 80)],
                    'published_at' => ['label' => 'Tanggal terbit', 'type' => 'date'],
                    'is_published' => ['label' => 'Status', 'type' => 'boolean', 'labels' => ['Terbit', 'Draf']],
                ],
                'sections' => [['title' => 'Pengumuman', 'fields' => ['title', 'excerpt', 'content', 'published_at', 'is_published']]],
                'fields' => [
                    'title' => ['label' => 'Judul', 'type' => 'text', 'required' => true],
                    'excerpt' => ['label' => 'Ringkasan', 'type' => 'textarea', 'help' => 'Satu-dua kalimat yang tampil di beranda. Kosongkan untuk memakai awal isi.', 'attrs' => ['rows' => 2]],
                    'content' => ['label' => 'Isi pengumuman', 'type' => 'textarea', 'required' => true, 'attrs' => ['rows' => 8]],
                    'published_at' => ['label' => 'Tanggal terbit', 'type' => 'date', 'help' => 'Kosongkan untuk memakai tanggal hari ini saat diterbitkan.'],
                    'is_published' => ['label' => 'Terbitkan di situs warga', 'type' => 'boolean', 'default' => false],
                ],
            ],
            'users' => [
                'model' => User::class, 'title' => 'Pengguna', 'singular' => 'pengguna',
                'description' => 'Akun petugas yang dapat masuk ke panel ini beserta role yang dipegangnya.',
                'with' => ['roles'], 'search' => ['name', 'email'],
                'columns' => [
                    'name' => ['label' => 'Nama', 'type' => 'title', 'sub' => fn (User $u) => $u->email],
                    'role_label' => ['label' => 'Role'],
                    'is_active' => ['label' => 'Status', 'type' => 'boolean', 'labels' => ['Aktif', 'Dinonaktifkan']],
                ],
                'sections' => [['title' => 'Akun', 'fields' => ['name', 'email', 'password', 'phone', 'is_active', 'roles']]],
                'fields' => [
                    'name' => ['label' => 'Nama lengkap', 'type' => 'text', 'required' => true],
                    'email' => ['label' => 'Alamat email', 'type' => 'email', 'required' => true, 'help' => 'Dipakai untuk masuk.'],
                    'password' => ['label' => 'Kata sandi', 'type' => 'password', 'help' => 'Minimal 8 karakter.'],
                    'phone' => ['label' => 'Nomor HP', 'type' => 'phone'],
                    'is_active' => ['label' => 'Akun aktif', 'type' => 'boolean', 'default' => true, 'help' => 'Akun nonaktif tidak dapat masuk.'],
                    'roles' => ['label' => 'Role', 'type' => 'roles', 'required' => true],
                ],
            ],
            'roles' => [
                'model' => Role::class, 'title' => 'Role & Izin', 'singular' => 'role',
                'description' => 'Kelompok izin yang diberikan kepada pengguna. Centang tindakan yang boleh dilakukan pada tiap menu.',
                'withCount' => ['permissions', 'users'], 'search' => ['name'],
                'columns' => [
                    'name' => ['label' => 'Role', 'type' => 'title'],
                    'permissions_count' => ['label' => 'Izin', 'type' => 'number'],
                    'users_count' => ['label' => 'Pengguna', 'type' => 'number'],
                ],
                'sections' => [['title' => 'Role', 'fields' => ['name', 'permissions']]],
                'fields' => [
                    'name' => ['label' => 'Nama role', 'type' => 'text', 'required' => true, 'placeholder' => 'Contoh: Kasi Pelayanan'],
                    'permissions' => ['label' => 'Izin', 'type' => 'permissions'],
                ],
            ],
            // Creating a service type uses the generic form; configuring it has its own screen.
            'service-types' => [
                'model' => ServiceType::class, 'title' => 'Jenis Layanan', 'singular' => 'jenis layanan',
                'description' => 'Jenis surat yang dapat diajukan warga.',
                'withCount' => ['fields', 'requirements'], 'search' => ['name'],
                'columns' => [
                    'name' => ['label' => 'Layanan', 'type' => 'title', 'sub' => fn ($s) => $s->slug],
                    'fields_count' => ['label' => 'Isian', 'type' => 'number'],
                    'requirements_count' => ['label' => 'Syarat berkas', 'type' => 'number'],
                    'is_active' => ['label' => 'Status', 'type' => 'boolean', 'labels' => ['Aktif', 'Nonaktif']],
                ],
                'sections' => [['title' => 'Jenis layanan', 'fields' => ['name', 'slug', 'description', 'sort_order', 'is_active']]],
                'fields' => [
                    'name' => ['label' => 'Nama layanan', 'type' => 'text', 'required' => true],
                    'slug' => ['label' => 'Slug alamat', 'type' => 'text', 'help' => 'Bagian alamat halaman. Kosongkan untuk dibuat otomatis dari nama.'],
                    'description' => ['label' => 'Deskripsi', 'type' => 'textarea'],
                    'sort_order' => ['label' => 'Urutan tampil', 'type' => 'number'],
                    'is_active' => ['label' => 'Dapat diajukan warga', 'type' => 'boolean', 'default' => true],
                ],
            ],
        ];
    }

    /** Field definitions in the order the form shows them. @return array<string, array<string, mixed>> */
    public static function fields(string $resource): array
    {
        $schema = self::resource($resource);
        $ordered = [];
        foreach ($schema['sections'] as $section) {
            foreach ($section['fields'] as $name) {
                $ordered[$name] = $schema['fields'][$name] + ['name' => $name];
            }
        }

        return $ordered;
    }

    /** Resolve a column into what the index table shows. @return array{text: string, sub: ?string, chip: ?string, mono: bool, num: bool} */
    public static function cell(array $column, Model $item, string $key): array
    {
        $raw = isset($column['value']) ? ($column['value'])($item) : $item->{$key};
        $type = $column['type'] ?? 'text';
        $sub = isset($column['sub']) ? ($column['sub'])($item) : null;

        return match ($type) {
            'boolean' => [
                'text' => $column['labels'][$raw ? 0 : 1] ?? ($raw ? 'Ya' : 'Tidak'),
                'chip' => $raw ? 'success' : 'muted', 'sub' => $sub, 'mono' => false, 'num' => false,
            ],
            'date' => ['text' => $raw ? \Illuminate\Support\Carbon::parse($raw)->translatedFormat('d M Y') : '—', 'chip' => null, 'sub' => $sub, 'mono' => false, 'num' => false],
            'datetime' => ['text' => $raw ? \Illuminate\Support\Carbon::parse($raw)->translatedFormat('d M Y, H:i') : '—', 'chip' => null, 'sub' => $sub, 'mono' => false, 'num' => false],
            'number' => ['text' => $raw === null ? '—' : number_format((float) $raw, 0, ',', '.'), 'chip' => null, 'sub' => $sub, 'mono' => false, 'num' => true],
            'mono' => ['text' => (string) ($raw ?? '—'), 'chip' => null, 'sub' => $sub, 'mono' => true, 'num' => false],
            default => ['text' => (string) (is_array($raw) ? implode(', ', $raw) : ($raw ?? '—')), 'chip' => null, 'sub' => $sub, 'mono' => false, 'num' => false],
        };
    }
}
