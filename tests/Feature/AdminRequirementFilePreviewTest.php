<?php

namespace Tests\Feature;

use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminRequirementFilePreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_preview_pdf_and_image_requirement_files_inline(): void
    {
        Storage::fake('private');
        $this->seed();

        $admin = User::where('email', 'admin@desa.test')->firstOrFail();
        $serviceRequest = ServiceRequest::factory()->create();

        $pdfContent = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF";
        $pngContent = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        Storage::disk('private')->put('service-requests/test/document.pdf', $pdfContent);
        Storage::disk('private')->put('service-requests/test/photo.png', $pngContent);

        $pdf = $serviceRequest->files()->create([
            'original_name' => 'Kartu Keluarga.pdf',
            'file_name' => 'document.pdf',
            'file_path' => 'service-requests/test/document.pdf',
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'file_size' => strlen($pdfContent),
        ]);
        $image = $serviceRequest->files()->create([
            'original_name' => 'KTP Pemohon.png',
            'file_name' => 'photo.png',
            'file_path' => 'service-requests/test/photo.png',
            'file_type' => 'png',
            'mime_type' => 'image/png',
            'file_size' => strlen($pngContent),
        ]);

        $detail = $this->actingAs($admin)->get('/admin/service-requests/'.$serviceRequest->id);
        $detail->assertOk()
            ->assertSee('/admin/service-requests/'.$serviceRequest->id.'/files/'.$pdf->id.'/preview', false)
            ->assertSee('/admin/service-requests/'.$serviceRequest->id.'/files/'.$image->id.'/preview', false)
            ->assertSee('Lihat');

        $this->actingAs($admin)
            ->get('/admin/service-requests/'.$serviceRequest->id.'/files/'.$pdf->id.'/preview')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename="Kartu-Keluarga.pdf"');

        $this->actingAs($admin)
            ->get('/admin/service-requests/'.$serviceRequest->id.'/files/'.$image->id.'/preview')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('Content-Disposition', 'inline; filename="KTP-Pemohon.png"');
    }

    public function test_requirement_file_preview_requires_permission_and_matching_request(): void
    {
        Storage::fake('private');
        $this->seed();

        $firstRequest = ServiceRequest::factory()->create();
        $secondRequest = ServiceRequest::factory()->create();
        Storage::disk('private')->put('service-requests/test/document.pdf', '%PDF-1.4 test');
        $file = $firstRequest->files()->create([
            'original_name' => 'Dokumen.pdf',
            'file_name' => 'document.pdf',
            'file_path' => 'service-requests/test/document.pdf',
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 13,
        ]);

        $url = '/admin/service-requests/'.$firstRequest->id.'/files/'.$file->id.'/preview';
        $this->get($url)->assertRedirect('/login');

        $admin = User::where('email', 'admin@desa.test')->firstOrFail();
        $this->actingAs($admin)
            ->get('/admin/service-requests/'.$secondRequest->id.'/files/'.$file->id.'/preview')
            ->assertNotFound();
    }

    public function test_admin_can_download_non_previewable_requirement_file(): void
    {
        Storage::fake('private');
        $this->seed();

        $admin = User::where('email', 'admin@desa.test')->firstOrFail();
        $serviceRequest = ServiceRequest::factory()->create();
        $content = 'fake docx content';
        Storage::disk('private')->put('service-requests/test/attachment.docx', $content);
        $file = $serviceRequest->files()->create([
            'original_name' => 'Lampiran Pendukung.docx',
            'file_name' => 'attachment.docx',
            'file_path' => 'service-requests/test/attachment.docx',
            'file_type' => 'docx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'file_size' => strlen($content),
        ]);
        $downloadUrl = '/admin/service-requests/'.$serviceRequest->id.'/files/'.$file->id.'/download';

        $this->get($downloadUrl)->assertRedirect('/login');

        $detail = $this->actingAs($admin)->get('/admin/service-requests/'.$serviceRequest->id);
        $detail->assertOk()
            ->assertSee('/admin/service-requests/'.$serviceRequest->id.'/files/'.$file->id.'/download', false)
            ->assertSee('Unduh')
            ->assertDontSee('Lihat berkas Lampiran Pendukung.docx', false);

        $this->actingAs($admin)
            ->get($downloadUrl)
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="Lampiran-Pendukung.docx"')
            ->assertContent($content);

        $otherRequest = ServiceRequest::factory()->create();
        $this->actingAs($admin)
            ->get('/admin/service-requests/'.$otherRequest->id.'/files/'.$file->id.'/download')
            ->assertNotFound();
    }
}
