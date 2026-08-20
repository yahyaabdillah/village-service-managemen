<?php

namespace App\Http\Controllers;

use App\Models\ServiceRequest;
use App\Services\PrivateDocumentResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DocumentDownloadController extends Controller
{
    public function prompt(ServiceRequest $serviceRequest)
    {
        return redirect()->route('status.form');
    }

    public function authorizeDownload(Request $request, ServiceRequest $serviceRequest, PrivateDocumentResponse $response)
    {
        $data = $request->validate([
            'request_code' => ['required', 'string'],
            'nik' => ['required', 'string'],
        ]);

        if ($serviceRequest->request_code !== $data['request_code'] || $serviceRequest->nik !== $data['nik']) {
            Log::channel('security')->warning('documents.download_denied', [
                'service_request_id' => $serviceRequest->id,
                'ip' => $request->ip(),
            ]);
            abort(403);
        }

        $adminMayDownload = auth()->check() && auth()->user()->can('process service requests');
        abort_unless($serviceRequest->status === 'completed' || $adminMayDownload, 404);

        $document = $serviceRequest->generatedDocuments()->where('is_active', true)->latest('generated_at')->first()
            ?: $serviceRequest->generatedDocuments()->latest('generated_at')->first();
        $path = $document?->file_path
            ?: $serviceRequest->generated_document_path
            ?: $serviceRequest->uploaded_document_path;
        abort_unless($path && Storage::disk('private')->exists($path), 404);

        Log::channel('security')->info('documents.download_authorized', [
            'service_request_id' => $serviceRequest->id,
            'ip' => $request->ip(),
        ]);

        return $response->download($path, $serviceRequest->request_code);
    }
}
