<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\DocumentTemplate;
use App\Models\FamilyCard;
use App\Models\GeneratedDocument;
use App\Models\Resident;
use App\Models\ServiceRequest;
use App\Models\ServiceRequirement;
use App\Models\ServiceType;
use App\Models\ServiceTypeField;
use App\Models\TemplateField;
use App\Models\User;
use App\Models\VillageProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_area_requires_login_and_permission(): void
    {
        $this->seed();

        $this->get('/admin')->assertRedirect('/login');

        $unauthorized = User::factory()->create([
            'email' => 'staff@example.test',
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($unauthorized)
            ->get('/admin')
            ->assertForbidden();

        $admin = User::where('email', 'admin@desa.test')->firstOrFail();
        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('Dashboard Layanan Desa')
            ->assertSee('Total Penduduk')
            ->assertSee('Pengajuan Baru');
    }

    public function test_login_and_logout_flow_works(): void
    {
        $this->seed();

        $this->get('/login')->assertOk()->assertSee('Login Admin');

        $this->post('/login', [
            'email' => 'admin@desa.test',
            'password' => 'password',
        ])->assertRedirect('/admin');

        $this->assertAuthenticated();

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_admin_can_manage_core_master_data_crud(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@desa.test')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.family-cards.store'), [
            'family_card_number' => '3201019999990001',
            'head_of_family_name' => 'Siti Aminah',
            'address' => 'Jl. Melati No. 2',
            'hamlet' => 'Dusun B',
            'rt' => '003',
            'rw' => '004',
        ])->assertRedirect();

        $familyCard = FamilyCard::where('family_card_number', '3201019999990001')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.residents.store'), [
            'family_card_id' => $familyCard->id,
            'nik' => '3201019999990002',
            'name' => 'Siti Aminah',
            'gender' => 'female',
            'address' => 'Jl. Melati No. 2',
            'hamlet' => 'Dusun B',
            'rt' => '003',
            'rw' => '004',
            'is_active' => 1,
        ])->assertRedirect();

        $resident = Resident::where('nik', '3201019999990002')->firstOrFail();
        $this->assertSame('Siti Aminah', $resident->name);

        $this->actingAs($admin)->patch(route('admin.residents.update', $resident), [
            'family_card_id' => $familyCard->id,
            'nik' => '3201019999990002',
            'name' => 'Siti Aminah Updated',
            'gender' => 'female',
            'address' => 'Jl. Melati No. 2',
            'is_active' => 1,
        ])->assertRedirect();

        $this->assertSame('Siti Aminah Updated', $resident->fresh()->name);

        $this->actingAs($admin)->delete(route('admin.residents.destroy', $resident))->assertRedirect();
        $this->assertSoftDeleted('residents', ['id' => $resident->id]);
    }

    public function test_admin_can_open_every_generic_crud_edit_form(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@desa.test')->firstOrFail();

        $resources = [
            'village-profiles' => VillageProfile::firstOrFail(),
            'family-cards' => FamilyCard::firstOrFail(),
            'residents' => Resident::firstOrFail(),
            'service-types' => ServiceType::firstOrFail(),
            'service-requirements' => ServiceRequirement::firstOrFail(),
            'service-type-fields' => ServiceTypeField::firstOrFail(),
            'announcements' => Announcement::firstOrFail(),
            'users' => User::firstOrFail(),
            'roles' => Role::firstOrFail(),
        ];

        foreach ($resources as $resource => $item) {
            $this->actingAs($admin)
                ->get(route('admin.'.$resource.'.edit', $item->id))
                ->assertOk();
        }
    }

    public function test_document_template_builder_generation_and_protected_download(): void
    {
        Storage::fake('private');
        $this->seed();
        $admin = User::where('email', 'admin@desa.test')->firstOrFail();
        $service = ServiceType::where('slug', 'surat-keterangan-domisili')->firstOrFail();
        $validPdf = Storage::disk('private')->get(
            DocumentTemplate::where('service_type_id', $service->id)->firstOrFail()->template_file_path
        );

        $this->actingAs($admin)->post(route('admin.document-templates.store'), [
            'service_type_id' => $service->id,
            'name' => 'Template Domisili',
            'description' => 'Template surat domisili',
            'template' => UploadedFile::fake()->createWithContent('domisili.pdf', $validPdf),
        ])->assertRedirect();

        $template = DocumentTemplate::where('name', 'Template Domisili')->firstOrFail();
        $this->actingAs($admin)
            ->get(route('admin.document-templates.preview', $template))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($admin)->post(route('admin.document-templates.fields.store', $template), [
            'label' => 'Nama Pemohon',
            'variable_key' => 'applicant_name',
            'page_number' => 1,
            'x_position' => 10,
            'y_position' => 20,
            'width' => 50,
            'height' => 8,
            'font_size' => 12,
            'text_align' => 'left',
            'text_color' => '#000000',
        ])->assertRedirect();

        $this->assertSame(1, TemplateField::where('document_template_id', $template->id)->count());
        $this->actingAs($admin)->patch(route('admin.document-templates.activate', $template))->assertRedirect();

        $request = ServiceRequest::factory()->create([
            'service_type_id' => $service->id,
            'applicant_name' => 'Yahya Abdillah',
            'nik' => '3201010101010001',
            'status' => 'processing',
        ]);

        $this->actingAs($admin)->post(route('admin.service-requests.generate-document', $request), [
            'document_template_id' => $template->id,
            'letter_number' => '470/001/DS/2026',
        ])->assertRedirect();

        $request->refresh();
        $document = GeneratedDocument::where('service_request_id', $request->id)->firstOrFail();
        $this->assertSame('generated', $request->document_source);
        Storage::disk('private')->assertExists($document->file_path);
        $request->update(['status' => 'completed']);

        $this->get(route('documents.download', $request))->assertRedirect(route('status.form'));

        $this->post(route('documents.download.authorize', $request), [
            'request_code' => $request->request_code,
            'nik' => '3201010101010001',
        ])->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_approved_request_generates_pdf_and_is_ready_for_citizen_download(): void
    {
        Storage::fake('private');
        $this->seed();

        $admin = User::where('email', 'admin@desa.test')->firstOrFail();
        $service = ServiceType::where('slug', 'surat-keterangan-domisili')->firstOrFail();
        $request = ServiceRequest::factory()->create([
            'service_type_id' => $service->id,
            'status' => 'verified',
            'nik' => '3201010101010001',
        ]);
        $request->fieldValues()->create([
            'service_type_field_id' => $service->fields()->where('field_key', 'keperluan')->value('id'),
            'field_key' => 'keperluan',
            'label' => 'Keperluan',
            'value' => 'Administrasi kependudukan',
        ]);

        $this->actingAs($admin)->patch(route('admin.service-requests.publish', $request), [
            'letter_number' => '470/001/DS/2026',
        ])->assertRedirect();

        $request->refresh();
        $document = $request->generatedDocuments()->firstOrFail();

        $this->assertSame('completed', $request->status);
        $this->assertSame('generated', $request->document_source);
        $this->assertSame('470/001/DS/2026', $request->letter_number);
        Storage::disk('private')->assertExists($document->file_path);

        $this->post(route('status.check'), [
            'request_code' => $request->request_code,
            'nik' => $request->nik,
        ])->assertOk()->assertSee('Unduh Dokumen');
    }

    public function test_invalid_pdf_upload_is_rejected_instead_of_creating_a_fallback_document(): void
    {
        Storage::fake('private');
        $this->seed();
        $admin = User::where('email', 'admin@desa.test')->firstOrFail();
        $service = ServiceType::firstOrFail();

        $this->actingAs($admin)->post(route('admin.document-templates.store'), [
            'service_type_id' => $service->id,
            'name' => 'PDF Rusak',
            'template' => UploadedFile::fake()->create('rusak.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors('template');

        $this->assertDatabaseMissing('document_templates', ['name' => 'PDF Rusak']);
    }

    public function test_download_uses_actual_png_mime_and_extension(): void
    {
        Storage::fake('private');
        $this->seed();
        $request = ServiceRequest::factory()->create(['status' => 'completed']);
        $path = 'generated-documents/'.$request->request_code.'/legacy.png';
        Storage::disk('private')->put($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL9WQAAAABJRU5ErkJggg=='));
        $request->generatedDocuments()->create([
            'source' => 'manual', 'file_path' => $path, 'original_file_name' => 'legacy.pdf',
            'file_type' => 'pdf', 'is_active' => true, 'status' => 'valid', 'generated_at' => now(),
        ]);

        $this->post(route('documents.download.authorize', $request), [
            'request_code' => $request->request_code,
            'nik' => $request->nik,
        ])->assertOk()
            ->assertHeader('content-type', 'image/png')
            ->assertHeader('content-disposition', 'attachment; filename="'.$request->request_code.'.png"');
    }

    public function test_builder_can_create_draft_variable_without_exposing_it_publicly(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@desa.test')->firstOrFail();
        $template = DocumentTemplate::firstOrFail();

        $this->actingAs($admin)->postJson(route('admin.document-templates.variables.store', $template), [
            'label' => 'Tujuan Surat', 'field_key' => 'tujuan_surat', 'field_type' => 'text', 'is_required' => true,
        ])->assertCreated()->assertJsonPath('variable.key', 'tujuan_surat');

        $this->assertDatabaseHas('service_type_fields', [
            'service_type_id' => $template->service_type_id, 'field_key' => 'tujuan_surat', 'is_active' => false,
        ]);
        $this->get(route('requests.create', $template->serviceType))->assertDontSee('name="fields[tujuan_surat]"', false);
    }

    public function test_admin_request_index_is_a_filterable_table_with_actions(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@desa.test')->firstOrFail();
        $visible = ServiceRequest::factory()->create(['status' => 'verified', 'applicant_name' => 'Siti Terverifikasi']);
        ServiceRequest::factory()->create(['status' => 'completed', 'applicant_name' => 'Budi Selesai']);

        $this->actingAs($admin)->get(route('admin.service-requests.index', ['status' => 'verified']))
            ->assertOk()
            ->assertSee('<table', false)
            ->assertSee('Siti Terverifikasi')
            ->assertDontSee('Budi Selesai')
            ->assertSee('Dokumen')
            ->assertSee('Aksi')
            ->assertSee(route('admin.service-requests.show', $visible));
    }

    public function test_request_detail_shows_review_data_and_publish_action(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@desa.test')->firstOrFail();
        $request = ServiceRequest::factory()->create([
            'status' => 'verified',
            'applicant_name' => 'Yahya Abdillah',
            'address' => 'Dusun Krajan RT 01 RW 02',
        ]);

        $this->actingAs($admin)->get(route('admin.service-requests.show', $request))
            ->assertOk()
            ->assertSee('Yahya Abdillah')
            ->assertSee('Dusun Krajan RT 01 RW 02')
            ->assertSee('Setujui & Terbitkan', false)
            ->assertSee('letter_number', false)
            ->assertDontSee('Modal form')
            ->assertDontSee('Drawer form');
    }

    public function test_service_type_requirements_fields_announcements_and_users_have_admin_crud_routes(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@desa.test')->firstOrFail();
        $service = ServiceType::firstOrFail();

        $this->actingAs($admin)->get(route('admin.service-types.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.service-requirements.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.service-type-fields.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.announcements.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.roles.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.document-templates.index'))->assertOk();

        $this->actingAs($admin)->post(route('admin.service-type-fields.store'), [
            'service_type_id' => $service->id,
            'label' => 'Tanggal Keperluan',
            'field_key' => 'tanggal_keperluan',
            'field_type' => 'date',
            'is_required' => 1,
            'sort_order' => 2,
        ])->assertRedirect();

        $this->assertDatabaseHas('service_type_fields', [
            'service_type_id' => $service->id,
            'field_key' => 'tanggal_keperluan',
        ]);
    }

    public function test_public_form_renders_and_validates_configured_dynamic_field_types(): void
    {
        $this->seed();
        $service = ServiceType::where('slug', 'surat-keterangan-domisili')->firstOrFail();
        $service->fields()->create([
            'label' => 'Tanggal Keperluan',
            'field_key' => 'tanggal_keperluan',
            'field_type' => 'date',
            'is_required' => true,
            'sort_order' => 10,
        ]);
        $service->fields()->create([
            'label' => 'Jenis Keperluan',
            'field_key' => 'jenis_keperluan',
            'field_type' => 'select',
            'options' => ['Pribadi', 'Usaha'],
            'is_required' => true,
            'sort_order' => 11,
        ]);

        $this->get(route('requests.create', $service))
            ->assertOk()
            ->assertSee('type="date"', false)
            ->assertSee('name="fields[jenis_keperluan]"', false)
            ->assertSee('<option value="Pribadi"', false)
            ->assertSee('<option value="Usaha"', false);

        $this->post(route('requests.store'), [
            'service_type_id' => $service->id,
            'nik' => '3201010101010001',
            'applicant_name' => 'Yahya Abdillah',
            'phone' => '081234567890',
            'address' => 'Jl. Merdeka No. 1',
            'fields' => [
                'keperluan' => 'Administrasi',
                'tanggal_keperluan' => 'bukan-tanggal',
                'jenis_keperluan' => 'Pilihan Tidak Valid',
            ],
        ])
            ->assertSessionHasErrors([
                'fields.tanggal_keperluan',
                'fields.jenis_keperluan',
            ]);
    }

    public function test_upload_forms_render_dropzone_boxes_for_public_and_admin_files(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@desa.test')->firstOrFail();
        $service = ServiceType::where('slug', 'surat-keterangan-domisili')->firstOrFail();
        $request = ServiceRequest::factory()->create(['service_type_id' => $service->id]);

        $this->get(route('requests.create', $service))
            ->assertOk()
            ->assertSee('data-dropzone', false)
            ->assertSee('Tarik file ke sini atau klik untuk upload')
            ->assertSee('name="requirements['.$service->requirements()->firstOrFail()->id.']"', false)
            ->assertSee('.mp4', false);

        $this->actingAs($admin)->get(route('admin.document-templates.create'))
            ->assertOk()
            ->assertSee('data-dropzone', false)
            ->assertSee('name="template"', false);

        $this->actingAs($admin)->get(route('admin.service-requests.show', $request))
            ->assertOk()
            ->assertSee('data-dropzone', false)
            ->assertSee('name="document"', false)
            ->assertSee('.mp4', false);

        $this->actingAs($admin)->get(route('admin.residents.index'))
            ->assertOk()
            ->assertSee('residents-import-file')
            ->assertSee('name="csv"', false)
            ->assertDontSee('residents-import-preview');
    }

    public function test_seeded_services_have_professional_default_letter_templates(): void
    {
        $this->seed();

        $this->assertSame(5, DocumentTemplate::where('name', 'like', 'Template Resmi%')->count());

        $this->assertDatabaseHas('village_profiles', [
            'village_name' => 'Desa Ngringo',
            'district' => 'Kecamatan Jaten',
            'regency' => 'Kabupaten Karanganyar',
            'province' => 'Jawa Tengah',
        ]);

        DocumentTemplate::where('name', 'like', 'Template Resmi%')->with('fields')->get()->each(function (DocumentTemplate $template) {
            Storage::disk('private')->assertExists($template->template_file_path);
            $content = Storage::disk('private')->get($template->template_file_path);

            $this->assertStringStartsWith('%PDF', $content);
            $this->assertSame(8, $template->fields->count());
            $this->assertTrue($template->fields->contains('variable_key', 'letter_number'));
            $this->assertTrue($template->fields->contains('variable_key', 'applicant_name'));
            $this->assertTrue($template->fields->contains('variable_key', 'place_date'));
            $this->assertTrue($template->fields->every(fn ($field) => ($field->mapping_config['key'] ?? null) === $field->variable_key));
        });
    }
}
