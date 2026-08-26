<?php

namespace Tests\Feature;

use App\Models\DocumentTemplate;
use App\Models\FamilyCard;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestStatusHistory;
use App\Models\ServiceType;
use App\Models\TemplateField;
use App\Models\User;
use App\Services\DocumentGenerationService;
use App\Services\ResidentExcelService;
use FPDF;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_disk_is_configured_for_non_public_documents(): void
    {
        $this->assertSame('local', config('filesystems.disks.private.driver'));
        $this->assertStringEndsWith(
            'storage/app/private',
            str_replace('\\', '/', config('filesystems.disks.private.root'))
        );
        $this->assertFalse(config('filesystems.disks.private.serve', true));
    }

    public function test_public_status_only_displays_public_history_entries(): void
    {
        $this->seed();
        $request = ServiceRequest::factory()->create([
            'nik' => '3201010101010001',
            'status' => 'processing',
        ]);

        ServiceRequestStatusHistory::create([
            'service_request_id' => $request->id,
            'from_status' => 'submitted',
            'to_status' => 'processing',
            'note' => 'Catatan publik aman.',
            'is_public' => true,
        ]);
        ServiceRequestStatusHistory::create([
            'service_request_id' => $request->id,
            'from_status' => 'processing',
            'to_status' => 'processing',
            'note' => 'INTERNAL: verifikasi manual dokumen sensitif.',
            'is_public' => false,
        ]);

        $this->post(route('status.check'), [
            'request_code' => $request->request_code,
            'nik' => '3201010101010001',
        ])
            ->assertOk()
            ->assertSee('Catatan publik aman.')
            ->assertDontSee('INTERNAL: verifikasi manual dokumen sensitif.');
    }

    public function test_inactive_service_types_are_not_publicly_accessible_or_submittable(): void
    {
        $this->seed();
        $service = ServiceType::where('slug', 'surat-keterangan-domisili')->firstOrFail();
        $service->update(['is_active' => false]);

        $this->get(route('services.show', $service))->assertNotFound();
        $this->get(route('requests.create', $service))->assertNotFound();

        $this->post(route('requests.store'), [
            'service_type_id' => $service->id,
            'nik' => '3201010101010001',
            'applicant_name' => 'Yahya Abdillah',
            'phone' => '081234567890',
            'address' => 'Jl. Merdeka No. 1',
        ])->assertSessionHasErrors('service_type_id');
    }

    public function test_success_page_cannot_be_enumerated_without_creation_session(): void
    {
        $this->seed();
        $request = ServiceRequest::factory()->create();

        $this->get(route('requests.success', $request))->assertRedirect(route('status.form'));
    }

    public function test_status_transition_guards_and_actor_audit_fields(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@desa.test')->firstOrFail();
        $request = ServiceRequest::factory()->create(['status' => 'submitted']);

        $this->actingAs($admin)
            ->patch(route('admin.service-requests.complete', $request), ['public_note' => 'Tidak boleh lompat'])
            ->assertSessionHasErrors('status');

        $this->actingAs($admin)
            ->patch(route('admin.service-requests.verify', $request), ['public_note' => 'Valid'])
            ->assertRedirect();

        $request->refresh();
        $this->assertSame('verified', $request->status);
        $this->assertSame($admin->id, $request->verified_by);
        $this->assertDatabaseHas('service_request_status_histories', [
            'service_request_id' => $request->id,
            'to_status' => 'verified',
            'changed_by' => $admin->id,
        ]);
    }

    public function test_audit_columns_are_filled_for_business_model_crud(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@desa.test')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.family-cards.store'), [
            'family_card_number' => '3201017777770001',
            'head_of_family_name' => 'Audit User',
            'address' => 'Jl. Audit',
        ])->assertRedirect();

        $familyCard = FamilyCard::where('family_card_number', '3201017777770001')->firstOrFail();
        $this->assertSame($admin->id, $familyCard->created_by);

        $this->actingAs($admin)->patch(route('admin.family-cards.update', $familyCard), [
            'family_card_number' => '3201017777770001',
            'head_of_family_name' => 'Audit User Updated',
            'address' => 'Jl. Audit',
        ])->assertRedirect();

        $this->assertSame($admin->id, $familyCard->fresh()->updated_by);

        $this->actingAs($admin)->delete(route('admin.family-cards.destroy', $familyCard))->assertRedirect();
        $this->assertSame($admin->id, FamilyCard::withTrashed()->findOrFail($familyCard->id)->deleted_by);
    }

    public function test_public_upload_validation_uses_per_requirement_file_rules(): void
    {
        Storage::fake('private');
        $this->seed();
        $service = ServiceType::where('slug', 'surat-keterangan-domisili')->firstOrFail();
        $requirement = $service->requirements()->firstOrFail();
        $requirement->update([
            'allowed_file_types' => ['pdf'],
            'max_file_size_kb' => 10,
        ]);

        $basePayload = [
            'service_type_id' => $service->id,
            'nik' => '3201010101010001',
            'applicant_name' => 'Yahya Abdillah',
            'phone' => '081234567890',
            'address' => 'Jl. Merdeka No. 1',
            'fields' => ['keperluan' => 'Administrasi'],
        ];

        $this->post(route('requests.store'), $basePayload + [
            'requirements' => [$requirement->id => UploadedFile::fake()->create('ktp.jpg', 5, 'image/jpeg')],
        ])->assertSessionHasErrors('requirements.'.$requirement->id);

        $this->post(route('requests.store'), $basePayload + [
            'requirements' => [$requirement->id => UploadedFile::fake()->create('ktp.pdf', 20, 'application/pdf')],
        ])->assertSessionHasErrors('requirements.'.$requirement->id);
    }

    public function test_document_generation_overlays_fields_on_uploaded_pdf_template(): void
    {
        Storage::fake('private');
        $this->seed();
        $admin = User::where('email', 'admin@desa.test')->firstOrFail();
        $this->actingAs($admin);

        $service = ServiceType::where('slug', 'surat-keterangan-domisili')->firstOrFail();
        $templatePath = 'document-templates/base.pdf';
        Storage::disk('private')->put($templatePath, $this->makeSinglePagePdf('Template dasar'));

        $template = DocumentTemplate::create([
            'service_type_id' => $service->id,
            'name' => 'Template Overlay Test',
            'template_file_path' => $templatePath,
            'original_file_name' => 'base.pdf',
            'page_count' => 1,
            'is_active' => true,
        ]);
        TemplateField::create([
            'document_template_id' => $template->id,
            'label' => 'Nama',
            'variable_key' => 'applicant_name',
            'page_number' => 1,
            'x_position' => 15,
            'y_position' => 25,
            'width' => 50,
            'height' => 8,
            'font_size' => 12,
            'text_align' => 'left',
            'text_color' => '#000000',
        ]);
        $request = ServiceRequest::factory()->create([
            'service_type_id' => $service->id,
            'applicant_name' => 'Yahya Abdillah',
            'letter_number' => '470/001/DS/2026',
        ]);

        $document = app(DocumentGenerationService::class)->generate($request, $template->fresh('fields'));

        Storage::disk('private')->assertExists($document->file_path);
        $generated = Storage::disk('private')->get($document->file_path);
        $this->assertStringStartsWith('%PDF', $generated);
        $this->assertGreaterThan(strlen(Storage::disk('private')->get($templatePath)), strlen($generated));
    }

    public function test_upload_malware_signature_is_rejected_before_storage(): void
    {
        Storage::fake('private');
        config(['security.uploads.blocked_signatures' => ['EICAR-STANDARD-ANTIVIRUS-TEST-FILE']]);
        $this->seed();
        $service = ServiceType::where('slug', 'surat-keterangan-domisili')->firstOrFail();
        $requirement = $service->requirements()->firstOrFail();

        $this->post(route('requests.store'), [
            'service_type_id' => $service->id,
            'nik' => '3201010101010001',
            'applicant_name' => 'Yahya Abdillah',
            'phone' => '081234567890',
            'address' => 'Jl. Merdeka No. 1',
            'fields' => ['keperluan' => 'Administrasi'],
            'requirements' => [
                $requirement->id => UploadedFile::fake()->createWithContent(
                    'ktp.pdf',
                    'X5O!P%@AP[4\\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*'
                ),
            ],
        ])->assertServerError();

        $this->assertDatabaseCount('service_requests', 0);
    }

    public function test_native_deployment_assets_exist_without_docker_setup(): void
    {
        $root = base_path();

        $this->assertFileDoesNotExist($root.'/Dockerfile');
        $this->assertFileDoesNotExist($root.'/docker-compose.prod.yml');
        $this->assertDirectoryDoesNotExist($root.'/docker');
        $this->assertFileExists($root.'/.env.production.example');
        $this->assertFileExists($root.'/.github/workflows/ci.yml');
        $this->assertFileExists($root.'/docs/DEPLOYMENT.md');
    }

    public function test_ui_revision_has_phone_country_selector_and_form_patterns(): void
    {
        $this->seed();
        $service = ServiceType::where('is_active', true)->firstOrFail();
        $admin = User::where('email', 'admin@desa.test')->firstOrFail();

        $this->get(route('requests.create', $service))
            ->assertOk()
            ->assertSee('phone-country', false)
            ->assertSee('🇮🇩 +62', false)
            ->assertSee('data-stepper', false);

        $this->actingAs($admin)->get(route('admin.residents.create'))
            ->assertOk()
            ->assertSee('Stepper untuk input banyak')
            ->assertSee('phone-country', false);

        $this->actingAs($admin)->get(route('admin.roles.create'))
            ->assertOk()
            ->assertSee('Modal untuk input sedikit');
    }

    public function test_health_endpoint_reports_core_dependencies(): void
    {
        $this->get(route('health'))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('checks.database.ok', true)
            ->assertJsonPath('checks.cache.ok', true)
            ->assertJsonPath('checks.private_storage.ok', true);
    }

    public function test_backup_command_creates_private_archive(): void
    {
        Storage::disk('private')->put('service-requests/test/file.txt', 'backup me');

        $this->artisan('backup:run')->assertSuccessful();

        $this->assertNotEmpty(Storage::disk('private')->files('backups'));
    }

    public function test_security_log_channel_is_configured(): void
    {
        $this->assertSame('daily', config('logging.channels.security.driver'));
        $this->assertStringEndsWith(
            'storage/logs/security.log',
            str_replace('\\', '/', config('logging.channels.security.path'))
        );
    }

    public function test_granular_rbac_blocks_petugas_from_user_management_but_allows_request_workflow(): void
    {
        $this->seed();
        $petugas = User::factory()->create(['email' => 'petugas@example.test']);
        $petugas->assignRole('Petugas');
        $request = ServiceRequest::factory()->create(['status' => 'submitted']);

        $this->actingAs($petugas)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($petugas)->get(route('admin.service-requests.index'))->assertOk();
        $this->actingAs($petugas)->patch(route('admin.service-requests.verify', $request))->assertRedirect();

        $this->assertSame('verified', $request->fresh()->status);
    }

    public function test_activity_log_dashboard_shows_business_model_changes(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@desa.test')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.family-cards.store'), [
            'family_card_number' => '3201018888880001',
            'head_of_family_name' => 'Activity User',
            'address' => 'Jl. Activity',
        ])->assertRedirect();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'business-model',
            'description' => 'FamilyCard created',
        ]);

        $this->actingAs($admin)->get(route('admin.activity-logs.index'))
            ->assertOk()
            ->assertSee('FamilyCard created');
    }

    public function test_security_log_dashboard_is_permission_protected(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@desa.test')->firstOrFail();
        $petugas = User::factory()->create(['email' => 'petugas-log@example.test']);
        $petugas->assignRole('Petugas');

        $this->actingAs($petugas)->get(route('admin.security-logs.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.security-logs.index'))->assertOk();
    }

    public function test_resident_csv_import_export_flow(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@desa.test')->firstOrFail();

        $csv = "nik,name,gender,birth_place,birth_date,address,hamlet,rt,rw,religion,marital_status,occupation,phone,is_active\n".
            "3201017777770002,CSV Resident,male,Bandung,1991-02-03,Jl CSV,Dusun C,005,006,Islam,Belum Kawin,Petani,0811111111,1\n";

        $this->actingAs($admin)->post(route('admin.residents.import-preview'), [
            'csv' => UploadedFile::fake()->createWithContent('residents.csv', $csv),
        ])->assertRedirect()
            ->assertSessionHas('import_preview.can_import', true)
            ->assertSessionHas('resident_import.token');

        $this->actingAs($admin)->post(route('admin.residents.import'), [
            'import_token' => session('resident_import.token'),
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertDatabaseHas('residents', [
            'nik' => '3201017777770002',
            'name' => 'CSV Resident',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.residents.export'));
        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('CSV Resident', $response->streamedContent());
    }

    public function test_activity_log_filters_by_event_and_description(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@desa.test')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.family-cards.store'), [
            'family_card_number' => '3201015555550001',
            'head_of_family_name' => 'Filter Activity',
            'address' => 'Jl. Filter',
        ])->assertRedirect();

        $this->actingAs($admin)->get(route('admin.activity-logs.index', [
            'event' => 'created',
            'q' => 'FamilyCard',
        ]))->assertOk()->assertSee('FamilyCard created');

        $this->actingAs($admin)->get(route('admin.activity-logs.index', [
            'event' => 'deleted',
            'q' => 'No Match',
        ]))->assertOk()->assertDontSee('FamilyCard created');
    }

    public function test_excel_template_download_and_import(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@desa.test')->firstOrFail();

        $template = $this->actingAs($admin)->get(route('admin.residents.template'));
        $template->assertOk();
        $this->assertStringStartsWith('PK', $template->streamedContent());

        $xlsxPath = app(ResidentExcelService::class)->template();
        $this->actingAs($admin)->post(route('admin.residents.import-preview'), [
            'csv' => new UploadedFile($xlsxPath, 'template-import-penduduk.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
        ])->assertRedirect()
            ->assertSessionHas('import_preview.can_import', true);

        $this->actingAs($admin)->post(route('admin.residents.import'), [
            'import_token' => session('resident_import.token'),
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertDatabaseHas('residents', [
            'nik' => '3201010101010001',
            'name' => 'Budi Santoso',
        ]);
    }

    public function test_whatsapp_linking_page_and_status_notification_hook(): void
    {
        RateLimiter::clear('whatsapp-send:global');
        Http::fake([
            '127.0.0.1:3100/send-message' => Http::response(['ok' => true]),
            '127.0.0.1:3100/status' => Http::response([], 503),
        ]);
        config([
            'whatsapp.enabled' => true,
            'whatsapp.bridge_url' => 'http://127.0.0.1:3100',
            'whatsapp.bridge_token' => 'test-token',
        ]);
        $this->seed();
        $admin = User::where('email', 'admin@desa.test')->firstOrFail();

        $this->actingAs($admin)->get(route('admin.whatsapp.index'))
            ->assertOk()
            ->assertSee('Tautkan WhatsApp')
            ->assertSee('Bridge tidak terjangkau');

        $request = ServiceRequest::factory()->create([
            'status' => 'submitted',
            'phone' => '081234567890',
        ]);
        $request->transitionTo('verified', 'Berkas valid.', actorId: $admin->id);

        Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:3100/send-message'
            && $request['phone'] === '081234567890'
            && str_contains($request['message'], 'Berkas valid.'));
    }

    public function test_resident_import_preview_reports_errors_without_committing(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@desa.test')->firstOrFail();
        $csv = "nik,name,gender,address\n3201010000000009,,male,Jl Preview\n";

        $this->actingAs($admin)->post(route('admin.residents.import-preview'), [
            'csv' => UploadedFile::fake()->createWithContent('residents.csv', $csv),
        ])->assertRedirect()->assertSessionHas('import_preview');

        $this->assertDatabaseMissing('residents', ['nik' => '3201010000000009']);
        $preview = session('import_preview');
        $this->assertFalse($preview['can_import']);
        $this->assertSame(0, $preview['valid_rows']);
        $this->assertNotEmpty($preview['errors']);
        $this->assertNull(session('resident_import'));
    }

    public function test_resident_import_requires_a_previously_validated_file(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@desa.test')->firstOrFail();
        $csv = "nik,name,gender,address\n3201010000000010,Bypass Preview,male,Jl Bypass\n";

        $this->actingAs($admin)->post(route('admin.residents.import'), [
            'csv' => UploadedFile::fake()->createWithContent('residents.csv', $csv),
        ])->assertSessionHasErrors('import_token');

        $this->assertDatabaseMissing('residents', ['nik' => '3201010000000010']);
    }

    public function test_whatsapp_notification_creates_log_record(): void
    {
        Http::fake(['127.0.0.1:3100/send-message' => Http::response(['ok' => true])]);
        config([
            'whatsapp.enabled' => true,
            'whatsapp.bridge_url' => 'http://127.0.0.1:3100',
            'queue.default' => 'sync',
        ]);
        $this->seed();
        $admin = User::where('email', 'admin@desa.test')->firstOrFail();
        $request = ServiceRequest::factory()->create(['status' => 'submitted', 'phone' => '081299999999']);

        $request->transitionTo('verified', 'Siap diproses.', actorId: $admin->id);

        $this->assertDatabaseHas('notification_logs', [
            'service_request_id' => $request->id,
            'channel' => 'whatsapp',
            'recipient' => '081299999999',
            'status' => 'sent',
        ]);
        $this->actingAs($admin)->get(route('admin.notification-logs.index'))->assertOk()->assertSee('081299999999');
    }

    private function makeSinglePagePdf(string $text): string
    {
        $pdf = new FPDF;
        $pdf->AddPage();
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 10, $text);

        return $pdf->Output('S');
    }
}
