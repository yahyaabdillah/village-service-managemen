<?php

use App\Support\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds the "Ekspor" action to Pengajuan surat (the PDF report button). Any role that
 * could already view requests gets it automatically, so the report is not a surprise
 * 403 for whoever used to have full access to that screen.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::table('permissions')->updateOrInsert(
            ['name' => 'service-requests.export', 'guard_name' => 'web'],
            ['created_at' => $now, 'updated_at' => $now],
        );
        $exportId = DB::table('permissions')->where('name', 'service-requests.export')->value('id');
        $viewId = DB::table('permissions')->where('name', 'service-requests.view')->value('id');

        if ($exportId && $viewId) {
            $roleIds = DB::table('role_has_permissions')->where('permission_id', $viewId)->pluck('role_id');
            foreach ($roleIds as $roleId) {
                DB::table('role_has_permissions')->updateOrInsert(['role_id' => $roleId, 'permission_id' => $exportId]);
            }

            $userGrants = DB::table('model_has_permissions')->where('permission_id', $viewId)->get();
            foreach ($userGrants as $grant) {
                DB::table('model_has_permissions')->updateOrInsert([
                    'permission_id' => $exportId, 'model_type' => $grant->model_type, 'model_id' => $grant->model_id,
                ]);
            }
        }

        if ($superId = DB::table('roles')->where('name', PermissionCatalog::SUPER_ROLE)->value('id')) {
            if ($exportId) {
                DB::table('role_has_permissions')->updateOrInsert(['role_id' => $superId, 'permission_id' => $exportId]);
            }
        }

        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('permissions')->where('name', 'service-requests.export')->delete();
        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
