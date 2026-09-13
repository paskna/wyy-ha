<?php

namespace Tests\Feature;

use App\Enums\Preference;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Models\UserWine;
use App\Models\WineVintage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RegressionFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_core_admin_and_user_flow_remains_stable(): void
    {
        Storage::fake('local');
        Http::fake([
            'https://api.openai.com/v1/models/*' => Http::response(['id' => 'gpt-4.1-mini'], 200),
        ]);

        $admin = User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => 'password',
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
        ]);

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'User Eins',
            'email' => 'user1@example.test',
            'role' => UserRole::User->value,
            'status' => UserStatus::Active->value,
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertRedirect(route('admin.users.index'));

        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->post(route('login.store'), [
            'email' => 'user1@example.test',
            'password' => 'secret-password',
        ])->assertRedirect(route('dashboard'));

        $scanRedirect = $this->post(route('scans.store'), [
            'image' => UploadedFile::fake()->image('wine-2021.jpg'),
        ])->assertRedirect()->baseResponse->headers->get('Location');

        $scanId = (int) basename($scanRedirect);

        $this->post(route('scans.save', $scanId), [
            'producer' => 'Chateau Test',
            'wine_name' => 'Reserve',
            'vintage' => '2021',
            'country' => 'Frankreich',
            'region' => 'Bordeaux',
            'wine_type' => 'Rotwein',
            'preference' => Preference::Top->value,
            'personal_note' => 'Mein Favorit',
            'quantity' => 4,
        ])->assertRedirect();

        $vintage = WineVintage::query()->firstOrFail();

        $this->get(route('top-wines.index'))
            ->assertOk()
            ->assertSee('Chateau Test');

        $this->get(route('wines.show', $vintage))
            ->assertOk()
            ->assertSee('Mein Favorit');

        $this->assertDatabaseHas('user_wines', [
            'user_id' => User::query()->where('email', 'user1@example.test')->value('id'),
            'wine_vintage_id' => $vintage->id,
            'quantity' => 4,
        ]);

        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'User Zwei',
            'email' => 'user2@example.test',
            'role' => UserRole::User->value,
            'status' => UserStatus::Active->value,
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertRedirect(route('admin.users.index'));

        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->post(route('login.store'), [
            'email' => 'user2@example.test',
            'password' => 'secret-password',
        ])->assertRedirect(route('dashboard'));

        $scanRedirect2 = $this->post(route('scans.store'), [
            'image' => UploadedFile::fake()->image('wine-2021-copy.jpg'),
        ])->assertRedirect()->baseResponse->headers->get('Location');

        $scanId2 = (int) basename($scanRedirect2);

        $this->post(route('scans.save', $scanId2), [
            'producer' => 'Chateau Test',
            'wine_name' => 'Reserve',
            'vintage' => '2021',
            'country' => 'Frankreich',
            'region' => 'Bordeaux',
            'wine_type' => 'Rotwein',
            'preference' => Preference::Unsuitable->value,
            'personal_note' => '',
            'quantity' => null,
        ])->assertRedirect();

        $this->get(route('wines.show', $vintage))
            ->assertOk()
            ->assertDontSee('Mein Favorit')
            ->assertSee('Unpassend');

        $this->assertSame(2, UserWine::query()->where('wine_vintage_id', $vintage->id)->count());

        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->actingAs($admin)->get(route('admin.integrations.index'))
            ->assertOk()
            ->assertSee('API & Integrationen');

        $this->actingAs($admin)->put(route('admin.integrations.update', 'openai'), [
            'enabled' => '1',
            'api_key' => 'sk-proj-123456789ABCDE',
            'image_model' => 'gpt-4.1-mini',
            'text_model' => 'gpt-4.1-mini',
            'structuring_model' => 'gpt-4.1-mini',
            'timeout' => 10,
            'retries' => 1,
            'priority' => 1,
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('admin.integrations.test', 'openai'))
            ->assertRedirect()
            ->assertSessionHas('status', 'OpenAI API erfolgreich verbunden.');

        $user2 = User::query()->where('email', 'user2@example.test')->firstOrFail();

        $this->actingAs($admin)->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('User Zwei');

        $this->actingAs($admin)->patch(route('admin.users.status', $user2), [
            'status' => UserStatus::Inactive->value,
        ])->assertRedirect();

        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->post(route('login.store'), [
            'email' => 'user2@example.test',
            'password' => 'secret-password',
        ])->assertSessionHasErrors('email');
    }
}
