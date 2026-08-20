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
            'serviceTypes' => ServiceType::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function show(ServiceRequest $serviceRequest)
    {
        $serviceRequest->load('serviceType', 'fieldValues', 'files', 'statusHistories', 'generatedDocuments.documentTemplate');

        return view('admin.service-requests.show', [
            'serviceRequest' => $serviceRequest,
            'availableTemplates' => DocumentTemplate::where('service_type_id', $serviceRequest->service_type_id)
                ->where('is_active', true)->where('status', 'active')->orderByDesc('is_default')->orderByDesc('version')->get(),
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
        return $this->transition($serviceRequest, 'verified', $request->string('public_note')->toString() ?: 'Berkas sedang diverifikasi.', 'Pengajuan berhasil diverifikasi.');
    }

    public function process(Request $request, ServiceRequest $serviceRequest)
    {
        return $this->transition($serviceRequest, 'processing', $request->string('public_note')->toString() ?: 'Pengajuan sedang diproses.', 'Pengajuan diproses.');
    }

    public function reject(Request $request, ServiceRequest $serviceRequest)
    {
        $data = $request->validate(['rejection_reason' => ['required', 'string']]);
        $serviceRequest->rejection_reason = $data['rejection_reason'];

        return $this->transition($serviceRequest, 'rejected', $data['rejection_reason'], 'Pengajuan ditolak.');
    }

    public function uploadManualDocument(Request $request, ServiceRequest $serviceRequest, MalwareScanner $scanner)
    {
        $data = $request->validate(['document' => ['required', 'file', 'max:5120', 'mimes:pdf,docx,jpg,jpeg,png,mp4,mov,webm']]);
        $file = $data['document'];
        $scanner->assertClean($file);
        $path = $file->store('generated-documents/'.$serviceRequest->request_code, 'private');
        $content = Storage::disk('private')->get($path);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($content) ?: $file->getMimeType();
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

        return back()->with('status', 'Dokumen final berhasil diunggah.');
    }

    public function generateDocument(Request $request, ServiceRequest $serviceRequest, DocumentGenerationService $generator)
    {
        $data = $request->validate([
            'document_template_id' => ['required', 'exists:document_templates,id'],
            'letter_number' => ['required', 'string', 'max:255'],
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

        return back()->with('status', 'Dokumen berhasil digenerate.');
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
            return back()->withErrors(['whatsapp_document' => 'Tidak ada dokumen final aktif yang dapat dikirim.']);
        }

        try {
            $whatsApp->sendDocument($serviceRequest, $document);
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withErrors(['whatsapp_document' => 'Dokumen gagal dikirim lewat WhatsApp. Pastikan bridge terhubung lalu coba lagi.']);
        }

        return back()->with('status', 'Dokumen berhasil dikirim lewat WhatsApp.');
    }

    public function complete(Request $request, ServiceRequest $serviceRequest)
    {
        if (! $serviceRequest->canTransitionTo('completed')) {
            return back()->withErrors(['status' => 'Transisi status tidak valid.']);
        }

        if (! $serviceRequest->generatedDocuments()->exists() && ! $serviceRequest->generated_document_path && ! $serviceRequest->uploaded_document_path) {
            return back()->withErrors(['final_document' => 'Tidak bisa menyelesaikan pengajuan tanpa dokumen final.']);
        }

        return $this->transition($serviceRequest, 'completed', $request->string('public_note')->toString() ?: 'Dokumen selesai dan siap diunduh.', 'Pengajuan selesai.');
    }

    public function publish(Request $request, ServiceRequest $serviceRequest, DocumentGenerationService $generator)
    {
        $data = $request->validate(['letter_number' => ['required', 'string', 'max:255']]);

        if (! $serviceRequest->canTransitionTo('completed')) {
            return back()->withErrors(['status' => 'Pengajuan ini belum dapat diterbitkan.']);
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
                    ->firstOrFail();

                $locked->update(['letter_number' => $data['letter_number']]);
                $document = $generator->generate($locked->fresh(), $template);
                $locked->fresh()->transitionTo('completed', 'Dokumen selesai dan siap diunduh.');
            });
        } catch (\Throwable $exception) {
            if ($document) {
                Storage::disk('private')->delete($document->file_path);
            }
            report($exception);

            return back()->withErrors(['final_document' => 'Dokumen gagal dibuat. Periksa template lalu coba lagi.']);
        }

        return back()->with('status', 'Pengajuan disetujui dan dokumen siap diunduh.');
    }

    private function transition(ServiceRequest $serviceRequest, string $status, string $publicNote, string $successMessage)
    {
        try {
            $serviceRequest->transitionTo($status, $publicNote);
        } catch (InvalidArgumentException) {
            return back()->withErrors(['status' => 'Transisi status tidak valid.']);
        }

        return back()->with('status', $successMessage);
    }
}
