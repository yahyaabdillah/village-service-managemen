<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceRequirement;
use App\Models\ServiceType;
use App\Models\ServiceTypeField;
use App\Support\CrudSchema;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * One screen per letter type: its description, the questions the citizen answers, the
 * documents they attach, and the templates that print it. Replaces three flat CRUD
 * tables that had to be cross-referenced by numeric id.
 */
class ServiceConfigurationController extends Controller
{
    public function index()
    {
        $services = ServiceType::withCount([
            'fields as fields_count' => fn ($q) => $q->where('is_active', true),
            'requirements',
            'templates as active_templates_count' => fn ($q) => $q->where('is_active', true),
        ])->withCount('requests')->orderBy('sort_order')->orderBy('name')->get();

        return view('admin.service-types.index', compact('services'));
    }

    public function edit(ServiceType $serviceType, Request $request)
    {
        $serviceType->load([
            'fields' => fn ($q) => $q->orderBy('sort_order')->orderBy('id'),
            'requirements' => fn ($q) => $q->orderBy('sort_order')->orderBy('id'),
            'templates' => fn ($q) => $q->orderByDesc('is_default')->orderByDesc('version'),
        ]);

        return view('admin.service-types.configure', [
            'service' => $serviceType,
            'tab' => $request->string('tab')->toString() ?: 'informasi',
            'fieldTypes' => CrudSchema::FIELD_TYPES,
        ]);
    }

    public function update(Request $request, ServiceType $serviceType)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'alpha_dash', Rule::unique('service_types', 'slug')->ignore($serviceType->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['slug'] = filled($data['slug'] ?? null) ? $data['slug'] : Str::slug($data['name']);
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        $serviceType->update($data);

        return redirect()->route('admin.service-types.edit', [$serviceType, 'tab' => 'informasi'])->with('status', 'Informasi layanan disimpan.');
    }

    // ---- Custom form fields -------------------------------------------------------

    public function storeField(Request $request, ServiceType $serviceType)
    {
        $data = $this->validateField($request, $serviceType);
        $data['sort_order'] = $data['sort_order'] ?? ((int) $serviceType->fields()->max('sort_order') + 1);
        $serviceType->fields()->create($data);

        return $this->backToTab($serviceType, 'isian', 'Isian "'.$data['label'].'" ditambahkan ke formulir.');
    }

    public function updateField(Request $request, ServiceType $serviceType, ServiceTypeField $field)
    {
        abort_unless($field->service_type_id === $serviceType->id, 404);
        $field->update($this->validateField($request, $serviceType, $field));

        return $this->backToTab($serviceType, 'isian', 'Isian "'.$field->label.'" diperbarui.');
    }

    public function destroyField(ServiceType $serviceType, ServiceTypeField $field)
    {
        abort_unless($field->service_type_id === $serviceType->id, 404);
        $field->delete();

        return $this->backToTab($serviceType, 'isian', 'Isian "'.$field->label.'" dihapus dari formulir.');
    }

    // ---- Required documents ---------------------------------------------------------

    public function storeRequirement(Request $request, ServiceType $serviceType)
    {
        $data = $this->validateRequirement($request);
        $data['sort_order'] = $data['sort_order'] ?? ((int) $serviceType->requirements()->max('sort_order') + 1);
        $serviceType->requirements()->create($data);

        return $this->backToTab($serviceType, 'berkas', 'Syarat berkas "'.$data['name'].'" ditambahkan.');
    }

    public function updateRequirement(Request $request, ServiceType $serviceType, ServiceRequirement $requirement)
    {
        abort_unless($requirement->service_type_id === $serviceType->id, 404);
        $requirement->update($this->validateRequirement($request));

        return $this->backToTab($serviceType, 'berkas', 'Syarat berkas "'.$requirement->name.'" diperbarui.');
    }

    public function destroyRequirement(ServiceType $serviceType, ServiceRequirement $requirement)
    {
        abort_unless($requirement->service_type_id === $serviceType->id, 404);
        $requirement->delete();

        return $this->backToTab($serviceType, 'berkas', 'Syarat berkas "'.$requirement->name.'" dihapus.');
    }

    // ---- helpers --------------------------------------------------------------------

    private function validateField(Request $request, ServiceType $serviceType, ?ServiceTypeField $current = null): array
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'field_key' => ['nullable', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/'],
            'field_type' => ['required', Rule::in(array_keys(CrudSchema::FIELD_TYPES))],
            'options_text' => ['nullable', 'string', 'max:2000', 'required_if:field_type,select'],
            'placeholder' => ['nullable', 'string', 'max:255'],
            'help_text' => ['nullable', 'string', 'max:1000'],
            'is_required' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ], [], ['options_text' => 'daftar pilihan']);

        $key = filled($data['field_key'] ?? null) ? $data['field_key'] : Str::snake(Str::slug($data['label'], '_'));
        $taken = $serviceType->fields()->where('field_key', $key)->when($current, fn ($q) => $q->whereKeyNot($current->id))->exists();
        if ($taken) {
            throw \Illuminate\Validation\ValidationException::withMessages(['field_key' => 'Kunci isian "'.$key.'" sudah dipakai pada layanan ini.']);
        }

        $options = $data['field_type'] === 'select'
            ? array_values(array_unique(array_filter(array_map('trim', preg_split('/\r\n|\r|\n|,/', (string) ($data['options_text'] ?? ''))))))
            : null;
        if ($data['field_type'] === 'select' && empty($options)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['options_text' => 'Tuliskan minimal satu pilihan, satu per baris.']);
        }

        return [
            'label' => $data['label'],
            'field_key' => $key,
            'field_type' => $data['field_type'],
            'options' => $options,
            'placeholder' => $data['placeholder'] ?? null,
            'help_text' => $data['help_text'] ?? null,
            'is_required' => (bool) ($data['is_required'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? false),
            'sort_order' => $data['sort_order'] ?? null,
        ];
    }

    private function validateRequirement(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_required' => ['nullable', 'boolean'],
            'allowed_file_types' => ['nullable', 'array'],
            'allowed_file_types.*' => [Rule::in(['pdf', 'jpg', 'jpeg', 'png', 'docx'])],
            'max_file_size_kb' => ['nullable', 'integer', 'min:100', 'max:6144'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $types = array_values(array_unique($data['allowed_file_types'] ?? []));
        if (in_array('jpg', $types, true) && ! in_array('jpeg', $types, true)) {
            $types[] = 'jpeg';
        }

        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_required' => (bool) ($data['is_required'] ?? false),
            'allowed_file_types' => $types ?: ['pdf', 'jpg', 'jpeg', 'png'],
            'max_file_size_kb' => $data['max_file_size_kb'] ?? 5120,
            'sort_order' => $data['sort_order'] ?? null,
        ];
    }

    private function backToTab(ServiceType $serviceType, string $tab, string $message)
    {
        return redirect()->route('admin.service-types.edit', [$serviceType, 'tab' => $tab])->with('status', $message);
    }
}
