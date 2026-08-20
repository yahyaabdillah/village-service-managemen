<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\ServiceRequest;
use App\Models\ServiceType;
use App\Models\User;
use App\Models\VillageProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VillageServiceMvpTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_home_lists_village_services_and_announcements(): void
    {
        $this->seed();

        $this->get('/')
            ->assertOk()
            ->assertSee('Sistem Layanan Desa')
            ->assertSee('Surat Keterangan Domisili')
            ->assertSee('Cek Status Pengajuan');
    }

    public function test_citizen_can_submit_request_with_dynamic_fields_and_check_status(): void
    {
        Storage::fake('private');
        $this->seed();

        $service = ServiceType::where('slug', 'surat-keterangan-domisili')->firstOrFail();
        $requirement = $service->requirements()->firstOrFail();

        $response = $this->post('/pengajuan', [
            'service_type_id' => $service->id,
            'nik' => '3201010101010001',
            'applicant_name' => 'Yahya Abdillah',
            'phone' => '081234567890',
            'address' => 'Jl. Merdeka No. 1',
            'hamlet' => 'Dusun A',
            'rt' => '001',
            'rw' => '002',
            'fields' => [
                'keperluan' => 'Keperluan administrasi bank',
            ],
            'requirements' => [
                $requirement->id => UploadedFile::fake()->create('ktp.jpg', 100, 'image/jpeg'),
            ],
        ]);

        $request = ServiceRequest::where('nik', '3201010101010001')->firstOrFail();

        $response->assertRedirect(route('requests.success', $request));
        $this->assertStringStartsWith('REQ-', $request->request_code);
        $this->assertSame('submitted', $request->status);
        $this->assertDatabaseHas('service_request_field_values', [
            'service_request_id' => $request->id,
            'field_key' => 'keperluan',
            'value' => 'Keperluan administrasi bank',
        ]);
        $this->assertDatabaseCount('request_files', 1);
        $this->assertDatabaseHas('service_request_status_histories', [
            'service_request_id' => $request->id,
            'to_status' => 'submitted',
            'is_public' => true,
        ]);

        $this->post('/cek-status', [
            'request_code' => $request->request_code,
            'nik' => '3201010101010001',
        ])
            ->assertOk()
            ->assertSee('Pengajuan diterima')
            ->assertSee($request->request_code);
    }

    public function test_admin_status_workflow_requires_final_document_before_completion(): void
    {
        Storage::fake('private');
        $this->seed();

        $admin = User::where('email', 'admin@desa.test')->firstOrFail();
        $request = ServiceRequest::factory()->create([
            'status' => 'submitted',
            'document_source' => null,
        ]);

        $this->actingAs($admin)->patch(route('admin.service-requests.verify', $request), [
            'public_note' => 'Berkas sudah valid.',
        ])->assertRedirect();
        $this->assertSame('verified', $request->fresh()->status);

        $this->actingAs($admin)->patch(route('admin.service-requests.complete', $request), [
            'public_note' => 'Selesai.',
        ])->assertSessionHasErrors('final_document');

        $file = UploadedFile::fake()->create('surat-final.pdf', 120, 'application/pdf');
        $this->actingAs($admin)->post(route('admin.service-requests.manual-document', $request), [
            'document' => $file,
        ])->assertRedirect();

        $this->actingAs($admin)->patch(route('admin.service-requests.complete', $request), [
            'public_note' => 'Dokumen selesai.',
        ])->assertRedirect();

        $request->refresh();
        $this->assertSame('completed', $request->status);
        $this->assertSame('manual', $request->document_source);
        $this->assertNotNull($request->completed_at);
        $this->assertDatabaseHas('generated_documents', [
            'service_request_id' => $request->id,
            'source' => 'manual',
        ]);
        $this->assertDatabaseHas('service_request_status_histories', [
            'service_request_id' => $request->id,
            'to_status' => 'completed',
            'is_public' => true,
        ]);
    }

    public function test_core_schema_seeders_create_roles_permissions_and_village_profile(): void
    {
        $this->seed();

        $this->assertDatabaseHas('roles', ['name' => 'Super Admin']);
        $this->assertDatabaseHas('roles', ['name' => 'Admin Desa']);
        $this->assertDatabaseHas('roles', ['name' => 'Petugas']);
        $this->assertDatabaseHas('permissions', ['name' => 'manage service requests']);
        $this->assertSame(1, VillageProfile::count());
        $this->assertGreaterThanOrEqual(5, ServiceType::count());
        $this->assertGreaterThanOrEqual(1, Announcement::count());
    }
}
