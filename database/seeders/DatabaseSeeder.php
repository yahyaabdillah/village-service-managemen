<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\FamilyCard;
use App\Models\Resident;
use App\Models\ServiceRequirement;
use App\Models\ServiceType;
use App\Models\ServiceTypeField;
use App\Models\User;
use App\Models\VillageProfile;
use App\Services\ProfessionalLetterTemplateService;
use App\Support\PermissionCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PermissionCatalog::all() as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        foreach (PermissionCatalog::presets() as $roleName => $permissions) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web'])->syncPermissions($permissions);
        }

        $user = User::firstOrCreate(['email' => 'admin@desa.test'], ['name' => 'Super Admin', 'password' => Hash::make('password'), 'is_active' => true]);
        $user->assignRole(PermissionCatalog::SUPER_ROLE);

        $profile = VillageProfile::updateOrCreate(['is_active' => true], ['village_name' => 'Desa Ngringo', 'district' => 'Kecamatan Jaten', 'regency' => 'Kabupaten Karanganyar', 'province' => 'Jawa Tengah', 'address' => 'Desa Ngringo, Kecamatan Jaten, Kabupaten Karanganyar, Jawa Tengah', 'phone' => '-', 'email' => null, 'website' => null, 'village_head_name' => 'Kepala Desa Ngringo', 'default_signer_name' => 'Kepala Desa Ngringo', 'default_signer_title' => 'Kepala Desa Ngringo', 'letterhead_logo_path' => null]);

        $this->call(ServiceCatalogSeeder::class);

        Model::withoutEvents(fn () => app(ProfessionalLetterTemplateService::class)->syncDefaultTemplates($profile));

        $family = FamilyCard::firstOrCreate(['family_card_number' => '3313100101010001'], ['head_of_family_name' => 'Budi Santoso', 'address' => 'Jl. Merdeka No. 1', 'hamlet' => 'Dusun Ngringo Lor', 'rt' => '001', 'rw' => '002', 'postal_code' => '57772']);
        Resident::firstOrCreate(['nik' => '3313100101010001'], ['family_card_id' => $family->id, 'name' => 'Budi Santoso', 'gender' => 'male', 'birth_place' => 'Karanganyar', 'birth_date' => '1990-01-01', 'address' => 'Jl. Merdeka No. 1', 'hamlet' => 'Dusun Ngringo Lor', 'rt' => '001', 'rw' => '002', 'religion' => 'Islam', 'marital_status' => 'Kawin', 'occupation' => 'Wiraswasta', 'phone' => '08123456789', 'is_active' => true]);
        Announcement::firstOrCreate(['slug' => 'layanan-surat-daring-telah-tersedia'], ['title' => 'Layanan Surat Daring Telah Tersedia', 'content' => "Warga kini dapat mengajukan surat keterangan dari rumah melalui situs ini. Pilih jenis layanan, lengkapi data dan berkas, lalu pantau prosesnya dengan kode pengajuan yang dikirim ke WhatsApp Anda.\n\nDokumen yang telah ditandatangani dapat diunduh langsung setelah pengajuan dinyatakan selesai.", 'excerpt' => 'Ajukan surat keterangan dari rumah dan pantau prosesnya secara daring.', 'published_at' => now(), 'is_published' => true]);
    }
}
