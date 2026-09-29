<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DocumentTemplate;
use App\Models\GeneratedDocument;
use App\Models\RequestFile;
use App\Models\ServiceRequest;
use App\Models\ServiceType;
use App\Services\DocumentGenerationService;
use App\Services\MalwareScanner;
use App\Services\PrivateDocumentResponse;
use App\Services\WhatsAppNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class ServiceRequestController extends Controller
{
    public function index(Request $request)
    {
        $query = ServiceRequest::with('serviceType')->withCount('generatedDocuments')->latest();

        if ($search = trim($request->string('q')->toString())) {
            $query->where(function ($query) use ($search) {
                $query->where('request_code', 'like', "%{$search}%")
                    ->orWhere('applicant_name', 'like', "%{$search}%")
                    ->orWhere('nik', 'like', "%{$search}%");
            });
        }
        if (array_key_exists($request->string('status')->toString(), ServiceRequest::statuses())) {
            $query->where('status', $request->string('status')->toString());
        }
        if ($request->filled('service_type_id')) {
            $query->where('service_type_id', $request->integer('service_type_id'));
        }

        return view('admin.service-requests.index', [
            'requests' => $query->paginate(20)->withQueryString(),
            'serviceTypes' => ServiceType::orderBy('name')->get(),
            'counts' => ServiceRequest::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function show(ServiceRequest $serviceRequest)
    {
        $serviceRequest->load([
            'serviceType' => fn ($q) => $q->withTrashed(),
            'fieldValues', 'files', 'statusHistories.changedBy', 'generatedDocuments.documentTemplate',
        ]);

        $defaultTemplate = DocumentTemplate::where('service_type_id', $serviceRequest->service_type_id)
            ->where('is_active', true)->where('is_default', true)->where('status', 'active')->first();

        return view('admin.service-requests.show', [
            'serviceRequest' => $serviceRequest,
            'defaultTemplate' => $defaultTemplate,
            'availableTemplates' => DocumentTemplate::where('service_type_id', $serviceRequest->service_type_id)
                ->where('is_active', true)->where('status', 'active')->orderByDesc('is_default')->orderByDesc('version')->get(),
            'whatsappEnabled' => (bool) config('whatsapp.enabled'),
        ]);
    }

    public function previewRequirementFile(ServiceRequest $serviceRequest, RequestFile $requestFile, PrivateDocumentResponse $response)
    {
        abort_unless($requestFile->service_request_id === $serviceRequest->id, 404);

        return $response->inline($requestFile->file_path, $requestFile->original_name);
    }

    public function downloadRequirementFile(ServiceRequest $serviceRequest, RequestFile $requestFile, PrivateDocumentResponse $response)
    {
        abort_unless($requestFile->service_request_id === $serviceRequest->id, 404);

        return $response->download($requestFile->file_path, pathinfo($requestFile->original_name, PATHINFO_FILENAME));
    }

    public function verify(Request $request, ServiceRequest $serviceRequest)
    {
        $note = $this->publicNote($request) ?: 'Berkas telah diperiksa dan dinyatakan lengkap.';

        return $this->transition($serviceRequest, 'verified', $note, 'Berkas dinyatakan lengkap. Warga menerima pemberitahuan.');
    }

    public function process(Request $request, ServiceRequest $serviceRequest)
    {
        $note = $this->publicNote($request) ?: 'Pengajuan sedang diproses oleh petugas desa.';

        return $this->transition($serviceRequest, 'processing', $note, 'Pengajuan ditandai sedang diproses.');
    }

    public function reject(Request $request, ServiceRequest $serviceRequest)
    {
        $data = $request->validate(['rejection_reason' => ['required', 'string', 'min:10', 'max:1000']]);

        return $this->transition($serviceRequest, 'rejected', $data['rejection_reason'], 'Pengajuan ditolak. Warga menerima alasan penolakan.', $data['rejection_reason']);
    }

    public function complete(Request $request, ServiceRequest $serviceRequest)
    {
        if (! $serviceRequest->canTransitionTo('completed')) {
            return back()->withErrors(['status' => 'Pengajuan ini tidak dapat diselesaikan dari status saat ini.']);
        }
        if (! $serviceRequest->generatedDocuments()->where('is_active', true)->exists() && ! $serviceRequest->uploaded_document_path && ! $serviceRequest->generated_document_path) {
            return back()->withErrors(['final_document' => 'Terbitkan atau unggah dokumen terlebih dahulu sebelum menyelesaikan pengajuan.']);
        }

        $note = $this->publicNote($request) ?: 'Dokumen selesai dan dapat diunduh.';

        return $this->transition($serviceRequest, 'completed', $note, 'Pengajuan selesai. Warga dapat mengunduh dokumennya.');
    }

    public function uploadManualDocument(Request $request, ServiceRequest $serviceRequest, MalwareScanner $scanner)
    {
        if (in_array($serviceRequest->status, ['rejected', 'cancelled'], true)) {
            return back()->withErrors(['document' => 'Pengajuan yang ditolak atau dibatalkan tidak menerima dokumen.']);
        }

        $data = $request->validate(['document' => ['required', 'file', 'max:5120', 'mimes:pdf,docx,jpg,jpeg,png']]);
        $file = $data['document'];
        $scanner->assertClean($file);
        $path = $file->store('generated-documents/'.$serviceRequest->request_code, 'private');
        $content = Storage::disk('private')->get($path);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($content) ?: $file->getMimeType();

        DB::transaction(function () use ($serviceRequest, $path, $file, $mime, $content) {
            $serviceRequest->generatedDocuments()->where('is_active', true)->update(['is_active' => false, 'status' => 'superseded']);
            GeneratedDocument::create([
                'service_request_id' => $serviceRequest->id,
                'source' => 'manual',
                'file_path' => $path,
                'original_file_name' => $file->getClientOriginalName(),
                'file_type' => $file->getClientOriginalExtension(),
                'mime_type' => $mime,
                'file_size' => $file->getSize(),
                'checksum' => hash('sha256', $content),
                'status' => 'valid',
                'is_active' => true,
                'generated_by' => auth()->id(),
                'generated_at' => now(),
            ]);
            $serviceRequest->update(['document_source' => 'manual', 'uploaded_document_path' => $path, 'generated_document_path' => null]);
        });

        return back()->with('status', 'Dokumen diunggah. Tandai pengajuan selesai agar warga dapat mengunduhnya.');
    }

    public function generateDocument(Request $request, ServiceRequest $serviceRequest, DocumentGenerationService $generator)
    {
        $data = $request->validate([
            'document_template_id' => ['required', 'exists:document_templates,id'],
            'letter_number' => ['required', 'string', 'max:255', Rule::unique('service_requests', 'letter_number')->ignore($serviceRequest->id)],
            'reason' => [$serviceRequest->status === 'completed' ? 'required' : 'nullable', 'string', 'max:1000'],
        ]);

        $template = DocumentTemplate::with('fields')
            ->where('service_type_id', $serviceRequest->service_type_id)
            ->where('is_active', true)
            ->findOrFail($data['document_template_id']);

        DB::transaction(function () use ($data, $generator, $serviceRequest, $template) {
            $locked = ServiceRequest::lockForUpdate()->findOrFail($serviceRequest->id);
            $locked->update(['letter_number' => $data['letter_number']]);
            $generator->generate($locked->fresh(), $template, $data['reason'] ?? null);
        });

        return back()->with('status', 'Dokumen dibuat ulang dari template.');
    }

    public function downloadDocument(ServiceRequest $serviceRequest, GeneratedDocument $generatedDocument, PrivateDocumentResponse $response)
    {
        abort_unless($generatedDocument->service_request_id === $serviceRequest->id, 404);

        return $response->download($generatedDocument->file_path, $serviceRequest->request_code.'-'.$generatedDocument->id);
    }

    public function sendDocumentWhatsApp(ServiceRequest $serviceRequest, WhatsAppNotificationService $whatsApp)
    {
        $document = $serviceRequest->generatedDocuments()
            ->where('is_active', true)
            ->where('status', 'valid')
            ->latest('generated_at')
            ->first();

        if (! $document) {
            return back()->withErrors(['whatsapp_document' => 'Belum ada dokumen aktif yang dapat dikirim.']);
        }

        // Service-layer failures carry a clerk-readable message and are rendered on the
        // page by the global RuntimeException handler.
        $whatsApp->sendDocument($serviceRequest, $document);

        return back()->with('status', 'Dokumen berhasil dikirim ke WhatsApp '.$serviceRequest->phone.'.');
    }

    public function publish(Request $request, ServiceRequest $serviceRequest, DocumentGenerationService $generator)
    {
        $data = $request->validate([
            'letter_number' => ['required', 'string', 'max:255', Rule::unique('service_requests', 'letter_number')->ignore($serviceRequest->id)],
            'public_note' => ['nullable', 'string', 'max:500'],
        ]);

        if (! $serviceRequest->canTransitionTo('completed')) {
            return back()->withErrors(['status' => 'Pengajuan ini belum dapat diterbitkan dari status saat ini.']);
        }

        $document = null;

        try {
            DB::transaction(function () use ($data, $generator, &$document, $serviceRequest) {
                $locked = ServiceRequest::lockForUpdate()->findOrFail($serviceRequest->id);
                if (! $locked->canTransitionTo('completed')) {
                    throw new InvalidArgumentException('Pengajuan ini belum dapat diterbitkan.');
                }

                $template = DocumentTemplate::with('fields')
                    ->where('service_type_id', $locked->service_type_id)
                    ->where('is_active', true)
                    ->where('is_default', true)
                    ->where('status', 'active')
                    ->first();
                if (! $template) {
                    throw new \RuntimeException('Layanan ini belum mempunyai template surat yang aktif. Aktifkan template pada menu Konfigurasi Layanan, atau unggah dokumen yang ditandatangani secara manual.');
                }

                $locked->update(['letter_number' => $data['letter_number']]);
                $document = $generator->generate($locked->fresh(), $template);
                $locked->fresh()->transitionTo('completed', ($data['public_note'] ?? null) ?: 'Dokumen selesai dan dapat diunduh.');
            });
        } catch (\RuntimeException $exception) {
            if ($document) {
                Storage::disk('private')->delete($document->file_path);
            }
            report($exception);

            return back()->withInput()->withErrors(['final_document' => $exception->getMessage()]);
        } catch (\Throwable $exception) {
            if ($document) {
                Storage::disk('private')->delete($document->file_path);
            }
            report($exception);

            return back()->withInput()->withErrors(['final_document' => 'Dokumen gagal dibuat. Periksa template pada Konfigurasi Layanan lalu coba lagi.']);
        }

        return back()->with('status', 'Dokumen diterbitkan. Pengajuan selesai dan warga dapat mengunduhnya.');
    }

    private function publicNote(Request $request): ?string
    {
        return $request->validate(['public_note' => ['nullable', 'string', 'max:500']])['public_note'] ?? null;
    }

    private function transition(ServiceRequest $serviceRequest, string $status, string $publicNote, string $successMessage, ?string $rejectionReason = null)
    {
        try {
            // Lock the row so a double click cannot write the transition (and the WhatsApp
            // message behind it) twice.
            DB::transaction(function () use ($serviceRequest, $status, $publicNote, $rejectionReason) {
                $locked = ServiceRequest::lockForUpdate()->findOrFail($serviceRequest->id);
                if ($rejectionReason !== null) {
                    $locked->rejection_reason = $rejectionReason;
                }
                $locked->transitionTo($status, $publicNote);
            });
        } catch (InvalidArgumentException) {
            return back()->withErrors(['status' => 'Status pengajuan sudah berubah; muat ulang halaman untuk melihat kondisi terbaru.']);
        }

        return back()->with('status', $successMessage);
    }
}
