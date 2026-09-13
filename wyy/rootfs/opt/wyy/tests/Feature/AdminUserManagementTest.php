<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_user_list(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Active]);

        $this->actingAs($admin)->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Benutzer');
    }

    public function test_user_cannot_open_user_list(): void
    {
        $user = User::factory()->create(['role' => UserRole::User, 'status' => UserStatus::Active]);

        $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_admin_can_create_user(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Active]);

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Max Muster',
            'email' => 'max@example.test',
            'role' => UserRole::User->value,
            'status' => UserStatus::Active->value,
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', ['email' => 'max@example.test', 'role' => UserRole::User->value]);
    }

    public function test_admin_can_create_user_with_forced_password_change(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Active]);

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Erstlogin Pflicht',
            'email' => 'pflicht@example.test',
            'role' => UserRole::User->value,
            'status' => UserStatus::Active->value,
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'must_change_password' => '1',
        ])->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'pflicht@example.test',
            'must_change_password' => 1,
        ]);
    }

    public function test_user_cannot_create_user(): void
    {
        $user = User::factory()->create(['role' => UserRole::User, 'status' => UserStatus::Active]);

        $this->actingAs($user)->post(route('admin.users.store'), [
            'name' => 'Nope',
            'email' => 'nope@example.test',
            'role' => UserRole::User->value,
            'status' => UserStatus::Active->value,
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertForbidden();
    }

    public function test_admin_can_deactivate_and_reactivate_user(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Active]);
        $user = User::factory()->create(['status' => UserStatus::Active]);

        $this->actingAs($admin)->patch(route('admin.users.status', $user), [
            'status' => UserStatus::Inactive->value,
        ])->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'status' => UserStatus::Inactive->value]);

        $this->actingAs($admin)->patch(route('admin.users.status', $user), [
            'status' => UserStatus::Active->value,
        ])->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'status' => UserStatus::Active->value]);
    }

    public function test_deactivated_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'email' => 'inactive@example.test',
            'password' => 'password',
            'status' => UserStatus::Inactive,
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');
    }

    public function test_last_admin_cannot_be_deactivated_or_deleted_or_downgraded(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Active]);

        $this->actingAs($admin)->patch(route('admin.users.status', $admin), [
            'status' => UserStatus::Inactive->value,
        ])->assertSessionHasErrors();

        $this->actingAs($admin)->patch(route('admin.users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => UserRole::User->value,
            'status' => UserStatus::Active->value,
        ])->assertSessionHasErrors();

        $this->actingAs($admin)->delete(route('admin.users.destroy', $admin))
            ->assertSessionHasErrors();
    }

    public function test_admin_can_reset_password_for_user(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Active]);
        $user = User::factory()->create();

        $this->actingAs($admin)->patch(route('admin.users.password.update', $user), [
            'password' => 'new-secret-password',
            'password_confirmation' => 'new-secret-password',
        ])->assertRedirect(route('admin.users.show', $user));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'must_change_password' => 1,
        ]);

        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'new-secret-password',
        ])->assertRedirect(route('dashboard'));

        $this->get(route('dashboard'))
            ->assertRedirect(route('profile.edit'));

        $this->patch(route('profile.password'), [
            'current_password' => 'new-secret-password',
            'password' => 'final-secret-password',
            'password_confirmation' => 'final-secret-password',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'must_change_password' => 0,
        ]);

        $this->get(route('dashboard'))->assertOk();
    }

    public function test_admin_cannot_deactivate_or_downgrade_their_own_account_even_if_other_admins_exist(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Active]);
        User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Active]);

        $this->actingAs($admin)->patch(route('admin.users.status', $admin), [
            'status' => UserStatus::Inactive->value,
        ])->assertSessionHasErrors('role');

        $this->actingAs($admin)->patch(route('admin.users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => UserRole::User->value,
            'status' => UserStatus::Active->value,
        ])->assertSessionHasErrors('role');
    }
}
