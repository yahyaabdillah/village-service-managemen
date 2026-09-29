<?php

namespace App\Support;

use App\Models\User;

/**
 * One description of the admin sidebar, shared by the layout and the post-login
 * redirect. Every admin route is guarded by a permission; keeping both readers on
 * this list means a link is never shown that the viewer cannot open, and nobody is
 * dropped on a screen their role forbids.
 */
class AdminNavigation
{
    /** @return array<string, list<array{permission: string, route: string, patterns: list<string>, icon: string, label: string}>> */
    public static function sections(): array
    {
        return [
            'Pelayanan' => [
                self::link('dashboard.view', 'admin.dashboard', ['admin.dashboard'], 'layout-dashboard', 'Dashboard'),
                self::link('service-requests.view', 'admin.service-requests.index', ['admin.service-requests.*'], 'inbox', 'Pengajuan'),
            ],
            'Data Desa' => [
                self::link('residents.view', 'admin.residents.index', ['admin.residents.*'], 'users', 'Penduduk'),
                self::link('family-cards.view', 'admin.family-cards.index', ['admin.family-cards.*'], 'contact-round', 'Kartu Keluarga'),
                self::link('service-types.view', 'admin.service-types.index', ['admin.service-types.*', 'admin.service-requirements.*', 'admin.service-type-fields.*'], 'grid-2x2-check', 'Konfigurasi Layanan'),
                self::link('document-templates.view', 'admin.document-templates.index', ['admin.document-templates.*'], 'file-pen-line', 'Template Dokumen'),
                self::link('announcements.view', 'admin.announcements.index', ['admin.announcements.*'], 'megaphone', 'Pengumuman'),
                self::link('village-profile.view', 'admin.village-profiles.index', ['admin.village-profiles.*'], 'landmark', 'Profil Desa'),
            ],
            'Sistem' => [
                self::link('users.view', 'admin.users.index', ['admin.users.*'], 'user-cog', 'Pengguna'),
                self::link('roles.view', 'admin.roles.index', ['admin.roles.*'], 'shield-check', 'Role & Izin'),
                self::link('activity-logs.view', 'admin.activity-logs.index', ['admin.activity-logs.*'], 'history', 'Jejak Audit'),
                self::link('notification-logs.view', 'admin.notification-logs.index', ['admin.notification-logs.*'], 'bell-ring', 'Riwayat Notifikasi'),
                self::link('whatsapp.view', 'admin.whatsapp.index', ['admin.whatsapp.*'], 'message-circle-more', 'WhatsApp'),
            ],
        ];
    }

    /**
     * Sections the user may open, with empty ones dropped so no heading hangs over
     * an empty group.
     *
     * @return array<string, list<array{permission: string, route: string, patterns: list<string>, icon: string, label: string}>>
     */
    public static function visibleTo(?User $user): array
    {
        if (! $user) {
            return [];
        }

        return array_filter(array_map(
            fn (array $links) => array_values(array_filter($links, fn (array $link) => $user->can($link['permission']))),
            self::sections()
        ));
    }

    /** First screen the user may open, or null when their roles grant nothing here. */
    public static function landingRoute(?User $user): ?string
    {
        foreach (self::visibleTo($user) as $links) {
            foreach ($links as $link) {
                return route($link['route']);
            }
        }

        return null;
    }

    /** @return array{permission: string, route: string, patterns: list<string>, icon: string, label: string} */
    private static function link(string $permission, string $route, array $patterns, string $icon, string $label): array
    {
        return compact('permission', 'route', 'patterns', 'icon', 'label');
    }
}
