<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DocumentTemplate;
use App\Models\ServiceType;
use App\Models\ServiceTypeField;
use App\Models\TemplateField;
use App\Services\DocumentMappingResolver;
use App\Services\DocumentVariableRegistry;
use App\Services\MalwareScanner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use setasign\Fpdi\Fpdi;

class DocumentTemplateController extends Controller
{
    public function index()
    {
        return view('admin.document-templates.index', [
            'templates' => DocumentTemplate::with('serviceType')->latest()->paginate(20),
        ]);
    }

    public function create()
    {
        return view('admin.document-templates.form', [
            'template' => new DocumentTemplate,
            'serviceTypes' => ServiceType::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, MalwareScanner $scanner)
    {
        $data = $request->validate([
            'service_type_id' => ['required', 'exists:service_types,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'template' => ['required', 'file', 'max:5120', 'mimes:pdf'],
        ]);

        $file = $data['template'];
        $scanner->assertClean($file);

        try {
            $pageCount = (new Fpdi)->setSourceFile($file->getRealPath());
        } catch (\Throwable) {
            throw ValidationException::withMessages(['template' => 'File tidak dapat dibaca sebagai PDF yang valid.']);
        }

        $path = $file->store('document-templates', 'private');
        $version = DocumentTemplate::where('service_type_id', $data['service_type_id'])->max('version') + 1;

        $template = DocumentTemplate::create([
            'service_type_id' => $data['service_type_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'template_file_path' => $path,
            'original_file_name' => $file->getClientOriginalName(),
            'page_count' => $pageCount,
            'is_active' => false,
            'status' => 'draft',
            'version' => $version,
            'is_default' => false,
        ]);

        return redirect()->route('admin.document-templates.builder', $template)->with('status', 'Template berhasil dibuat.');
    }

    public function builder(DocumentTemplate $documentTemplate, DocumentVariableRegistry $registry)
    {
        $documentTemplate->load('fields', 'serviceType');

        return view('admin.document-templates.builder', [
            'template' => $documentTemplate,
            'variables' => $registry->for($documentTemplate->serviceType),
            'dateFormats' => DocumentMappingResolver::DATE_FORMATS,
        ]);
    }

    public function preview(DocumentTemplate $documentTemplate)
    {
        abort_unless(Storage::disk('private')->exists($documentTemplate->template_file_path), 404);

        return Storage::disk('private')->response(
            $documentTemplate->template_file_path,
            $documentTemplate->original_file_name ?: 'template.pdf',
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline',
                'Cache-Control' => 'private, no-store',
            ],
        );
    }

    public function storeField(Request $request, DocumentTemplate $documentTemplate)
    {
        $data = $this->validateField($request, $documentTemplate);

        $field = $documentTemplate->fields()->create($data);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Field berhasil ditambahkan.', 'field' => $field], 201);
        }

        return back()->with('status', 'Field template berhasil ditambahkan.');
    }

    public function updateField(Request $request, DocumentTemplate $documentTemplate, TemplateField $templateField)
    {
        abort_unless($templateField->document_template_id === $documentTemplate->id, 404);
        $data = $this->validateField($request, $documentTemplate);
        $templateField->update($data);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Field diperbarui.', 'field' => $templateField->fresh()]);
        }

        return back()->with('status', 'Field diperbarui.');
    }

    public function destroyField(DocumentTemplate $documentTemplate, TemplateField $templateField)
    {
        abort_unless($templateField->document_template_id === $documentTemplate->id, 404);
        $templateField->delete();

        if (request()->expectsJson()) {
            return response()->json(status: 204);
        }

        return back()->with('status', 'Field dihapus.');
    }

    public function storeVariable(Request $request, DocumentTemplate $documentTemplate)
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'field_key' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('service_type_fields')->where('service_type_id', $documentTemplate->service_type_id)],
            'field_type' => ['required', Rule::in(['text', 'textarea', 'number', 'date', 'email', 'select'])],
            'is_required' => ['nullable', 'boolean'],
            'options' => ['nullable', 'array'],
            'options.*' => ['string', 'max:255', 'distinct'],
            'placeholder' => ['nullable', 'string', 'max:255'],
            'help_text' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($data['field_type'] === 'select' && empty($data['options'])) {
            throw ValidationException::withMessages(['options' => 'Pilihan wajib diisi untuk tipe select.']);
        }

        $field = ServiceTypeField::create([
            ...$data,
            'service_type_id' => $documentTemplate->service_type_id,
            'field_key' => Str::snake($data['field_key']),
            'is_active' => false,
            'sort_order' => ServiceTypeField::where('service_type_id', $documentTemplate->service_type_id)->max('sort_order') + 1,
        ]);

        return response()->json([
            'message' => 'Variable form dibuat sebagai draft.',
            'variable' => [
                'key' => $field->field_key,
                'label' => $field->label,
                'group' => 'Form Layanan',
                'source' => 'form',
                'is_active' => false,
            ],
        ], 201);
    }

    public function activate(DocumentTemplate $documentTemplate, DocumentVariableRegistry $registry)
    {
        $documentTemplate->load('fields', 'serviceType');
        if ($documentTemplate->fields->isEmpty()) {
            return back()->withErrors(['template' => 'Template belum mempunyai field.']);
        }

        foreach ($documentTemplate->fields as $field) {
            $this->assertMappingVariables($documentTemplate, $field->mapping_config, $field->variable_key, $registry);
            if ($field->page_number > $documentTemplate->page_count || $field->x_position + $field->width > 100 || $field->y_position + $field->height > 100) {
                return back()->withErrors(['template' => "Field '{$field->label}' berada di luar halaman."]);
            }
        }

        DB::transaction(function () use ($documentTemplate) {
            DocumentTemplate::where('service_type_id', $documentTemplate->service_type_id)
                ->whereKeyNot($documentTemplate->id)
                ->update(['is_default' => false]);

            $keys = $documentTemplate->fields
                ->flatMap(fn ($field) => $this->mappingKeys($field->mapping_config, $field->variable_key))
                ->filter()->unique();
            ServiceTypeField::where('service_type_id', $documentTemplate->service_type_id)
                ->whereIn('field_key', $keys)
                ->update(['is_active' => true]);

            $documentTemplate->update([
                'is_active' => true,
                'is_default' => true,
                'status' => 'active',
                'validated_at' => now(),
            ]);
        });

        return back()->with('status', 'Template tervalidasi dan diaktifkan sebagai default.');
    }

    private function validateField(Request $request, DocumentTemplate $documentTemplate): array
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'variable_key' => ['required', 'string', 'max:100'],
            'mapping_config' => ['nullable', 'array'],
            'mapping_config.version' => ['nullable', 'integer', 'in:1'],
            'mapping_config.mode' => ['nullable', Rule::in(['source', 'literal', 'segments'])],
            'mapping_config.key' => ['nullable', 'string', 'max:100'],
            'mapping_config.value' => ['nullable', 'string', 'max:2000'],
            'mapping_config.prefix' => ['nullable', 'string', 'max:255'],
            'mapping_config.suffix' => ['nullable', 'string', 'max:255'],
            'mapping_config.fallback' => ['nullable', 'string', 'max:255'],
            'mapping_config.date_format' => ['nullable', Rule::in(DocumentMappingResolver::DATE_FORMATS)],
            'mapping_config.segments' => ['nullable', 'array', 'max:20'],
            'mapping_config.segments.*.type' => ['required_with:mapping_config.segments', Rule::in(['source', 'literal'])],
            'mapping_config.segments.*.key' => ['nullable', 'string', 'max:100'],
            'mapping_config.segments.*.value' => ['nullable', 'string', 'max:500'],
            'page_number' => ['required', 'integer', 'min:1', 'max:'.$documentTemplate->page_count],
            'x_position' => ['required', 'numeric', 'between:0,100'],
            'y_position' => ['required', 'numeric', 'between:0,100'],
            'width' => ['required', 'numeric', 'between:1,100'],
            'height' => ['required', 'numeric', 'between:1,100'],
            'font_size' => ['required', 'numeric', 'between:6,72'],
            'text_align' => ['required', 'in:left,center,right'],
            'text_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        if ($data['x_position'] + $data['width'] > 100 || $data['y_position'] + $data['height'] > 100) {
            throw ValidationException::withMessages(['position' => 'Posisi dan ukuran field harus berada di dalam halaman.']);
        }

        $mapping = $data['mapping_config'] ?? null;
        if (($mapping['mode'] ?? null) === 'literal' && trim((string) ($mapping['value'] ?? '')) === '') {
            throw ValidationException::withMessages(['mapping_config.value' => 'Teks tetap tidak boleh kosong.']);
        }
        if (($mapping['mode'] ?? null) === 'segments' && empty($mapping['segments'])) {
            throw ValidationException::withMessages(['mapping_config.segments' => 'Mapping gabungan harus mempunyai minimal satu segmen.']);
        }
        if (! empty($mapping['date_format']) && ! in_array($mapping['key'] ?? '', ['letter_date', 'submitted_date', 'completed_date'], true)) {
            throw ValidationException::withMessages(['mapping_config.date_format' => 'Format tanggal hanya dapat dipakai pada variable tanggal.']);
        }

        $this->assertMappingVariables($documentTemplate, $mapping, $data['variable_key'], app(DocumentVariableRegistry::class));

        return $data;
    }

    private function assertMappingVariables(DocumentTemplate $template, ?array $mapping, string $legacyKey, DocumentVariableRegistry $registry): void
    {
        if (($mapping['mode'] ?? null) === 'literal' && trim((string) ($mapping['value'] ?? '')) === '') {
            throw ValidationException::withMessages(['mapping_config' => 'Teks tetap tidak boleh kosong.']);
        }
        if (($mapping['mode'] ?? null) === 'segments' && empty($mapping['segments'])) {
            throw ValidationException::withMessages(['mapping_config' => 'Mapping gabungan harus mempunyai minimal satu segmen.']);
        }
        if (! empty($mapping['date_format']) && ! in_array($mapping['key'] ?? '', ['letter_date', 'submitted_date', 'completed_date'], true)) {
            throw ValidationException::withMessages(['mapping_config' => 'Format tanggal hanya dapat dipakai pada variable tanggal.']);
        }

        $allowed = $registry->keys($template->serviceType);
        $keys = $this->mappingKeys($mapping, $legacyKey);

        foreach ($keys as $key) {
            if (! in_array($registry->normalize((string) $key), array_map([$registry, 'normalize'], $allowed), true)) {
                throw ValidationException::withMessages(['mapping_config' => "Variable '{$key}' tidak tersedia untuk layanan ini."]);
            }
        }
    }

    private function mappingKeys(?array $mapping, string $legacyKey): array
    {
        return match ($mapping['mode'] ?? 'source') {
            'literal' => [],
            'segments' => collect($mapping['segments'] ?? [])->where('type', 'source')->pluck('key')->all(),
            default => [$mapping['key'] ?? $legacyKey],
        };
    }
}
