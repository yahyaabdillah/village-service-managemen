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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = ['manage users', 'manage roles', 'manage village profiles', 'manage residents', 'manage family cards', 'manage service types', 'manage service requirements', 'manage service fields', 'manage service requests', 'verify service requests', 'process service requests', 'manage document templates', 'generate documents', 'upload final documents', 'manage announcements', 'view activity logs', 'manage notifications'];
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $super = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $admin = Role::firstOrCreate(['name' => 'Admin Desa', 'guard_name' => 'web']);
        $petugas = Role::firstOrCreate(['name' => 'Petugas', 'guard_name' => 'web']);
        $super->syncPermissions($permissions);
        $admin->syncPermissions(array_filter($permissions, fn ($p) => ! in_array($p, ['manage users', 'manage roles', 'view activity logs', 'manage notifications'])));
        $petugas->syncPermissions(['manage service requests', 'verify service requests', 'process service requests', 'generate documents', 'upload final documents']);
        $user = User::firstOrCreate(['email' => 'admin@desa.test'], ['name' => 'Super Admin', 'password' => Hash::make('password'), 'is_active' => true]);
        $user->assignRole($super);
        $profile = VillageProfile::updateOrCreate(['is_active' => true], ['village_name' => 'Desa Ngringo', 'district' => 'Kecamatan Jaten', 'regency' => 'Kabupaten Karanganyar', 'province' => 'Jawa Tengah', 'address' => 'Desa Ngringo, Kecamatan Jaten, Kabupaten Karanganyar, Jawa Tengah', 'phone' => '-', 'email' => null, 'website' => null, 'village_head_name' => 'Kepala Desa Ngringo', 'default_signer_name' => 'Kepala Desa Ngringo', 'default_signer_title' => 'Kepala Desa Ngringo', 'letterhead_logo_path' => null]);
        $services = [['Surat Keterangan Domisili', 'surat-keterangan-domisili', 'Layanan surat keterangan domisili warga.'], ['Surat Keterangan Usaha', 'surat-keterangan-usaha', 'Layanan surat keterangan usaha.'], ['Surat Keterangan Tidak Mampu', 'surat-keterangan-tidak-mampu', 'Layanan SKTM.'], ['Surat Pengantar KTP/KK', 'surat-pengantar-ktp-kk', 'Layanan pengantar administrasi KTP/KK.'], ['Pengaduan Masyarakat', 'pengaduan-masyarakat', 'Kanal pengaduan warga.']];
        foreach ($services as $i => [$name,$slug,$description]) {
            $service = ServiceType::firstOrCreate(['slug' => $slug], ['name' => $name, 'description' => $description, 'is_active' => true, 'sort_order' => $i + 1]);
            $requirement = $slug === 'surat-pengantar-ktp-kk'
                ? ['name' => 'Kartu Keluarga (KK) atau Akta Kelahiran', 'description' => 'Unggah KK atau akta kelahiran sebagai dokumen pendukung. KTP tidak wajib untuk pengajuan KTP pertama.']
                : ['name' => 'KTP Pemohon', 'description' => 'Foto/scan KTP pemohon.'];
            ServiceRequirement::updateOrCreate(
                ['service_type_id' => $service->id, 'name' => $requirement['name']],
                ['description' => $requirement['description'], 'is_required' => true, 'allowed_file_types' => ['pdf', 'jpg', 'jpeg', 'png', 'docx', 'mp4', 'mov', 'webm'], 'max_file_size_kb' => 5120, 'sort_order' => 1],
            );
            ServiceTypeField::firstOrCreate(['service_type_id' => $service->id, 'field_key' => 'keperluan'], ['label' => 'Keperluan', 'field_type' => 'textarea', 'is_required' => true, 'placeholder' => 'Contoh: keperluan administrasi bank', 'sort_order' => 1]);
        }
        Model::withoutEvents(fn () => app(ProfessionalLetterTemplateService::class)->syncDefaultTemplates($profile));
        $family = FamilyCard::firstOrCreate(['family_card_number' => '3201010101010001'], ['head_of_family_name' => 'Budi Santoso', 'address' => 'Jl. Merdeka No. 1', 'hamlet' => 'Dusun A', 'rt' => '001', 'rw' => '002', 'postal_code' => '12345']);
        Resident::firstOrCreate(['nik' => '3201010101010001'], ['family_card_id' => $family->id, 'name' => 'Budi Santoso', 'gender' => 'male', 'birth_place' => 'Bandung', 'birth_date' => '1990-01-01', 'address' => 'Jl. Merdeka No. 1', 'hamlet' => 'Dusun A', 'rt' => '001', 'rw' => '002', 'religion' => 'Islam', 'marital_status' => 'Kawin', 'occupation' => 'Wiraswasta', 'phone' => '08123456789', 'is_active' => true]);
        Announcement::firstOrCreate(['slug' => 'selamat-datang-di-layanan-desa'], ['title' => 'Selamat Datang di Layanan Desa', 'content' => 'Gunakan layanan online desa untuk mengajukan surat dan mengecek status pengajuan.', 'excerpt' => 'Layanan online desa sudah tersedia.', 'published_at' => now(), 'is_published' => true]);
    }
}
