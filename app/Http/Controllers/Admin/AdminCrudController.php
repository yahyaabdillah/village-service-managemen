<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\CrudSchema;
use App\Support\PermissionCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * One controller for the simple master-data screens. Everything screen-specific
 * (columns, fields, labels) lives in CrudSchema; this class keeps the validation rules
 * and the handful of behaviours that need code: role sync, user/role protection.
 */
class AdminCrudController extends Controller
{
    /** URL resource → permission resource in PermissionCatalog. */
    private const PERMISSION_KEY = [
        'village-profiles' => 'village-profile',
    ];

    public function index(Request $request, string $resource)
    {
        $schema = CrudSchema::resource($resource);
        $query = $schema['model']::query();
        if (! empty($schema['with'])) {
            $query->with($schema['with']);
        }
        if (! empty($schema['withCount'])) {
            $query->withCount($schema['withCount']);
        }
        if ($search = trim($request->string('q')->toString())) {
            $query->where(function ($q) use ($schema, $search) {
                foreach ($schema['search'] ?? [] as $column) {
                    $q->orWhere($column, 'like', "%{$search}%");
                }
            });
        }

        return view('admin.crud.index', [
            'resource' => $resource,
            'schema' => $schema,
            'permission' => self::PERMISSION_KEY[$resource] ?? $resource,
            'items' => $query->latest('id')->paginate(20)->withQueryString(),
        ]);
    }

    public function create(string $resource)
    {
        $schema = CrudSchema::resource($resource);

        return view('admin.crud.form', [
            'resource' => $resource,
            'schema' => $schema,
            'title' => 'Tambah '.$schema['singular'],
            'item' => new $schema['model'],
            'options' => $this->options($resource),
        ]);
    }

    public function store(Request $request, string $resource)
    {
        $schema = CrudSchema::resource($resource);
        $data = $this->validated($request, $resource);
        $item = $schema['model']::create($this->transform($data, $resource));
        $this->syncRelations($item, $request, $resource);

        return redirect()->route('admin.'.$resource.'.index')->with('status', ucfirst($schema['singular']).' berhasil ditambahkan.');
    }

    public function edit(int $id, string $resource)
    {
        $schema = CrudSchema::resource($resource);

        return view('admin.crud.form', [
            'resource' => $resource,
            'schema' => $schema,
            'title' => 'Ubah '.$schema['singular'],
            'item' => $schema['model']::findOrFail($id),
            'options' => $this->options($resource),
        ]);
    }

    public function update(Request $request, int $id, string $resource)
    {
        $schema = CrudSchema::resource($resource);
        $item = $schema['model']::findOrFail($id);
        $data = $this->validated($request, $resource, $id);
        $this->guardAgainstLockout($request, $resource, $item, 'save');
        $item->update($this->transform($data, $resource, $item));
        $this->syncRelations($item, $request, $resource);

        return redirect()->route('admin.'.$resource.'.index')->with('status', 'Perubahan '.$schema['singular'].' disimpan.');
    }

    public function destroy(Request $request, int $id, string $resource)
    {
        $schema = CrudSchema::resource($resource);
        $item = $schema['model']::findOrFail($id);
        $this->guardAgainstLockout($request, $resource, $item, 'delete');
        $item->delete();

        return back()->with('status', ucfirst($schema['singular']).' dihapus.');
    }

    /** Choices the form needs that cannot be derived from the model itself. */
    private function options(string $resource): array
    {
        $actor = request()->user();

        return match ($resource) {
            'users' => [
                // Only a Super Admin may hand out the Super Admin role, so hide it from others.
                'roles' => Role::orderBy('name')->pluck('name')
                    ->reject(fn ($name) => $name === PermissionCatalog::SUPER_ROLE && ! $this->isSuper($actor))
                    ->values()->all(),
                // Inline role creation is a role-management action, so it needs that permission.
                'can_create_roles' => (bool) $actor?->can('roles.create'),
            ],
            default => [],
        };
    }

    private function validated(Request $request, string $resource, ?int $id = null): array
    {
        $unique = fn (string $table, string $column) => 'unique:'.$table.','.$column.','.($id ?? 'NULL').',id';
        $phone = ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9]{7,18}$/'];

        $rules = match ($resource) {
            'family-cards' => ['family_card_number' => ['required', 'digits:16', $unique('family_cards', 'family_card_number')], 'head_of_family_name' => ['required', 'string', 'max:255'], 'address' => ['required', 'string'], 'hamlet' => ['nullable', 'string', 'max:255'], 'rt' => ['nullable', 'string', 'max:10'], 'rw' => ['nullable', 'string', 'max:10'], 'postal_code' => ['nullable', 'string', 'max:10']],
            'residents' => ['family_card_id' => ['nullable', 'exists:family_cards,id'], 'nik' => ['required', 'digits:16', $unique('residents', 'nik')], 'name' => ['required', 'string', 'max:255'], 'gender' => ['required', 'in:male,female'], 'birth_place' => ['nullable', 'string', 'max:255'], 'birth_date' => ['nullable', 'date', 'before:tomorrow'], 'address' => ['required', 'string'], 'hamlet' => ['nullable', 'string', 'max:255'], 'rt' => ['nullable', 'string', 'max:10'], 'rw' => ['nullable', 'string', 'max:10'], 'religion' => ['nullable', 'string', 'max:50'], 'marital_status' => ['nullable', 'string', 'max:50'], 'occupation' => ['nullable', 'string', 'max:255'], 'phone' => $phone, 'is_active' => ['nullable', 'boolean']],
            'village-profiles' => ['village_name' => ['required', 'string', 'max:255'], 'district' => ['nullable', 'string', 'max:255'], 'regency' => ['nullable', 'string', 'max:255'], 'province' => ['nullable', 'string', 'max:255'], 'address' => ['nullable', 'string'], 'phone' => $phone, 'email' => ['nullable', 'email'], 'website' => ['nullable', 'url'], 'village_head_name' => ['nullable', 'string', 'max:255'], 'village_head_nip' => ['nullable', 'string', 'max:50'], 'default_signer_name' => ['nullable', 'string', 'max:255'], 'default_signer_title' => ['nullable', 'string', 'max:255'], 'is_active' => ['nullable', 'boolean']],
            'service-types' => ['name' => ['required', 'string', 'max:255'], 'slug' => ['nullable', 'alpha_dash', $unique('service_types', 'slug')], 'description' => ['nullable', 'string'], 'is_active' => ['nullable', 'boolean'], 'sort_order' => ['nullable', 'integer', 'min:0']],
            'announcements' => ['title' => ['required', 'string', 'max:255'], 'content' => ['required', 'string'], 'excerpt' => ['nullable', 'string', 'max:500'], 'published_at' => ['nullable', 'date'], 'is_published' => ['nullable', 'boolean']],
            'users' => ['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', $unique('users', 'email')], 'password' => [$id ? 'nullable' : 'required', 'string', 'min:8'], 'phone' => $phone, 'is_active' => ['nullable', 'boolean'], 'roles' => ['nullable', 'array'], 'roles.*' => ['nullable', 'string', 'max:255', $this->roleAssignable($request)]],
            'roles' => ['name' => ['required', 'string', 'max:255', $unique('roles', 'name')], 'permissions' => ['nullable', 'array'], 'permissions.*' => ['string', 'exists:permissions,name']],
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
            $data['slug'] = filled($data['slug'] ?? null) ? $data['slug'] : Str::slug($data['name']);
        }
        if ($resource === 'announcements') {
            $data['slug'] = $item?->slug ?: Str::slug($data['title']).'-'.Str::lower(Str::random(4));
            if (! empty($data['is_published']) && empty($data['published_at'])) {
                $data['published_at'] = now();
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
            $data['guard_name'] = 'web';
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

    private function isSuper(?User $user): bool
    {
        return (bool) $user?->hasRole(PermissionCatalog::SUPER_ROLE);
    }

    /**
     * Assigning an existing role only needs `users.update`; naming a role that does not
     * exist yet creates one, which is a `roles.create` action; and the Super Admin role
     * may only be granted by someone who already holds it.
     */
    private function roleAssignable(Request $request): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
            if (blank($value)) {
                return;
            }
            if ($value === PermissionCatalog::SUPER_ROLE && ! $this->isSuper($request->user())) {
                $fail('Hanya Super Admin yang dapat memberikan role Super Admin.');

                return;
            }
            if (Role::where('name', $value)->exists()) {
                return;
            }
            if (! $request->user()?->can('roles.create')) {
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
            // Super Admin's matrix is informational: it always holds everything.
            $item->syncPermissions($item->name === PermissionCatalog::SUPER_ROLE
                ? PermissionCatalog::all()
                : $request->input('permissions', []));
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

        if ($resource === 'users' && $item instanceof User) {
            $this->guardUserChange($request, $user, $item, $action);
        }

        if ($resource === 'roles' && $item instanceof Role) {
            $this->guardRoleChange($request, $user, $item, $action);
        }
    }

    private function guardUserChange(Request $request, User $actor, User $target, string $action): void
    {
        $isSelf = $target->getKey() === $actor->getKey();

        if ($action === 'delete') {
            if ($isSelf) {
                throw ValidationException::withMessages(['name' => 'Anda tidak dapat menghapus akun Anda sendiri.']);
            }
            if ($this->isSuper($target) && ! $this->isSuper($actor)) {
                throw ValidationException::withMessages(['name' => 'Akun Super Admin hanya dapat dihapus oleh Super Admin lain.']);
            }

            return;
        }

        if ($this->isSuper($target) && ! $this->isSuper($actor)) {
            throw ValidationException::withMessages(['roles' => 'Akun Super Admin hanya dapat diubah oleh Super Admin.']);
        }

        // Editing your own account: the roles you end up with must still let you back in here.
        // A Super Admin bypasses every check, so only non-super accounts can lock themselves out.
        if ($isSelf && ! $this->isSuper($actor)) {
            $names = $this->submittedRoles($request);
            $granted = Role::whereIn('name', $names)->with('permissions')->get()
                ->flatMap->permissions->pluck('name')
                ->merge($actor->getDirectPermissions()->pluck('name'));

            $missing = collect(['users.view', 'users.update'])->reject(fn ($p) => $granted->contains($p));
            if ($missing->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'roles' => $names->isEmpty()
                        ? 'Anda tidak dapat mengosongkan role akun Anda sendiri.'
                        : 'Role yang tersisa tidak lagi memberi izin mengelola pengguna, sehingga Anda akan kehilangan akses ke halaman ini.',
                ]);
            }
        }
    }

    private function guardRoleChange(Request $request, User $actor, Role $role, string $action): void
    {
        if ($role->name === PermissionCatalog::SUPER_ROLE) {
            if ($action === 'delete') {
                throw ValidationException::withMessages(['name' => 'Role Super Admin tidak dapat dihapus.']);
            }
            if ($request->string('name')->toString() !== PermissionCatalog::SUPER_ROLE) {
                throw ValidationException::withMessages(['name' => 'Nama role Super Admin tidak dapat diubah.']);
            }
            if (! $this->isSuper($actor)) {
                throw ValidationException::withMessages(['name' => 'Role Super Admin hanya dapat diubah oleh Super Admin.']);
            }

            return;
        }

        if ($this->isSuper($actor) || ! $actor->hasRole($role->name)) {
            return;
        }

        // Access may also come from another role or a direct permission; only block a real loss.
        $keptElsewhere = $actor->roles->where('name', '!=', $role->name)
            ->flatMap->permissions->pluck('name')
            ->merge($actor->getDirectPermissions()->pluck('name'));
        $keptHere = $action === 'save' ? collect((array) $request->input('permissions', [])) : collect();

        $lost = collect(['roles.view', 'roles.update'])
            ->reject(fn ($p) => $keptElsewhere->contains($p) || $keptHere->contains($p));

        if ($lost->isNotEmpty()) {
            throw ValidationException::withMessages([
                $action === 'save' ? 'permissions' : 'name' => $action === 'save'
                    ? 'Izin melihat dan mengubah role tidak boleh dicabut dari role yang Anda pakai sendiri, karena Anda akan kehilangan akses ke halaman ini.'
                    : 'Role ini sedang Anda pakai dan merupakan satu-satunya sumber izin mengelola role, jadi tidak dapat dihapus.',
            ]);
        }
    }
}
