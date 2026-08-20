<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\FamilyCard;
use App\Models\Resident;
use App\Models\ServiceRequirement;
use App\Models\ServiceType;
use App\Models\ServiceTypeField;
use App\Models\User;
use App\Models\VillageProfile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class AdminCrudController extends Controller
{
    private array $map = [
        'village-profiles' => [VillageProfile::class, 'Profil Desa'],
        'family-cards' => [FamilyCard::class, 'Kartu Keluarga'],
        'residents' => [Resident::class, 'Data Penduduk'],
        'service-types' => [ServiceType::class, 'Jenis Layanan'],
        'service-requirements' => [ServiceRequirement::class, 'Syarat Layanan'],
        'service-type-fields' => [ServiceTypeField::class, 'Field Layanan'],
        'announcements' => [Announcement::class, 'Pengumuman'],
        'users' => [User::class, 'Users'],
        'roles' => [Role::class, 'Roles'],
    ];

    public function index(Request $request, string $resource)
    {
        [$class, $title] = $this->resolve($resource);
        $query = $class::query();
        if ($search = $request->string('q')->toString()) {
            $query = $this->applySearch($query, $resource, $search);
        }

        return view('admin.crud.index', [
            'resource' => $resource,
            'title' => $title,
            'items' => $query->latest('id')->paginate(20),
            'columns' => $this->columns($resource),
        ]);
    }

    public function create(string $resource)
    {
        [$class, $title] = $this->resolve($resource);

        return view('admin.crud.form', [
            'resource' => $resource,
            'title' => 'Tambah '.$title,
            'item' => new $class,
            'fields' => $this->fields($resource),
        ]);
    }

    public function store(Request $request, string $resource)
    {
        [$class] = $this->resolve($resource);
        $data = $this->validated($request, $resource);
        $item = $class::create($this->transform($data, $resource));
        $this->syncRole($item, $request, $resource);

        return redirect()->route('admin.'.$resource.'.index')->with('status', 'Data berhasil dibuat.');
    }

    public function edit(int $id, string $resource)
    {
        [$class, $title] = $this->resolve($resource);

        return view('admin.crud.form', [
            'resource' => $resource,
            'title' => 'Edit '.$title,
            'item' => $class::findOrFail($id),
            'fields' => $this->fields($resource),
        ]);
    }

    public function update(Request $request, int $id, string $resource)
    {
        [$class] = $this->resolve($resource);
        $item = $class::findOrFail($id);
        $data = $this->validated($request, $resource, $id);
        $item->update($this->transform($data, $resource, $item));
        $this->syncRole($item, $request, $resource);

        return redirect()->route('admin.'.$resource.'.index')->with('status', 'Data berhasil diperbarui.');
    }

    public function destroy(int $id, string $resource)
    {
        [$class] = $this->resolve($resource);
        $class::findOrFail($id)->delete();

        return back()->with('status', 'Data dihapus.');
    }

    private function resolve(string $resource): array
    {
        abort_unless(isset($this->map[$resource]), 404);

        return $this->map[$resource];
    }

    private function applySearch($query, string $resource, string $search)
    {
        return match ($resource) {
            'family-cards' => $query->where('family_card_number', 'like', "%$search%")->orWhere('head_of_family_name', 'like', "%$search%"),
            'residents' => $query->where('nik', 'like', "%$search%")->orWhere('name', 'like', "%$search%"),
            'service-types', 'announcements', 'roles' => $query->where('name', 'like', "%$search%"),
            'users' => $query->where('name', 'like', "%$search%")->orWhere('email', 'like', "%$search%"),
            default => $query,
        };
    }

    private function columns(string $resource): array
    {
        return match ($resource) {
            'village-profiles' => ['village_name', 'district', 'regency', 'is_active'],
            'family-cards' => ['family_card_number', 'head_of_family_name', 'hamlet', 'rt', 'rw'],
            'residents' => ['nik', 'name', 'gender', 'hamlet', 'rt', 'rw', 'is_active'],
            'service-types' => ['name', 'slug', 'is_active', 'sort_order'],
            'service-requirements' => ['service_type_id', 'name', 'is_required', 'max_file_size_kb'],
            'service-type-fields' => ['service_type_id', 'label', 'field_key', 'field_type', 'is_required'],
            'announcements' => ['title', 'slug', 'is_published', 'published_at'],
            'users' => ['name', 'email', 'is_active'],
            'roles' => ['name', 'guard_name'],
            default => ['id'],
        };
    }

    private function fields(string $resource): array
    {
        return match ($resource) {
            'family-cards' => ['family_card_number', 'head_of_family_name', 'address', 'hamlet', 'rt', 'rw', 'postal_code'],
            'residents' => ['family_card_id', 'nik', 'name', 'gender', 'birth_place', 'birth_date', 'address', 'hamlet', 'rt', 'rw', 'religion', 'marital_status', 'occupation', 'phone', 'is_active'],
            'village-profiles' => ['village_name', 'district', 'regency', 'province', 'address', 'phone', 'email', 'website', 'village_head_name', 'village_head_nip', 'default_signer_name', 'default_signer_title', 'is_active'],
            'service-types' => ['name', 'slug', 'description', 'is_active', 'sort_order'],
            'service-requirements' => ['service_type_id', 'name', 'description', 'is_required', 'allowed_file_types', 'max_file_size_kb', 'sort_order'],
            'service-type-fields' => ['service_type_id', 'label', 'field_key', 'field_type', 'options', 'is_required', 'placeholder', 'help_text', 'sort_order'],
            'announcements' => ['title', 'slug', 'content', 'excerpt', 'published_at', 'is_published'],
            'users' => ['name', 'email', 'password', 'phone', 'is_active', 'role'],
            'roles' => ['name', 'guard_name'],
            default => [],
        };
    }

    private function validated(Request $request, string $resource, ?int $id = null): array
    {
        $rules = match ($resource) {
            'family-cards' => ['family_card_number' => ['required', 'string', 'max:50', 'unique:family_cards,family_card_number,'.($id ?? 'NULL').',id'], 'head_of_family_name' => ['required'], 'address' => ['required'], 'hamlet' => ['nullable'], 'rt' => ['nullable'], 'rw' => ['nullable'], 'postal_code' => ['nullable']],
            'residents' => ['family_card_id' => ['nullable', 'exists:family_cards,id'], 'nik' => ['required', 'string', 'unique:residents,nik,'.($id ?? 'NULL').',id'], 'name' => ['required'], 'gender' => ['required'], 'birth_place' => ['nullable'], 'birth_date' => ['nullable', 'date'], 'address' => ['required'], 'hamlet' => ['nullable'], 'rt' => ['nullable'], 'rw' => ['nullable'], 'religion' => ['nullable'], 'marital_status' => ['nullable'], 'occupation' => ['nullable'], 'phone' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9]{7,18}$/'], 'is_active' => ['nullable', 'boolean']],
            'village-profiles' => ['village_name' => ['required'], 'district' => ['nullable'], 'regency' => ['nullable'], 'province' => ['nullable'], 'address' => ['nullable'], 'phone' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9]{7,18}$/'], 'email' => ['nullable', 'email'], 'website' => ['nullable'], 'village_head_name' => ['nullable'], 'village_head_nip' => ['nullable'], 'default_signer_name' => ['nullable'], 'default_signer_title' => ['nullable'], 'is_active' => ['nullable', 'boolean']],
            'service-types' => ['name' => ['required'], 'slug' => ['nullable', 'unique:service_types,slug,'.($id ?? 'NULL').',id'], 'description' => ['nullable'], 'is_active' => ['nullable', 'boolean'], 'sort_order' => ['nullable', 'integer']],
            'service-requirements' => ['service_type_id' => ['required', 'exists:service_types,id'], 'name' => ['required'], 'description' => ['nullable'], 'is_required' => ['nullable', 'boolean'], 'allowed_file_types' => ['nullable'], 'max_file_size_kb' => ['nullable', 'integer'], 'sort_order' => ['nullable', 'integer']],
            'service-type-fields' => ['service_type_id' => ['required', 'exists:service_types,id'], 'label' => ['required'], 'field_key' => ['required'], 'field_type' => ['required'], 'options' => ['nullable'], 'is_required' => ['nullable', 'boolean'], 'placeholder' => ['nullable'], 'help_text' => ['nullable'], 'sort_order' => ['nullable', 'integer']],
            'announcements' => ['title' => ['required'], 'slug' => ['nullable', 'unique:announcements,slug,'.($id ?? 'NULL').',id'], 'content' => ['required'], 'excerpt' => ['nullable'], 'published_at' => ['nullable', 'date'], 'is_published' => ['nullable', 'boolean']],
            'users' => ['name' => ['required'], 'email' => ['required', 'email', 'unique:users,email,'.($id ?? 'NULL').',id'], 'password' => [$id ? 'nullable' : 'required', 'string', 'min:8'], 'phone' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9]{7,18}$/'], 'is_active' => ['nullable', 'boolean'], 'role' => ['nullable', 'exists:roles,name']],
            'roles' => ['name' => ['required', 'unique:roles,name,'.($id ?? 'NULL').',id'], 'guard_name' => ['nullable']],
            default => [],
        };

        return $request->validate($rules);
    }

    private function transform(array $data, string $resource, ?Model $item = null): array
    {
        foreach (['is_active', 'is_required', 'is_published'] as $bool) {
            if (array_key_exists($bool, $data)) {
                $data[$bool] = (bool) $data[$bool];
            }
        }
        if ($resource === 'service-types') {
            $data['slug'] = $data['slug'] ?? Str::slug($data['name']);
        }
        if ($resource === 'announcements') {
            $data['slug'] = $data['slug'] ?? Str::slug($data['title']);
        }
        if (in_array($resource, ['service-requirements', 'service-type-fields'])) {
            foreach (['allowed_file_types', 'options'] as $json) {
                if (isset($data[$json]) && is_string($data[$json])) {
                    $data[$json] = array_values(array_filter(array_map('trim', explode(',', $data[$json]))));
                }
            }
        }
        if (array_key_exists('phone', $data) && filled($data['phone'])) {
            $data['phone'] = $this->normalizePhone($data['phone']);
        }
        if ($resource === 'users') {
            unset($data['role']);
            if (! empty($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            } else {
                unset($data['password']);
            }
        }
        if ($resource === 'roles') {
            $data['guard_name'] = $data['guard_name'] ?? 'web';
        }

        return $data;
    }

    private function normalizePhone(string $phone): string
    {
        $phone = trim($phone);
        if (str_starts_with($phone, '+')) {
            return '+'.preg_replace('/\D+/', '', $phone);
        }

        return '+62'.ltrim(preg_replace('/\D+/', '', $phone), '0');
    }

    private function syncRole(Model $item, Request $request, string $resource): void
    {
        if ($resource === 'users' && $request->filled('role') && method_exists($item, 'syncRoles')) {
            $item->syncRoles([$request->string('role')->toString()]);
        }
    }
}
