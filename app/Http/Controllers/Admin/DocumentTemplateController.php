<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DocumentTemplate;
use App\Models\ServiceRequest;
use App\Models\ServiceType;
use App\Models\ServiceTypeField;
use App\Models\TemplateField;
use App\Services\DocumentGenerationService;
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
    public function index(Request $request)
    {
        $query = DocumentTemplate::with('serviceType')->withCount('fields')
            ->orderByDesc('is_default')->orderByDesc('is_active')->latest('updated_at');

        if ($request->filled('service_type_id')) {
            $query->where('service_type_id', $request->integer('service_type_id'));
        }
        if ($search = trim($request->string('q')->toString())) {
            $query->where('name', 'like', "%{$search}%");
        }

        return view('admin.document-templates.index', [
            'templates' => $query->paginate(20)->withQueryString(),
            'serviceTypes' => ServiceType::orderBy('name')->get(),
            'servicesWithoutLive' => ServiceType::where('is_active', true)
                ->whereDoesntHave('templates', fn ($q) => $q->where('is_active', true)->where('is_default', true)->where('status', 'active'))
                ->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request)
    {
        return view('admin.document-templates.form', [
            'template' => new DocumentTemplate,
            'serviceTypes' => ServiceType::where('is_active', true)->orderBy('name')->get(),
            'selectedServiceId' => (int) old('service_type_id', $request->integer('service_type_id')),
        ]);
    }

    public function store(Request $request, MalwareScanner $scanner)
    {
        $data = $request->validate([
            'service_type_id' => ['required', 'exists:service_types,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'template' => ['required', 'file', 'max:5120', 'mimes:pdf'],
        ]);

        $file = $data['template'];
        $scanner->assertClean($file);
        $path = $file->store('document-templates', 'private');

        try {
            $pageCount = (new Fpdi)->setSourceFile(Storage::disk('private')->path($path));
        } catch (\Throwable) {
            Storage::disk('private')->delete($path);
            throw ValidationException::withMessages(['template' => 'File tidak dapat dibaca sebagai PDF. Simpan ulang dokumen sebagai PDF biasa (bukan terenkripsi) lalu unggah lagi.']);
        }

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

        return redirect()->route('admin.document-templates.builder', $template)
            ->with('status', 'PDF tersimpan. Sekarang tempatkan data yang ingin dicetak di atas halaman, lalu aktifkan template.');
    }

    public function builder(DocumentTemplate $documentTemplate, DocumentVariableRegistry $registry)
    {
        $documentTemplate->load('fields', 'serviceType');

        return view('admin.document-templates.builder', [
            'template' => $documentTemplate,
            'variables' => $registry->for($documentTemplate->serviceType),
            'dateFormats' => self::dateFormatLabels(),
            'dateKeys' => DocumentVariableRegistry::DATE_KEYS,
        ]);
    }

    public function update(Request $request, DocumentTemplate $documentTemplate)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'make_default' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($data, $documentTemplate) {
            $documentTemplate->update(['name' => $data['name'], 'description' => $data['description'] ?? null]);

            if (! empty($data['make_default'])) {
                if (! $documentTemplate->is_active || $documentTemplate->status !== 'active') {
                    throw ValidationException::withMessages(['make_default' => 'Aktifkan template ini terlebih dahulu sebelum menjadikannya template yang dipakai.']);
                }
                DocumentTemplate::where('service_type_id', $documentTemplate->service_type_id)
                    ->whereKeyNot($documentTemplate->id)->update(['is_default' => false]);
                $documentTemplate->update(['is_default' => true]);
            }
        });

        return back()->with('status', 'Pengaturan template disimpan.');
    }

    public function destroy(DocumentTemplate $documentTemplate)
    {
        $serviceTypeId = $documentTemplate->service_type_id;
        $name = $documentTemplate->name;

        DB::transaction(function () use ($documentTemplate) {
            $documentTemplate->update(['is_active' => false, 'is_default' => false, 'status' => 'archived']);
            $documentTemplate->delete();
        });

        return redirect()->route('admin.service-types.edit', [$serviceTypeId, 'tab' => 'template'])
            ->with('status', "Template “{$name}” dihapus. Surat yang sudah diterbitkan tetap tersimpan.");
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

    /**
     * The template filled with example data, so the clerk can check positions before
     * a real request exists. Nothing is stored.
     */
    public function sample(DocumentTemplate $documentTemplate, DocumentGenerationService $generator, DocumentVariableRegistry $registry)
    {
        $documentTemplate->load('fields', 'serviceType');
        $now = now();
        $samples = $registry->samples($documentTemplate->serviceType) + [
            '__raw_letter_date' => $now->toIso8601String(),
            '__raw_submitted_date' => $now->copy()->subDays(2)->toIso8601String(),
            '__raw_completed_date' => $now->toIso8601String(),
        ];
        $samples['letter_date'] = $now->locale('id')->translatedFormat('d F Y');
        $samples['place_date'] = str_replace(['Desa ', 'desa '], '', (string) ($samples['village_name'] ?? 'Ngringo')).', '.$samples['letter_date'];

        $request = new ServiceRequest(['service_type_id' => $documentTemplate->service_type_id, 'request_code' => 'CONTOH']);

        try {
            $content = $generator->render($request, $documentTemplate, $samples);
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['template' => $exception->getMessage()]);
        }

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="contoh-'.Str::slug($documentTemplate->name).'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function storeField(Request $request, DocumentTemplate $documentTemplate)
    {
        $data = $this->validateField($request, $documentTemplate);

        $field = $documentTemplate->fields()->create($data);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Teks ditambahkan.', 'field' => $field], 201);
        }

        return back()->with('status', 'Teks ditambahkan ke template.');
    }

    public function updateField(Request $request, DocumentTemplate $documentTemplate, TemplateField $templateField)
    {
        abort_unless($templateField->document_template_id === $documentTemplate->id, 404);
        $data = $this->validateField($request, $documentTemplate);
        $templateField->update($data);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Tersimpan.', 'field' => $templateField->fresh()]);
        }

        return back()->with('status', 'Teks diperbarui.');
    }

    public function destroyField(DocumentTemplate $documentTemplate, TemplateField $templateField)
    {
        abort_unless($templateField->document_template_id === $documentTemplate->id, 404);
        $templateField->delete();

        if (request()->expectsJson()) {
            return response()->json(status: 204);
        }

        return back()->with('status', 'Teks dihapus dari template.');
    }

    /**
     * A new question for the citizen form, created from inside the builder so its
     * answer can be printed. It appears on the public form right away.
     */
    public function storeVariable(Request $request, DocumentTemplate $documentTemplate)
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'field_key' => ['nullable', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/'],
            'field_type' => ['required', Rule::in(['text', 'textarea', 'number', 'date', 'email', 'select'])],
            'is_required' => ['nullable', 'boolean'],
            'options' => ['nullable', 'array'],
            'options.*' => ['string', 'max:255', 'distinct'],
            'placeholder' => ['nullable', 'string', 'max:255'],
            'help_text' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($data['field_type'] === 'select' && empty($data['options'])) {
            throw ValidationException::withMessages(['options' => 'Tulis pilihan jawabannya, satu per baris.']);
        }

        $key = Str::snake(Str::slug(($data['field_key'] ?? null) ?: $data['label'], '_'));
        if ($key === '' || ! preg_match('/^[a-z][a-z0-9_]*$/', $key)) {
            throw ValidationException::withMessages(['label' => 'Nama pertanyaan harus mengandung huruf.']);
        }
        $taken = ServiceTypeField::where('service_type_id', $documentTemplate->service_type_id)->where('field_key', $key)->exists()
            || array_key_exists($key, array_flip(app(DocumentVariableRegistry::class)->keys($documentTemplate->serviceType)));
        if ($taken) {
            throw ValidationException::withMessages(['label' => "Pertanyaan dengan nama “{$data['label']}” sudah ada pada layanan ini."]);
        }

        $field = ServiceTypeField::create([
            'service_type_id' => $documentTemplate->service_type_id,
            'label' => $data['label'],
            'field_key' => $key,
            'field_type' => $data['field_type'],
            'options' => $data['options'] ?? null,
            'is_required' => (bool) ($data['is_required'] ?? false),
            'is_active' => true,
            'placeholder' => $data['placeholder'] ?? null,
            'help_text' => $data['help_text'] ?? null,
            'sort_order' => ServiceTypeField::where('service_type_id', $documentTemplate->service_type_id)->max('sort_order') + 1,
        ]);

        return response()->json([
            'message' => "Pertanyaan “{$field->label}” ditambahkan ke formulir warga.",
            'variable' => [
                'key' => $field->field_key,
                'label' => $field->label,
                'group' => 'Isian formulir',
                'sample' => $field->placeholder ?: 'Contoh '.strtolower($field->label),
                'source' => 'form',
                'is_active' => true,
            ],
        ], 201);
    }

    public function activate(DocumentTemplate $documentTemplate, DocumentVariableRegistry $registry)
    {
        $documentTemplate->load('fields', 'serviceType');
        if ($documentTemplate->fields->isEmpty()) {
            return back()->withErrors(['template' => 'Tempatkan minimal satu teks di atas halaman sebelum mengaktifkan template.']);
        }

        foreach ($documentTemplate->fields as $field) {
            $this->assertMappingVariables($documentTemplate, $field->mapping_config, $field->variable_key, $registry);
            if ($field->page_number > $documentTemplate->page_count || $field->x_position + $field->width > 100 || $field->y_position + $field->height > 100) {
                return back()->withErrors(['template' => "Teks “{$field->label}” berada di luar halaman. Geser ke dalam halaman lalu coba lagi."]);
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

        return back()->with('status', 'Template aktif dan dipakai untuk menerbitkan '.$documentTemplate->serviceType->name.'.');
    }

    /** @return array<string, string> format => example */
    public static function dateFormatLabels(): array
    {
        $now = now()->locale('id');

        return collect(DocumentMappingResolver::DATE_FORMATS)
            ->mapWithKeys(fn (string $format) => [$format => $now->translatedFormat($format)])
            ->all();
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
            'font_weight' => ['nullable', Rule::in(['normal', 'bold'])],
            'text_align' => ['required', 'in:left,center,right'],
            'text_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        if ($data['x_position'] + $data['width'] > 100 || $data['y_position'] + $data['height'] > 100) {
            throw ValidationException::withMessages(['position' => 'Teks harus berada di dalam halaman.']);
        }

        $this->assertMappingVariables($documentTemplate, $data['mapping_config'] ?? null, $data['variable_key'], app(DocumentVariableRegistry::class));

        return $data;
    }

    private function assertMappingVariables(DocumentTemplate $template, ?array $mapping, string $legacyKey, DocumentVariableRegistry $registry): void
    {
        if (($mapping['mode'] ?? null) === 'literal' && trim((string) ($mapping['value'] ?? '')) === '') {
            throw ValidationException::withMessages(['mapping_config' => 'Teks tetap tidak boleh kosong.']);
        }
        if (($mapping['mode'] ?? null) === 'segments' && empty($mapping['segments'])) {
            throw ValidationException::withMessages(['mapping_config' => 'Gabungan harus mempunyai minimal satu bagian.']);
        }
        if (! empty($mapping['date_format']) && ! in_array($registry->normalize((string) ($mapping['key'] ?? '')), DocumentVariableRegistry::DATE_KEYS, true)) {
            throw ValidationException::withMessages(['mapping_config' => 'Format tanggal hanya berlaku untuk data berupa tanggal.']);
        }

        $allowed = array_map([$registry, 'normalize'], $registry->keys($template->serviceType));
        $keys = $this->mappingKeys($mapping, $legacyKey);

        foreach ($keys as $key) {
            if (! in_array($registry->normalize((string) $key), $allowed, true)) {
                throw ValidationException::withMessages(['mapping_config' => "Data “{$key}” tidak tersedia untuk layanan ini."]);
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
