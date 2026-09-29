<?php

use App\Support\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The first permission model was 17 flat "manage X" strings, so a role could not say
 * "may view residents but not delete them". Replace them with "<resource>.<action>"
 * permissions and hand every role (and any directly-permitted user) the equivalent set,
 * so nobody loses access at the moment this runs.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $legacy = PermissionCatalog::legacyMap();

        // 1. Make sure every catalogued permission exists.
        foreach (PermissionCatalog::all() as $name) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $name, 'guard_name' => 'web'],
                ['created_at' => $now, 'updated_at' => $now],
            );
        }
        $ids = DB::table('permissions')->where('guard_name', 'web')->pluck('id', 'name');

        // 2. Translate role grants and direct user grants.
        $legacyIds = DB::table('permissions')->whereIn('name', array_keys($legacy))->pluck('name', 'id');

        foreach (DB::table('role_has_permissions')->whereIn('permission_id', $legacyIds->keys())->get() as $grant) {
            foreach ($legacy[$legacyIds[$grant->permission_id]] as $replacement) {
                DB::table('role_has_permissions')->updateOrInsert(['role_id' => $grant->role_id, 'permission_id' => $ids[$replacement]]);
            }
        }
        foreach (DB::table('model_has_permissions')->whereIn('permission_id', $legacyIds->keys())->get() as $grant) {
            foreach ($legacy[$legacyIds[$grant->permission_id]] as $replacement) {
                DB::table('model_has_permissions')->updateOrInsert([
                    'permission_id' => $ids[$replacement], 'model_type' => $grant->model_type, 'model_id' => $grant->model_id,
                ]);
            }
        }

        // 3. Retire the old strings; the pivot rows cascade.
        DB::table('permissions')->whereIn('id', $legacyIds->keys())->delete();

        // 4. Super Admin keeps everything (it also bypasses checks via Gate::before, but an
        //    explicit grant keeps the matrix truthful).
        if ($superId = DB::table('roles')->where('name', PermissionCatalog::SUPER_ROLE)->value('id')) {
            foreach ($ids as $permissionId) {
                DB::table('role_has_permissions')->updateOrInsert(['role_id' => $superId, 'permission_id' => $permissionId]);
            }
        }

        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // The old strings cannot be reconstructed faithfully from the finer-grained set;
        // restore them as a coarse best effort so a rollback leaves roles usable.
        $now = now();
        foreach (PermissionCatalog::legacyMap() as $legacyName => $replacements) {
            DB::table('permissions')->updateOrInsert(['name' => $legacyName, 'guard_name' => 'web'], ['created_at' => $now, 'updated_at' => $now]);
            $legacyId = DB::table('permissions')->where('name', $legacyName)->value('id');
            $replacementIds = DB::table('permissions')->whereIn('name', $replacements)->pluck('id');
            $roleIds = DB::table('role_has_permissions')->whereIn('permission_id', $replacementIds)->distinct()->pluck('role_id');
            foreach ($roleIds as $roleId) {
                DB::table('role_has_permissions')->updateOrInsert(['role_id' => $roleId, 'permission_id' => $legacyId]);
            }
        }
        DB::table('permissions')->whereIn('name', PermissionCatalog::all())->delete();
        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
