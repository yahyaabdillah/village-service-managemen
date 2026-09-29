<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleAccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['manage users', 'manage roles', 'manage residents', 'manage service requests'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
    }

    private function userWith(array $permissions, string $roleName = 'Penguji'): User
    {
        $role = Role::findOrCreate($roleName, 'web');
        $role->syncPermissions($permissions);

        return tap(User::factory()->create())->syncRoles([$role]);
    }

    public function test_role_form_saves_selected_permissions(): void
    {
        $actor = $this->userWith(['manage roles']);

        $this->actingAs($actor)->post(route('admin.roles.store'), [
            'name' => 'Operator Surat',
            'permissions' => ['manage residents', 'manage users'],
        ])->assertRedirect(route('admin.roles.index'));

        $saved = Role::where('name', 'Operator Surat')->firstOrFail();
        $this->assertEqualsCanonicalizing(
            ['manage residents', 'manage users'],
            $saved->permissions->pluck('name')->all()
        );
    }

    public function test_existing_role_can_be_assigned_with_only_manage_users(): void
    {
        $actor = $this->userWith(['manage users']);
        Role::findOrCreate('Petugas', 'web');

        $this->actingAs($actor)->post(route('admin.users.store'), [
            'name' => 'Budi',
            'email' => 'budi@desa.test',
            'password' => 'rahasia123',
            'roles' => ['Petugas'],
        ])->assertSessionHasNoErrors();

        $this->assertTrue(User::where('email', 'budi@desa.test')->firstOrFail()->hasRole('Petugas'));
    }

    public function test_naming_a_new_role_is_refused_without_manage_roles(): void
    {
        $actor = $this->userWith(['manage users']);

        $this->actingAs($actor)->post(route('admin.users.store'), [
            'name' => 'Budi',
            'email' => 'budi@desa.test',
            'password' => 'rahasia123',
            'roles' => ['Role Karangan'],
        ])->assertSessionHasErrors('roles.0');

        $this->assertFalse(Role::where('name', 'Role Karangan')->exists());
        $this->assertFalse(User::where('email', 'budi@desa.test')->exists());
    }

    public function test_naming_a_new_role_creates_it_with_manage_roles(): void
    {
        $actor = $this->userWith(['manage users', 'manage roles']);

        $this->actingAs($actor)->post(route('admin.users.store'), [
            'name' => 'Budi',
            'email' => 'budi@desa.test',
            'password' => 'rahasia123',
            'roles' => ['Bendahara'],
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Role::where('name', 'Bendahara')->exists());
        $this->assertTrue(User::where('email', 'budi@desa.test')->firstOrFail()->hasRole('Bendahara'));
    }

    public function test_own_role_cannot_lose_manage_roles(): void
    {
        $actor = $this->userWith(['manage roles'], 'Pengelola');
        $role = Role::where('name', 'Pengelola')->firstOrFail();

        $this->actingAs($actor)->patch(route('admin.roles.update', $role->id), [
            'name' => 'Pengelola',
            'permissions' => ['manage residents'],
        ])->assertSessionHasErrors('permissions');

        $this->assertTrue($actor->fresh()->can('manage roles'));
    }

    public function test_own_role_may_lose_manage_roles_when_another_role_still_grants_it(): void
    {
        $actor = $this->userWith(['manage roles'], 'Pengelola');
        $backup = Role::findOrCreate('Cadangan', 'web');
        $backup->syncPermissions(['manage roles']);
        $actor->assignRole($backup);

        $role = Role::where('name', 'Pengelola')->firstOrFail();

        $this->actingAs($actor->fresh())->patch(route('admin.roles.update', $role->id), [
            'name' => 'Pengelola',
            'permissions' => ['manage residents'],
        ])->assertSessionHasNoErrors();

        $this->assertTrue($actor->fresh()->can('manage roles'));
    }

    public function test_own_role_cannot_be_deleted(): void
    {
        $actor = $this->userWith(['manage roles'], 'Pengelola');
        $role = Role::where('name', 'Pengelola')->firstOrFail();

        $this->actingAs($actor)->delete(route('admin.roles.destroy', $role->id))
            ->assertSessionHasErrors('name');

        $this->assertTrue(Role::whereKey($role->id)->exists());
    }

    public function test_own_user_role_cannot_be_cleared(): void
    {
        $actor = $this->userWith(['manage users'], 'Pengelola');

        $this->actingAs($actor)->patch(route('admin.users.update', $actor->id), [
            'name' => $actor->name,
            'email' => $actor->email,
            'roles' => [],
        ])->assertSessionHasErrors('roles');

        $this->assertTrue($actor->fresh()->hasRole('Pengelola'));
    }

    public function test_another_users_role_can_still_be_cleared(): void
    {
        $actor = $this->userWith(['manage users'], 'Pengelola');
        $other = tap(User::factory()->create())->syncRoles([Role::findOrCreate('Petugas', 'web')]);

        $this->actingAs($actor)->patch(route('admin.users.update', $other->id), [
            'name' => $other->name,
            'email' => $other->email,
            'roles' => [],
        ])->assertSessionHasNoErrors();

        $this->assertCount(0, $other->fresh()->roles);
    }

    public function test_several_roles_can_be_assigned_at_once(): void
    {
        $actor = $this->userWith(['manage users']);
        Role::findOrCreate('Petugas', 'web');
        Role::findOrCreate('Bendahara', 'web');

        $this->actingAs($actor)->post(route('admin.users.store'), [
            'name' => 'Budi',
            'email' => 'budi@desa.test',
            'password' => 'rahasia123',
            'roles' => ['Petugas', 'Bendahara'],
        ])->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing(
            ['Petugas', 'Bendahara'],
            User::where('email', 'budi@desa.test')->firstOrFail()->getRoleNames()->all()
        );
    }

    public function test_existing_and_new_roles_can_be_mixed(): void
    {
        $actor = $this->userWith(['manage users', 'manage roles']);
        Role::findOrCreate('Petugas', 'web');

        $this->actingAs($actor)->post(route('admin.users.store'), [
            'name' => 'Budi',
            'email' => 'budi@desa.test',
            'password' => 'rahasia123',
            'roles' => ['Petugas', 'Kasi Pemerintahan'],
        ])->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing(
            ['Petugas', 'Kasi Pemerintahan'],
            User::where('email', 'budi@desa.test')->firstOrFail()->getRoleNames()->all()
        );
    }

    public function test_duplicate_role_names_are_collapsed(): void
    {
        $actor = $this->userWith(['manage users']);
        Role::findOrCreate('Petugas', 'web');

        $this->actingAs($actor)->post(route('admin.users.store'), [
            'name' => 'Budi',
            'email' => 'budi@desa.test',
            'password' => 'rahasia123',
            'roles' => ['Petugas', 'Petugas', '  '],
        ])->assertSessionHasNoErrors();

        $this->assertSame(['Petugas'], User::where('email', 'budi@desa.test')->firstOrFail()->getRoleNames()->all());
    }

    public function test_own_account_cannot_be_left_without_manage_users(): void
    {
        $actor = $this->userWith(['manage users'], 'Pengelola');
        Role::findOrCreate('Petugas', 'web');  // grants nothing

        $this->actingAs($actor)->patch(route('admin.users.update', $actor->id), [
            'name' => $actor->name,
            'email' => $actor->email,
            'roles' => ['Petugas'],
        ])->assertSessionHasErrors('roles');

        $this->assertTrue($actor->fresh()->can('manage users'));
    }

    public function test_own_account_may_swap_roles_when_access_is_kept(): void
    {
        $actor = $this->userWith(['manage users'], 'Pengelola');
        $other = Role::findOrCreate('Sekretaris', 'web');
        $other->syncPermissions(['manage users']);

        $this->actingAs($actor)->patch(route('admin.users.update', $actor->id), [
            'name' => $actor->name,
            'email' => $actor->email,
            'roles' => ['Sekretaris'],
        ])->assertSessionHasNoErrors();

        $this->assertSame(['Sekretaris'], $actor->fresh()->getRoleNames()->all());
    }

    public function test_sidebar_links_to_the_role_screen_for_role_managers(): void
    {
        $actor = $this->userWith(['manage roles']);

        $this->actingAs($actor)->get(route('admin.roles.index'))
            ->assertOk()
            ->assertSee(route('admin.roles.index'))
            ->assertSee('Role &amp; Izin', false);
    }

    public function test_sidebar_hides_screens_the_viewer_cannot_open(): void
    {
        $actor = $this->userWith(['manage residents']);

        $response = $this->actingAs($actor)->get(route('admin.residents.index'))->assertOk();

        $response->assertSee('Penduduk');
        $response->assertDontSee(route('admin.roles.index'));
        $response->assertDontSee(route('admin.users.index'));
        // The heading must go with its only group member.
        $response->assertDontSee('>Sistem<', false);
    }

    public function test_sidebar_shows_user_and_role_links_separately(): void
    {
        $actor = $this->userWith(['manage users', 'manage roles']);

        $this->actingAs($actor)->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee(route('admin.users.index'))
            ->assertSee(route('admin.roles.index'));
    }

    public function test_login_lands_on_the_first_screen_the_role_allows(): void
    {
        $user = $this->userWith(['manage users'], 'Staf Kepegawaian');
        $user->forceFill(['password' => 'rahasia123', 'is_active' => true])->save();

        $this->post(route('login.attempt'), ['email' => $user->email, 'password' => 'rahasia123'])
            ->assertRedirect(route('admin.users.index'));
    }

    public function test_login_still_prefers_the_dashboard_for_a_full_role(): void
    {
        $user = $this->userWith(['manage service requests', 'manage users'], 'Lengkap');
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
}
