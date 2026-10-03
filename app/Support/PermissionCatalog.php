<?php

namespace App\Support;

/**
 * The permission vocabulary, in one place. Every guard, the seeder, the migration that
 * retired the old "manage X" strings, the sidebar and the role matrix read this list, so
 * a permission cannot exist in one of them and not the others.
 *
 * Names are "<resource>.<action>". Standard actions map onto the matrix columns; the
 * workflow verbs on service requests and WhatsApp pairing are listed as extra actions.
 */
class PermissionCatalog
{
    public const SUPER_ROLE = 'Super Admin';

    public const STANDARD_ACTIONS = [
        'view' => 'Lihat',
        'create' => 'Tambah',
        'update' => 'Ubah',
        'delete' => 'Hapus',
        'import' => 'Impor',
        'export' => 'Ekspor',
    ];

    /** @return array<string, array{label: string, group: string, description: string, actions: list<string>, extra?: array<string, string>}> */
    public static function resources(): array
    {
        return [
            'dashboard' => ['label' => 'Dashboard', 'group' => 'Pelayanan', 'description' => 'Ringkasan beban kerja dan tren pengajuan.', 'actions' => ['view']],
            'service-requests' => [
                'label' => 'Pengajuan surat', 'group' => 'Pelayanan', 'description' => 'Pengajuan warga beserta berkas dan dokumen hasilnya.',
                'actions' => ['view', 'export'],
                'extra' => [
                    'verify' => 'Verifikasi berkas',
                    'process' => 'Proses pengajuan',
                    'reject' => 'Tolak pengajuan',
                    'complete' => 'Selesaikan pengajuan',
                    'generate-document' => 'Terbitkan dokumen',
                    'upload-document' => 'Unggah dokumen manual',
                    'send-whatsapp' => 'Kirim dokumen via WhatsApp',
                ],
            ],
            'residents' => ['label' => 'Data penduduk', 'group' => 'Data Desa', 'description' => 'Warga terdaftar, termasuk impor dan ekspor berkas.', 'actions' => ['view', 'create', 'update', 'delete', 'import', 'export']],
            'family-cards' => ['label' => 'Kartu keluarga', 'group' => 'Data Desa', 'description' => 'Data KK dan kepala keluarga.', 'actions' => ['view', 'create', 'update', 'delete']],
            'service-types' => ['label' => 'Konfigurasi layanan', 'group' => 'Data Desa', 'description' => 'Jenis surat, syarat berkas, dan isian formulir tiap layanan.', 'actions' => ['view', 'create', 'update', 'delete']],
            'document-templates' => ['label' => 'Template dokumen', 'group' => 'Data Desa', 'description' => 'Template PDF dan penempatan datanya.', 'actions' => ['view', 'create', 'update', 'delete']],
            'announcements' => ['label' => 'Pengumuman', 'group' => 'Data Desa', 'description' => 'Pengumuman yang tampil di situs warga.', 'actions' => ['view', 'create', 'update', 'delete']],
            'village-profile' => ['label' => 'Profil desa', 'group' => 'Data Desa', 'description' => 'Identitas desa, kepala desa, dan penandatangan surat.', 'actions' => ['view', 'create', 'update', 'delete']],
            'users' => ['label' => 'Pengguna', 'group' => 'Sistem', 'description' => 'Akun petugas dan role yang dipegangnya.', 'actions' => ['view', 'create', 'update', 'delete']],
            'roles' => ['label' => 'Role & izin', 'group' => 'Sistem', 'description' => 'Role dan matriks izin ini.', 'actions' => ['view', 'create', 'update', 'delete']],
            'activity-logs' => ['label' => 'Jejak audit', 'group' => 'Sistem', 'description' => 'Riwayat perubahan data oleh petugas.', 'actions' => ['view']],
            'notification-logs' => ['label' => 'Riwayat notifikasi', 'group' => 'Sistem', 'description' => 'Pesan WhatsApp yang pernah dikirim sistem.', 'actions' => ['view']],
            'whatsapp' => ['label' => 'Koneksi WhatsApp', 'group' => 'Sistem', 'description' => 'Status perangkat pengirim notifikasi.', 'actions' => ['view'], 'extra' => ['manage' => 'Sambungkan / putuskan perangkat']],
        ];
    }

    /** Every permission name the application knows. @return list<string> */
    public static function all(): array
    {
        $names = [];
        foreach (self::resources() as $resource => $meta) {
            foreach ($meta['actions'] as $action) {
                $names[] = "$resource.$action";
            }
            foreach (array_keys($meta['extra'] ?? []) as $action) {
                $names[] = "$resource.$action";
            }
        }

        return $names;
    }

    /** Human label for a permission name, e.g. "Data penduduk · Impor". */
    public static function label(string $permission): string
    {
        [$resource, $action] = array_pad(explode('.', $permission, 2), 2, '');
        $meta = self::resources()[$resource] ?? null;
        if (! $meta) {
            return $permission;
        }
        $actionLabel = self::STANDARD_ACTIONS[$action] ?? ($meta['extra'][$action] ?? $action);

        return $meta['label'].' · '.$actionLabel;
    }

    /** Role presets used by the seeder and available as a starting point in the UI. @return array<string, list<string>> */
    public static function presets(): array
    {
        $all = self::all();
        $without = fn (array $prefixes) => array_values(array_filter($all, fn ($p) => ! self::startsWithAny($p, $prefixes)));

        return [
            self::SUPER_ROLE => $all,
            'Admin Desa' => $without(['users.', 'roles.', 'activity-logs.', 'whatsapp.manage']),
            'Petugas' => [
                'dashboard.view',
                'service-requests.view', 'service-requests.export', 'service-requests.verify', 'service-requests.process', 'service-requests.reject',
                'service-requests.complete', 'service-requests.generate-document', 'service-requests.upload-document', 'service-requests.send-whatsapp',
                'residents.view', 'family-cards.view',
            ],
        ];
    }

    /**
     * How the retired "manage X" strings translate. Consumed once by the migration that
     * replaced them, and kept here so the history is readable.
     *
     * @return array<string, list<string>>
     */
    public static function legacyMap(): array
    {
        return [
            'manage users' => ['users.view', 'users.create', 'users.update', 'users.delete'],
            'manage roles' => ['roles.view', 'roles.create', 'roles.update', 'roles.delete'],
            'manage village profiles' => ['village-profile.view', 'village-profile.create', 'village-profile.update', 'village-profile.delete'],
            'manage residents' => ['residents.view', 'residents.create', 'residents.update', 'residents.delete', 'residents.import', 'residents.export'],
            'manage family cards' => ['family-cards.view', 'family-cards.create', 'family-cards.update', 'family-cards.delete'],
            'manage service types' => ['service-types.view', 'service-types.create', 'service-types.update', 'service-types.delete'],
            'manage service requirements' => ['service-types.view', 'service-types.create', 'service-types.update', 'service-types.delete'],
            'manage service fields' => ['service-types.view', 'service-types.create', 'service-types.update', 'service-types.delete'],
            'manage service requests' => ['dashboard.view', 'service-requests.view'],
            'verify service requests' => ['service-requests.verify', 'service-requests.reject'],
            'process service requests' => ['service-requests.process', 'service-requests.reject', 'service-requests.complete'],
            'generate documents' => ['service-requests.generate-document', 'service-requests.send-whatsapp'],
            'upload final documents' => ['service-requests.upload-document'],
            'manage document templates' => ['document-templates.view', 'document-templates.create', 'document-templates.update', 'document-templates.delete'],
            'manage announcements' => ['announcements.view', 'announcements.create', 'announcements.update', 'announcements.delete'],
            'view activity logs' => ['activity-logs.view', 'notification-logs.view'],
            'manage notifications' => ['whatsapp.view', 'whatsapp.manage'],
        ];
    }

    private static function startsWithAny(string $value, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if (str_starts_with($value, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
