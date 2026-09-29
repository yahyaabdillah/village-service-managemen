<?php

namespace App\Support;

use App\Models\ServiceRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

/**
 * Turns an activity-log row into what a village clerk can read: who did what to which
 * record, and which values changed. The database keeps the raw attributes; only the
 * presentation is translated.
 */
class AuditPresenter
{
    /** Model basename → noun, the columns that identify a row, and where it can be opened. */
    public const SUBJECTS = [
        'ServiceRequest' => ['label' => 'Pengajuan', 'identity' => ['request_code', 'applicant_name'], 'route' => 'admin.service-requests.show'],
        'Resident' => ['label' => 'Penduduk', 'identity' => ['name', 'nik']],
        'FamilyCard' => ['label' => 'Kartu keluarga', 'identity' => ['head_of_family_name', 'family_card_number']],
        'Announcement' => ['label' => 'Pengumuman', 'identity' => ['title']],
        'VillageProfile' => ['label' => 'Profil desa', 'identity' => ['village_name']],
        'ServiceType' => ['label' => 'Layanan', 'identity' => ['name']],
        'ServiceTypeField' => ['label' => 'Isian formulir', 'identity' => ['label']],
        'ServiceRequirement' => ['label' => 'Syarat berkas', 'identity' => ['name']],
        'DocumentTemplate' => ['label' => 'Template surat', 'identity' => ['name']],
        'TemplateField' => ['label' => 'Teks template', 'identity' => ['label']],
        'GeneratedDocument' => ['label' => 'Dokumen surat', 'identity' => ['original_file_name']],
        'RequestFile' => ['label' => 'Berkas pengajuan', 'identity' => ['original_name']],
        'ServiceRequestFieldValue' => ['label' => 'Jawaban formulir', 'identity' => ['label']],
        'User' => ['label' => 'Pengguna', 'identity' => ['name', 'email']],
        'Role' => ['label' => 'Role', 'identity' => ['name']],
    ];

    public const EVENTS = [
        'created' => ['label' => 'Menambahkan', 'tone' => 'success', 'icon' => 'circle-plus'],
        'updated' => ['label' => 'Mengubah', 'tone' => 'info', 'icon' => 'pencil'],
        'deleted' => ['label' => 'Menghapus', 'tone' => 'danger', 'icon' => 'trash-2'],
        'restored' => ['label' => 'Memulihkan', 'tone' => 'progress', 'icon' => 'history'],
    ];

    /** Columns never worth showing in a diff. */
    public const HIDDEN = [
        'id', 'created_at', 'updated_at', 'deleted_at', 'created_by', 'updated_by', 'deleted_by',
        'password', 'remember_token', 'email_verified_at', 'two_factor_secret', 'two_factor_recovery_codes',
        'checksum', 'file_path', 'template_file_path', 'uploaded_document_path', 'generated_document_path',
        'letterhead_logo_path', 'mapping_config', 'batch_uuid',
    ];

    private const FIELDS = [
        'name' => 'Nama', 'email' => 'Email', 'is_active' => 'Aktif', 'status' => 'Status', 'title' => 'Judul',
        'body' => 'Isi', 'content' => 'Isi', 'description' => 'Deskripsi', 'slug' => 'Alamat halaman', 'sort_order' => 'Urutan',
        'nik' => 'NIK', 'phone' => 'Nomor HP', 'address' => 'Alamat', 'hamlet' => 'Dusun', 'rt' => 'RT', 'rw' => 'RW',
        'gender' => 'Jenis kelamin', 'birth_place' => 'Tempat lahir', 'birth_date' => 'Tanggal lahir', 'religion' => 'Agama',
        'marital_status' => 'Status perkawinan', 'occupation' => 'Pekerjaan', 'family_card_id' => 'Kartu keluarga',
        'family_card_number' => 'Nomor KK', 'head_of_family_name' => 'Kepala keluarga',
        'applicant_name' => 'Nama pemohon', 'request_code' => 'Kode pengajuan', 'letter_number' => 'Nomor surat',
        'rejection_reason' => 'Alasan penolakan', 'document_source' => 'Sumber dokumen', 'completed_at' => 'Selesai pada',
        'submitted_at' => 'Diajukan pada', 'verified_at' => 'Diverifikasi pada', 'processed_at' => 'Diproses pada',
        'service_type_id' => 'Layanan', 'document_template_id' => 'Template', 'resident_id' => 'Penduduk',
        'label' => 'Label', 'field_key' => 'Kunci data', 'field_type' => 'Jenis jawaban', 'is_required' => 'Wajib',
        'options' => 'Pilihan', 'placeholder' => 'Contoh jawaban', 'help_text' => 'Petunjuk',
        'allowed_file_types' => 'Jenis berkas', 'max_file_size_kb' => 'Ukuran maksimal (KB)',
        'version' => 'Versi', 'is_default' => 'Dipakai', 'page_count' => 'Jumlah halaman', 'original_file_name' => 'Nama file',
        'validated_at' => 'Divalidasi pada', 'variable_key' => 'Data', 'page_number' => 'Halaman',
        'x_position' => 'Dari kiri', 'y_position' => 'Dari atas', 'width' => 'Lebar', 'height' => 'Tinggi',
        'font_size' => 'Ukuran huruf', 'font_weight' => 'Tebal', 'text_align' => 'Perataan', 'text_color' => 'Warna',
        'village_name' => 'Nama desa', 'district' => 'Kecamatan', 'regency' => 'Kabupaten', 'province' => 'Provinsi',
        'postal_code' => 'Kode pos', 'website' => 'Situs', 'village_head_name' => 'Kepala desa', 'village_head_nip' => 'NIP kepala desa',
        'default_signer_name' => 'Penandatangan', 'default_signer_title' => 'Jabatan penandatangan',
        'published_at' => 'Terbit pada', 'is_published' => 'Dipublikasikan', 'roles' => 'Role', 'permissions' => 'Izin',
        'value' => 'Jawaban', 'file_size' => 'Ukuran file', 'mime_type' => 'Jenis file', 'source' => 'Sumber',
    ];

    /** @return array{label: string, name: string, url: ?string} */
    public static function subject(Activity $activity): array
    {
        $type = class_basename((string) $activity->subject_type);
        $meta = self::SUBJECTS[$type] ?? ['label' => $type ?: 'Data', 'identity' => []];
        $attributes = (array) ($activity->properties['attributes'] ?? []);
        $old = (array) ($activity->properties['old'] ?? []);

        $name = collect($meta['identity'])
            ->map(fn ($key) => $attributes[$key] ?? $old[$key] ?? null)
            ->filter()
            ->implode(' · ');

        $url = null;
        if (isset($meta['route']) && $activity->subject_id && $activity->event !== 'deleted') {
            $url = route($meta['route'], $activity->subject_id);
        }

        return ['label' => $meta['label'], 'name' => $name ?: '#'.$activity->subject_id, 'url' => $url];
    }

    /** @return array{label: string, tone: string, icon: string} */
    public static function event(Activity $activity): array
    {
        return self::EVENTS[$activity->event] ?? ['label' => Str::headline((string) $activity->event), 'tone' => 'muted', 'icon' => 'history'];
    }

    public static function actor(Activity $activity): string
    {
        return $activity->causer?->name ?? ($activity->causer_id ? 'Pengguna #'.$activity->causer_id : 'Sistem');
    }

    /**
     * Changed values as [label, old, new]. Only "updated" rows carry an old snapshot;
     * created and deleted rows show the record's main fields instead.
     *
     * @return array<int, array{label: string, old: ?string, new: ?string}>
     */
    public static function changes(Activity $activity, int $limit = 8): array
    {
        $attributes = (array) ($activity->properties['attributes'] ?? []);
        $old = (array) ($activity->properties['old'] ?? []);
        $type = class_basename((string) $activity->subject_type);

        if ($activity->event === 'updated') {
            $keys = array_keys($old);
        } else {
            $keys = array_keys($attributes);
        }

        $rows = [];
        foreach ($keys as $key) {
            if (in_array($key, self::HIDDEN, true) || (str_ends_with($key, '_id') && ! isset(self::FIELDS[$key]))) {
                continue;
            }
            $before = $activity->event === 'updated' ? self::value($key, $old[$key] ?? null, $type) : null;
            $after = self::value($key, $attributes[$key] ?? null, $type);
            if ($activity->event === 'updated' && $before === $after) {
                continue;
            }
            if ($activity->event !== 'updated' && ($after === null || $after === '')) {
                continue;
            }
            $rows[] = ['label' => self::FIELDS[$key] ?? Str::headline($key), 'old' => $before, 'new' => $after];
            if (count($rows) >= $limit) {
                break;
            }
        }

        return $rows;
    }

    public static function value(string $key, mixed $value, string $type = ''): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_bool($value) || in_array($key, ['is_active', 'is_required', 'is_default', 'is_published'], true)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'Ya' : 'Tidak';
        }
        if ($key === 'status' && $type === 'ServiceRequest') {
            return ServiceRequest::statuses()[$value] ?? (string) $value;
        }
        if ($key === 'gender') {
            return CrudSchema::GENDERS[$value] ?? (string) $value;
        }
        if ($key === 'field_type') {
            return CrudSchema::FIELD_TYPES[$value] ?? (string) $value;
        }
        if (is_array($value)) {
            $flat = array_map(fn ($item) => is_scalar($item) ? (string) $item : json_encode($item, JSON_UNESCAPED_UNICODE), $value);

            return Str::limit(implode(', ', $flat), 120);
        }
        if (str_ends_with($key, '_at') || str_ends_with($key, '_date')) {
            try {
                return Carbon::parse((string) $value)->translatedFormat(str_ends_with($key, '_date') ? 'd M Y' : 'd M Y H:i');
            } catch (\Throwable) {
                return (string) $value;
            }
        }

        return Str::limit(trim((string) $value), 120);
    }

    /** @return array<string, string> class basename → label, for the filter */
    public static function subjectOptions(): array
    {
        return collect(self::SUBJECTS)->map(fn ($meta) => $meta['label'])->sort()->all();
    }
}
