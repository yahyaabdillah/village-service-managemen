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
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
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
        'users' => [User::class, 'Pengguna'],
        'roles' => [Role::class, 'Role'],
    ];

    public function index(Request $request, string $resource)
    {
        [$class, $title] = $this->resolve($resource);
        $query = $class::query();
        match ($resource) {
            'users' => $query->with('roles'),
            'roles' => $query->withCount('permissions'),
            default => $query,
        };
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
            'options' => $this->options($resource),
        ]);
    }

    public function store(Request $request, string $resource)
    {
        [$class] = $this->resolve($resource);
        $data = $this->validated($request, $resource);
        $item = $class::create($this->transform($data, $resource));
        $this->syncRelations($item, $request, $resource);

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
            'options' => $this->options($resource),
        ]);
    }

    public function update(Request $request, int $id, string $resource)
    {
        [$class] = $this->resolve($resource);
        $item = $class::findOrFail($id);
        $data = $this->validated($request, $resource, $id);
        $this->guardAgainstLockout($request, $resource, $item, 'save');
        $item->update($this->transform($data, $resource, $item));
        $this->syncRelations($item, $request, $resource);

        return redirect()->route('admin.'.$resource.'.index')->with('status', 'Data berhasil diperbarui.');
    }

    public function destroy(Request $request, int $id, string $resource)
    {
        [$class] = $this->resolve($resource);
        $item = $class::findOrFail($id);
        $this->guardAgainstLockout($request, $resource, $item, 'delete');
        $item->delete();

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
            'users' => ['name', 'email', 'role_label', 'is_active'],
            'roles' => ['name', 'guard_name', 'permissions_count'],
            default => ['id'],
        };
    }

    /** Choices the form needs that cannot be derived from the model itself. */
    private function options(string $resource): array
    {
        return match ($resource) {
            'users' => [
                'roles' => Role::orderBy('name')->pluck('name')->all(),
                // Inline role creation is a role-management action, so it needs that permission.
                'can_create_roles' => (bool) request()->user()?->can('manage roles'),
            ],
            'roles' => ['permissions' => Permission::orderBy('name')->pluck('name')->all()],
            default => [],
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
            'users' => ['name', 'email', 'password', 'phone', 'is_active', 'roles'],
            'roles' => ['name', 'guard_name', 'permissions'],
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
            'users' => ['name' => ['required'], 'email' => ['required', 'email', 'unique:users,email,'.($id ?? 'NULL').',id'], 'password' => [$id ? 'nullable' : 'required', 'string', 'min:8'], 'phone' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9]{7,18}$/'], 'is_active' => ['nullable', 'boolean'], 'roles' => ['nullable', 'array'], 'roles.*' => ['nullable', 'string', 'max:255', $this->roleAssignable($request)]],
            'roles' => ['name' => ['required', 'unique:roles,name,'.($id ?? 'NULL').',id'], 'guard_name' => ['nullable'], 'permissions' => ['nullable', 'array'], 'permissions.*' => ['string', 'exists:permissions,name']],
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
            unset($data['roles']);
            if (! empty($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            } else {
                unset($data['password']);
            }
        }
        if ($resource === 'roles') {
            unset($data['permissions']);
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

    /**
     * Assigning an existing role only needs `manage users`; naming a role that does not
     * exist yet creates one, which is a `manage roles` action.
     */
    private function roleAssignable(Request $request): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
            if (blank($value) || Role::where('name', $value)->exists()) {
                return;
            }
            if (! $request->user()?->can('manage roles')) {
                $fail('Role "'.$value.'" belum ada dan Anda tidak berhak membuat role baru.');
            }
        };
    }

    /** @return \Illuminate\Support\Collection<int, string> */
    private function submittedRoles(Request $request): \Illuminate\Support\Collection
    {
        return collect($request->input('roles', []))
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->unique()
            ->values();
    }

    private function syncRelations(Model $item, Request $request, string $resource): void
    {
        if ($resource === 'users' && method_exists($item, 'syncRoles')) {
            $item->syncRoles($this->submittedRoles($request)
                ->map(fn (string $name) => Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']))
                ->all());
        }
        if ($resource === 'roles' && $item instanceof Role) {
            $item->syncPermissions($request->input('permissions', []));
        }
    }

    /**
     * Roles are edited through the same role system that guards this screen, so it is
     * possible to revoke your own access and be locked out. Block the cases that do.
     */
    private function guardAgainstLockout(Request $request, string $resource, ?Model $item, string $action): void
    {
        $user = $request->user();
        if (! $user || ! $item) {
            return;
        }

        // Editing your own account: the roles you end up with must still let you back in here.
        if ($resource === 'users' && $action === 'save' && $item->getKey() === $user->getKey()) {
            $names = $this->submittedRoles($request);
            $keepsAccess = Role::whereIn('name', $names)->with('permissions')->get()
                ->flatMap->permissions->pluck('name')
                ->merge($user->getDirectPermissions()->pluck('name'))
                ->contains('manage users');

            if (! $keepsAccess) {
                throw ValidationException::withMessages([
                    'roles' => $names->isEmpty()
                        ? 'Anda tidak dapat mengosongkan role akun Anda sendiri.'
                        : 'Role yang tersisa tidak memberi izin "manage users", sehingga Anda akan kehilangan akses ke halaman ini.',
                ]);
            }
        }

        if ($resource !== 'roles' || ! $item instanceof Role || ! $user->hasRole($item->name)) {
            return;
        }

        // Access may also come from another role or a direct permission; only block a real loss.
        $keptElsewhere = $user->roles->where('name', '!=', $item->name)
            ->flatMap->permissions->pluck('name')
            ->merge($user->getDirectPermissions()->pluck('name'))
            ->contains('manage roles');

        $keptHere = $action === 'save' && in_array('manage roles', (array) $request->input('permissions', []), true);

        if (! $keptElsewhere && ! $keptHere) {
            throw ValidationException::withMessages([
                $action === 'save' ? 'permissions' : 'name' => $action === 'save'
                    ? 'Izin "manage roles" tidak boleh dicabut dari role yang Anda pakai sendiri, karena Anda akan kehilangan akses ke halaman ini.'
                    : 'Role ini sedang Anda pakai dan merupakan satu-satunya sumber izin "manage roles" Anda, jadi tidak dapat dihapus.',
            ]);
        }
    }
}
