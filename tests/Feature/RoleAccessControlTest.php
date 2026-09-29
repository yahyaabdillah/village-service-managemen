<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleAccessControlTest extends TestCase
{
    use RefreshDatabase;

    private const ROLE_ADMIN = ['roles.view', 'roles.create', 'roles.update', 'roles.delete'];

    private const USER_ADMIN = ['users.view', 'users.create', 'users.update', 'users.delete'];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (PermissionCatalog::all() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
    }

    private function userWith(array $permissions, string $roleName = 'Penguji'): User
    {
        $role = Role::findOrCreate($roleName, 'web');
        $role->syncPermissions($permissions);

        return tap(User::factory()->create())->syncRoles([$role]);
    }

    private function superAdmin(): User
    {
        Role::findOrCreate(PermissionCatalog::SUPER_ROLE, 'web');

        return tap(User::factory()->create())->syncRoles([PermissionCatalog::SUPER_ROLE]);
    }

    public function test_catalog_covers_every_route_guard(): void
    {
        $guarded = collect(app('router')->getRoutes()->getRoutes())
            ->flatMap(fn ($route) => collect($route->gatherMiddleware())
                ->filter(fn ($m) => is_string($m) && str_starts_with($m, \App\Http\Middleware\EnsureUserCan::class.':'))
                ->map(fn ($m) => substr($m, strlen(\App\Http\Middleware\EnsureUserCan::class) + 1)))
            ->unique()->values();

        $unknown = $guarded->diff(PermissionCatalog::all());
        $this->assertTrue($unknown->isEmpty(), 'Routes guard permissions missing from the catalog: '.$unknown->implode(', '));
    }

    public function test_super_admin_passes_every_check_without_explicit_grants(): void
    {
        $super = $this->superAdmin();

        $this->assertTrue($super->can('residents.delete'));
        $this->assertTrue($super->can('a-permission-that-does-not-exist-yet'));
        $this->actingAs($super)->get(route('admin.roles.index'))->assertOk();
    }

    public function test_role_form_saves_selected_permissions(): void
    {
        $actor = $this->userWith(self::ROLE_ADMIN);

        $this->actingAs($actor)->post(route('admin.roles.store'), [
            'name' => 'Operator Surat',
            'permissions' => ['residents.view', 'residents.import'],
        ])->assertRedirect(route('admin.roles.index'));

        $saved = Role::where('name', 'Operator Surat')->firstOrFail();
        $this->assertEqualsCanonicalizing(['residents.view', 'residents.import'], $saved->permissions->pluck('name')->all());
        $this->assertSame('web', $saved->guard_name);
    }

    public function test_role_form_rejects_unknown_permissions(): void
    {
        $actor = $this->userWith(self::ROLE_ADMIN);

        $this->actingAs($actor)->post(route('admin.roles.store'), [
            'name' => 'Aneh',
            'permissions' => ['manage everything'],
        ])->assertSessionHasErrors('permissions.0');
    }

    public function test_existing_role_can_be_assigned_with_only_user_permissions(): void
    {
        $actor = $this->userWith(self::USER_ADMIN);
        Role::findOrCreate('Petugas', 'web');

        $this->actingAs($actor)->post(route('admin.users.store'), [
            'name' => 'Budi',
            'email' => 'budi@desa.test',
            'password' => 'rahasia123',
            'roles' => ['Petugas'],
        ])->assertSessionHasNoErrors();

        $this->assertTrue(User::where('email', 'budi@desa.test')->firstOrFail()->hasRole('Petugas'));
    }

    public function test_naming_a_new_role_is_refused_without_roles_create(): void
    {
        $actor = $this->userWith(self::USER_ADMIN);

        $this->actingAs($actor)->post(route('admin.users.store'), [
            'name' => 'Budi',
            'email' => 'budi@desa.test',
            'password' => 'rahasia123',
            'roles' => ['Role Karangan'],
        ])->assertSessionHasErrors('roles.0');

        $this->assertFalse(Role::where('name', 'Role Karangan')->exists());
        $this->assertFalse(User::where('email', 'budi@desa.test')->exists());
    }

    public function test_naming_a_new_role_creates_it_with_roles_create(): void
    {
        $actor = $this->userWith([...self::USER_ADMIN, 'roles.create']);

        $this->actingAs($actor)->post(route('admin.users.store'), [
            'name' => 'Budi',
            'email' => 'budi@desa.test',
            'password' => 'rahasia123',
            'roles' => ['Bendahara'],
        ])->assertSessionHasNoErrors();

        $this->assertTrue(User::where('email', 'budi@desa.test')->firstOrFail()->hasRole('Bendahara'));
    }

    public function test_several_roles_can_be_assigned_at_once(): void
    {
        $actor = $this->userWith(self::USER_ADMIN);
        Role::findOrCreate('Petugas', 'web');
        Role::findOrCreate('Bendahara', 'web');

        $this->actingAs($actor)->post(route('admin.users.store'), [
            'name' => 'Budi',
            'email' => 'budi@desa.test',
            'password' => 'rahasia123',
            'roles' => ['Petugas', 'Bendahara', 'Petugas', '  '],
        ])->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing(['Petugas', 'Bendahara'], User::where('email', 'budi@desa.test')->firstOrFail()->getRoleNames()->all());
    }

    public function test_only_a_super_admin_can_grant_the_super_admin_role(): void
    {
        Role::findOrCreate(PermissionCatalog::SUPER_ROLE, 'web');
        $actor = $this->userWith([...self::USER_ADMIN, ...self::ROLE_ADMIN]);

        $this->actingAs($actor)->post(route('admin.users.store'), [
            'name' => 'Penyusup',
            'email' => 'penyusup@desa.test',
            'password' => 'rahasia123',
            'roles' => [PermissionCatalog::SUPER_ROLE],
        ])->assertSessionHasErrors('roles.0');

        $this->assertFalse(User::where('email', 'penyusup@desa.test')->exists());

        $this->actingAs($this->superAdmin())->post(route('admin.users.store'), [
            'name' => 'Wakil',
            'email' => 'wakil@desa.test',
            'password' => 'rahasia123',
            'roles' => [PermissionCatalog::SUPER_ROLE],
        ])->assertSessionHasNoErrors();

        $this->assertTrue(User::where('email', 'wakil@desa.test')->firstOrFail()->hasRole(PermissionCatalog::SUPER_ROLE));
    }

    public function test_super_admin_role_cannot_be_renamed_stripped_or_deleted(): void
    {
        $super = $this->superAdmin();
        $role = Role::where('name', PermissionCatalog::SUPER_ROLE)->firstOrFail();
        $actor = $this->userWith(self::ROLE_ADMIN);

        $this->actingAs($actor)->patch(route('admin.roles.update', $role->id), ['name' => 'Bukan Super', 'permissions' => []])
            ->assertSessionHasErrors('name');
        $this->actingAs($actor)->delete(route('admin.roles.destroy', $role->id))->assertSessionHasErrors('name');
        $this->assertTrue(Role::whereKey($role->id)->exists());

        // Even a Super Admin submitting an empty matrix leaves the role holding everything.
        $this->actingAs($super)->patch(route('admin.roles.update', $role->id), ['name' => PermissionCatalog::SUPER_ROLE, 'permissions' => []])
            ->assertSessionHasNoErrors();
        $this->assertCount(count(PermissionCatalog::all()), $role->fresh()->permissions);
    }

    public function test_own_role_cannot_lose_role_management(): void
    {
        $actor = $this->userWith(self::ROLE_ADMIN, 'Pengelola');
        $role = Role::where('name', 'Pengelola')->firstOrFail();

        $this->actingAs($actor)->patch(route('admin.roles.update', $role->id), [
            'name' => 'Pengelola',
            'permissions' => ['residents.view'],
        ])->assertSessionHasErrors('permissions');

        $this->assertTrue($actor->fresh()->can('roles.update'));
    }

    public function test_own_role_may_lose_role_management_when_another_role_still_grants_it(): void
    {
        $actor = $this->userWith(self::ROLE_ADMIN, 'Pengelola');
        $backup = Role::findOrCreate('Cadangan', 'web');
        $backup->syncPermissions(['roles.view', 'roles.update']);
        $actor->assignRole($backup);

        $role = Role::where('name', 'Pengelola')->firstOrFail();

        $this->actingAs($actor->fresh())->patch(route('admin.roles.update', $role->id), [
            'name' => 'Pengelola',
            'permissions' => ['residents.view'],
        ])->assertSessionHasNoErrors();

        $this->assertTrue($actor->fresh()->can('roles.update'));
    }

    public function test_own_role_cannot_be_deleted(): void
    {
        $actor = $this->userWith(self::ROLE_ADMIN, 'Pengelola');
        $role = Role::where('name', 'Pengelola')->firstOrFail();

        $this->actingAs($actor)->delete(route('admin.roles.destroy', $role->id))->assertSessionHasErrors('name');
        $this->assertTrue(Role::whereKey($role->id)->exists());
    }

    public function test_own_account_cannot_be_left_without_user_management(): void
    {
        $actor = $this->userWith(self::USER_ADMIN, 'Pengelola');
        Role::findOrCreate('Petugas', 'web');

        $this->actingAs($actor)->patch(route('admin.users.update', $actor->id), [
            'name' => $actor->name, 'email' => $actor->email, 'roles' => ['Petugas'],
        ])->assertSessionHasErrors('roles');
        $this->actingAs($actor)->patch(route('admin.users.update', $actor->id), [
            'name' => $actor->name, 'email' => $actor->email, 'roles' => [],
        ])->assertSessionHasErrors('roles');

        $this->assertTrue($actor->fresh()->can('users.update'));
    }

    public function test_own_account_may_swap_roles_when_access_is_kept(): void
    {
        $actor = $this->userWith(self::USER_ADMIN, 'Pengelola');
        Role::findOrCreate('Sekretaris', 'web')->syncPermissions(self::USER_ADMIN);

        $this->actingAs($actor)->patch(route('admin.users.update', $actor->id), [
            'name' => $actor->name, 'email' => $actor->email, 'roles' => ['Sekretaris'],
        ])->assertSessionHasNoErrors();

        $this->assertSame(['Sekretaris'], $actor->fresh()->getRoleNames()->all());
    }

    public function test_own_account_cannot_be_deleted_and_super_admin_accounts_are_protected(): void
    {
        $actor = $this->userWith(self::USER_ADMIN);
        $super = $this->superAdmin();

        $this->actingAs($actor)->delete(route('admin.users.destroy', $actor->id))->assertSessionHasErrors('name');
        $this->actingAs($actor)->delete(route('admin.users.destroy', $super->id))->assertSessionHasErrors('name');
        $this->assertTrue(User::whereKey($actor->id)->exists());
        $this->assertTrue(User::whereKey($super->id)->exists());

        $other = tap(User::factory()->create())->syncRoles([Role::findOrCreate('Petugas', 'web')]);
        $this->actingAs($actor)->delete(route('admin.users.destroy', $other->id))->assertSessionHasNoErrors();
        $this->assertFalse(User::whereKey($other->id)->exists());
    }

    public function test_per_action_guards_separate_viewing_from_deleting(): void
    {
        $viewer = $this->userWith(['residents.view']);
        $resident = \App\Models\Resident::create(['nik' => '3313100101010009', 'name' => 'Sari', 'gender' => 'female', 'address' => 'Jl. Melati 3', 'is_active' => true]);

        $this->actingAs($viewer)->get(route('admin.residents.index'))->assertOk();
        $this->actingAs($viewer)->get(route('admin.residents.create'))->assertForbidden();
        $this->actingAs($viewer)->delete(route('admin.residents.destroy', $resident->id))->assertForbidden();
        $this->assertTrue(\App\Models\Resident::whereKey($resident->id)->exists());
    }

    public function test_sidebar_shows_only_screens_the_viewer_can_open(): void
    {
        $actor = $this->userWith(['residents.view']);

        $response = $this->actingAs($actor)->get(route('admin.residents.index'))->assertOk();
        $response->assertSee('Penduduk');
        $response->assertDontSee(route('admin.roles.index'));
        $response->assertDontSee(route('admin.users.index'));
        $response->assertDontSee('>Sistem<', false);

        $full = $this->userWith([...self::USER_ADMIN, ...self::ROLE_ADMIN], 'Lengkap');
        $this->actingAs($full)->get(route('admin.users.index'))->assertOk()
            ->assertSee(route('admin.users.index'))->assertSee(route('admin.roles.index'))->assertSee('Role &amp; Izin', false);
    }

    public function test_login_lands_on_the_first_screen_the_role_allows(): void
    {
        $user = $this->userWith(self::USER_ADMIN, 'Staf Kepegawaian');
        $user->forceFill(['password' => 'rahasia123', 'is_active' => true])->save();

        $this->post(route('login.attempt'), ['email' => $user->email, 'password' => 'rahasia123'])
            ->assertRedirect(route('admin.users.index'));
    }

    public function test_login_still_prefers_the_dashboard_for_a_full_role(): void
    {
        $user = $this->userWith(['dashboard.view', 'service-requests.view', ...self::USER_ADMIN], 'Lengkap');
        $user->forceFill(['password' => 'rahasia123', 'is_active' => true])->save();

        $this->post(route('login.attempt'), ['email' => $user->email, 'password' => 'rahasia123'])
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_login_is_refused_when_the_role_grants_nothing(): void
    {
        $user = $this->userWith([], 'Tanpa Izin');
        $user->forceFill(['password' => 'rahasia123', 'is_active' => true])->save();

        $this->post(route('login.attempt'), ['email' => $user->email, 'password' => 'rahasia123'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_role_matrix_renders_every_catalogued_permission(): void
    {
        $actor = $this->userWith(self::ROLE_ADMIN);

        $html = $this->actingAs($actor)->get(route('admin.roles.create'))->assertOk()->getContent();
        foreach (PermissionCatalog::all() as $permission) {
            $this->assertStringContainsString('value="'.$permission.'"', $html, "matrix is missing $permission");
        }
        $this->assertStringContainsString('Data penduduk', $html);
        $this->assertStringNotContainsString('guard_name', $html);
    }
}
