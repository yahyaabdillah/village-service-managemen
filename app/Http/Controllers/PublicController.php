<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\ServiceRequest;
use App\Models\ServiceType;
use App\Models\VillageProfile;
use App\Services\MalwareScanner;
use App\Services\WhatsAppNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PublicController extends Controller
{
    public function home()
    {
        return view('public.home', [
            'profile' => VillageProfile::where('is_active', true)->first(),
            'services' => ServiceType::where('is_active', true)->orderBy('sort_order')->get(),
            'announcements' => Announcement::where('is_published', true)->latest('published_at')->take(3)->get(),
        ]);
    }

    public function services()
    {
        return view('public.services', [
            'services' => ServiceType::with('requirements')->where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function serviceDetail(ServiceType $serviceType)
    {
        abort_unless($serviceType->is_active, 404);
        $serviceType->load(['requirements', 'fields' => fn ($query) => $query->where('is_active', true)]);

        return view('public.service-detail', compact('serviceType'));
    }

    public function requestForm(ServiceType $serviceType)
    {
        abort_unless($serviceType->is_active, 404);
        $serviceType->load(['requirements', 'fields' => fn ($query) => $query->where('is_active', true)]);

        return view('public.request-form', compact('serviceType'));
    }

    public function submitRequest(Request $request, MalwareScanner $scanner)
    {
        $data = $request->validate([
            'service_type_id' => ['required', Rule::exists('service_types', 'id')->where('is_active', true)],
            'nik' => ['required', 'digits_between:8,20'],
            'applicant_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9]{7,18}$/'],
            'address' => ['required', 'string'],
            'hamlet' => ['nullable', 'string', 'max:255'],
            'rt' => ['nullable', 'string', 'max:10'],
            'rw' => ['nullable', 'string', 'max:10'],
            'fields' => ['nullable', 'array'],
            'requirements' => ['nullable', 'array'],
        ]);

        $service = ServiceType::with(['fields' => fn ($query) => $query->where('is_active', true), 'requirements'])
            ->where('is_active', true)
            ->findOrFail($data['service_type_id']);

        $dynamicRules = [];
        $attributeNames = [];
        foreach ($service->fields as $field) {
            $key = 'fields.'.$field->field_key;
            $dynamicRules[$key] = match ($field->field_type) {
                'date' => [$field->is_required ? 'required' : 'nullable', 'date'],
                'number' => [$field->is_required ? 'required' : 'nullable', 'numeric'],
                'email' => [$field->is_required ? 'required' : 'nullable', 'email', 'max:255'],
                'select' => [
                    $field->is_required ? 'required' : 'nullable',
                    Rule::in($field->options ?: []),
                ],
                'textarea' => [$field->is_required ? 'required' : 'nullable', 'string', 'max:5000'],
                default => [$field->is_required ? 'required' : 'nullable', 'string', 'max:255'],
            };
            $attributeNames[$key] = $field->label;
        }
        foreach ($service->requirements as $req) {
            $key = 'requirements.'.$req->id;
            $allowedTypes = $req->allowed_file_types ?: ['pdf', 'jpg', 'jpeg', 'png', 'docx'];
            $maxSize = min((int) ($req->max_file_size_kb ?: 5120), 5120);
            $dynamicRules[$key] = array_filter([
                $req->is_required ? 'required' : 'nullable',
                'file',
                'max:'.$maxSize,
                'mimes:'.implode(',', $allowedTypes),
            ]);
            $attributeNames[$key] = $req->name;
        }
        Validator::make($request->all(), $dynamicRules, [], $attributeNames)->validate();

        $serviceRequest = DB::transaction(function () use ($data, $request, $service, $scanner) {
            $sr = ServiceRequest::create([
                'request_code' => ServiceRequest::makeRequestCode(),
                'service_type_id' => $service->id,
                'nik' => $data['nik'],
                'applicant_name' => $data['applicant_name'],
                'phone' => $this->normalizePhone($data['phone']),
                'address' => $data['address'],
                'hamlet' => $data['hamlet'] ?? null,
                'rt' => $data['rt'] ?? null,
                'rw' => $data['rw'] ?? null,
                'status' => 'submitted',
                'submitted_at' => now(),
            ]);

            foreach ($service->fields as $field) {
                $value = $request->input('fields.'.$field->field_key);

                $sr->fieldValues()->create([
                    'service_type_field_id' => $field->id,
                    'field_key' => $field->field_key,
                    'label' => $field->label,
                    'value' => is_array($value) ? json_encode($value) : $value,
                ]);
            }

            foreach ($service->requirements as $req) {
                $file = $request->file('requirements.'.$req->id);
                if ($req->is_required && ! $file) {
                    abort(422, 'Berkas '.$req->name.' wajib diunggah.');
                }

                if ($file) {
                    $scanner->assertClean($file);
                    $path = $file->store('service-requests/'.$sr->request_code, 'private');
                    $sr->files()->create([
                        'service_requirement_id' => $req->id,
                        'original_name' => $file->getClientOriginalName(),
                        'file_name' => basename($path),
                        'file_path' => $path,
                        'file_type' => $file->getClientOriginalExtension(),
                        'mime_type' => $file->getMimeType(),
                        'file_size' => $file->getSize(),
                    ]);
                }
            }

            $sr->statusHistories()->create([
                'from_status' => null,
                'to_status' => 'submitted',
                'note' => 'Pengajuan diterima oleh sistem.',
                'is_public' => true,
            ]);

            $requestId = $sr->id;
            DB::afterCommit(fn () => app(WhatsAppNotificationService::class)
                ->notifySubmitted(ServiceRequest::with('serviceType')->find($requestId)));

            return $sr;
        });

        return redirect()
            ->route('requests.success', $serviceRequest)
            ->with('created_service_request_id', $serviceRequest->id);
    }

    private function normalizePhone(string $phone): string
    {
        $phone = trim($phone);
        if (str_starts_with($phone, '+')) {
            return '+'.preg_replace('/\D+/', '', $phone);
        }

        return '+62'.ltrim(preg_replace('/\D+/', '', $phone), '0');
    }

    public function success(Request $request, ServiceRequest $serviceRequest)
    {
        if ((int) $request->session()->get('created_service_request_id') !== $serviceRequest->id) {
            return redirect()->route('status.form');
        }

        return view('public.success', compact('serviceRequest'));
    }

    public function checkStatusForm()
    {
        return view('public.check-status');
    }

    public function checkStatus(Request $request)
    {
        $data = $request->validate([
            'request_code' => ['required', 'string'],
            'nik' => ['required', 'string'],
        ]);

        $serviceRequest = ServiceRequest::with('serviceType', 'publicStatusHistories')
            ->where('request_code', $data['request_code'])
            ->where('nik', $data['nik'])
            ->first();

        $documentPath = $serviceRequest?->generated_document_path ?: $serviceRequest?->uploaded_document_path;
        $documentReady = $serviceRequest?->status === 'completed'
            && $documentPath
            && Storage::disk('private')->exists($documentPath);

        return view('public.status-result', compact('serviceRequest', 'documentReady'));
    }
}
