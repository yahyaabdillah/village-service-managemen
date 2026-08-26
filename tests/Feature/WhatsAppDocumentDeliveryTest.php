<?php

namespace Tests\Feature;

use App\Models\NotificationLog;
use App\Models\ServiceRequest;
use App\Models\ServiceType;
use App\Models\User;
use App\Services\WhatsAppNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WhatsAppDocumentDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'whatsapp.enabled' => true,
            'whatsapp.bridge_url' => 'http://127.0.0.1:3100',
            'whatsapp.bridge_token' => 'test-token',
            'whatsapp.queue_connection' => 'sync',
            'whatsapp.document_max_size_kb' => 5120,
            'queue.default' => 'database',
        ]);

        RateLimiter::clear('whatsapp-send:global');
    }

    public function test_status_notification_uses_dedicated_sync_queue_connection(): void
    {
        Http::fake(['127.0.0.1:3100/send-message' => Http::response(['ok' => true])]);
        $this->seed();
        $admin = User::where('email', 'admin@desa.test')->firstOrFail();
        $serviceRequest = ServiceRequest::factory()->create([
            'status' => 'submitted',
            'phone' => '+6281299999999',
        ]);

        $serviceRequest->transitionTo('verified', 'Berkas valid.', actorId: $admin->id);

        Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:3100/send-message'
            && $request['phone'] === '+6281299999999'
            && str_contains($request['message'], 'Berkas valid.'));
        $this->assertDatabaseHas('notification_logs', [
            'service_request_id' => $serviceRequest->id,
            'channel' => 'whatsapp',
            'status' => 'sent',
        ]);
        $this->assertSame(0, DB::table('jobs')->count());
    }

    public function test_submission_sends_whatsapp_confirmation_with_code_and_nik(): void
    {
        Storage::fake('private');
        Http::fake(['127.0.0.1:3100/send-message' => Http::response(['ok' => true])]);
        $this->seed();

        $service = ServiceType::where('slug', 'surat-keterangan-domisili')->firstOrFail();
        $requirement = $service->requirements()->firstOrFail();

        $this->post('/pengajuan', [
            'service_type_id' => $service->id,
            'nik' => '3201010101010099',
            'applicant_name' => 'Warga Uji Coba',
            'phone' => '081234500099',
            'address' => 'Jl. Uji Coba No. 9',
            'fields' => ['keperluan' => 'Keperluan pengujian'],
            'requirements' => [
                $requirement->id => UploadedFile::fake()->create('ktp.jpg', 100, 'image/jpeg'),
            ],
        ])->assertRedirect();

        $serviceRequest = ServiceRequest::where('nik', '3201010101010099')->firstOrFail();

        Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:3100/send-message'
            && $request['phone'] === $serviceRequest->phone
            && str_contains($request['message'], $serviceRequest->request_code)
            && str_contains($request['message'], '3201010101010099')
            && str_contains($request['message'], 'Desa Ngringo'));
        $this->assertDatabaseHas('notification_logs', [
            'service_request_id' => $serviceRequest->id,
            'channel' => 'whatsapp',
            'status' => 'sent',
        ]);
    }

    public function test_authorized_admin_can_send_active_private_document_through_whatsapp_bridge(): void
    {
        Storage::fake('private');
        Http::fake(['127.0.0.1:3100/send-document' => Http::response(['ok' => true])]);
        $this->seed();
        $admin = User::where('email', 'admin@desa.test')->firstOrFail();
        $serviceRequest = ServiceRequest::factory()->create([
            'status' => 'completed',
            'phone' => '+6281299990006',
        ]);
        $path = 'generated-documents/'.$serviceRequest->request_code.'/surat-final.pdf';
        $contents = '%PDF-1.4 dokumen final pengujian';
        Storage::disk('private')->put($path, $contents);
        $document = $serviceRequest->generatedDocuments()->create([
            'source' => 'generated',
            'file_path' => $path,
            'original_file_name' => 'surat-final.pdf',
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'file_size' => strlen($contents),
            'status' => 'valid',
            'is_active' => true,
            'generated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.service-requests.show', $serviceRequest))
            ->assertOk()
            ->assertSee('Kirim Dokumen via WhatsApp')
            ->assertSee(route('admin.service-requests.documents.send-whatsapp', $serviceRequest), false);

        $this->actingAs($admin)
            ->post(route('admin.service-requests.documents.send-whatsapp', $serviceRequest))
            ->assertRedirect()
            ->assertSessionHas('status', 'Dokumen berhasil dikirim lewat WhatsApp.');

        Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:3100/send-document'
            && $request->hasHeader('Authorization', 'Bearer test-token')
            && $request['phone'] === '+6281299990006'
            && $request['filename'] === $serviceRequest->request_code.'.pdf'
            && $request['mime_type'] === 'application/pdf'
            && base64_decode($request['document'], true) === $contents);
        $this->assertDatabaseHas('notification_logs', [
            'service_request_id' => $serviceRequest->id,
            'channel' => 'whatsapp_document',
            'recipient' => '+6281299990006',
            'status' => 'sent',
        ]);
        $this->assertDatabaseHas('generated_documents', ['id' => $document->id, 'is_active' => true]);
    }

    public function test_rate_limiter_blocks_excessive_sends_to_same_recipient(): void
    {
        config(['whatsapp.rate_limit_per_recipient' => 1]);
        Http::fake(['127.0.0.1:3100/send-message' => Http::response(['ok' => true])]);
        $this->seed();
        $serviceRequest = ServiceRequest::factory()->create(['phone' => '+6281200000111']);
        $service = app(WhatsAppNotificationService::class);

        $first = NotificationLog::create([
            'service_request_id' => $serviceRequest->id,
            'channel' => 'whatsapp',
            'recipient' => $serviceRequest->phone,
            'message' => 'Pesan pertama.',
            'status' => 'pending',
        ]);
        $second = NotificationLog::create([
            'service_request_id' => $serviceRequest->id,
            'channel' => 'whatsapp',
            'recipient' => $serviceRequest->phone,
            'message' => 'Pesan kedua.',
            'status' => 'pending',
        ]);

        $service->sendNow($first);
        $service->sendNow($second);

        $this->assertSame('sent', $first->fresh()->status);
        $this->assertSame('failed', $second->fresh()->status);
        $this->assertStringContainsString('Batas pengiriman', (string) $second->fresh()->error_message);
    }

    public function test_document_delivery_requires_document_permission(): void
    {
        $this->seed();
        $serviceRequest = ServiceRequest::factory()->create(['status' => 'completed']);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('admin.service-requests.documents.send-whatsapp', $serviceRequest))
            ->assertForbidden();
    }

}
