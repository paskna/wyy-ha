<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Producer;
use App\Models\User;
use App\Models\Wine;
use App\Models\WineVintage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_integrations_but_user_cannot(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Active]);
        $user = User::factory()->create(['role' => UserRole::User, 'status' => UserStatus::Active]);

        $this->actingAs($admin)->get(route('admin.integrations.index'))
            ->assertOk()
            ->assertSee('API & Integrationen');

        $this->actingAs($user)->get(route('admin.integrations.index'))->assertForbidden();
    }

    public function test_admin_can_store_encrypted_openai_key_and_page_does_not_expose_plaintext(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Active]);
        $secret = 'sk-proj-123456789ABCDE';

        $this->actingAs($admin)->put(route('admin.integrations.update', 'openai'), [
            'enabled' => '1',
            'api_key' => $secret,
            'image_model' => 'gpt-4.1-mini',
            'text_model' => 'gpt-4.1-mini',
            'structuring_model' => 'gpt-4.1-mini',
            'timeout' => 10,
            'retries' => 1,
            'priority' => 1,
        ])->assertRedirect();

        $row = \DB::table('settings')->where('group', 'integration.openai')->where('key', 'api_key')->first();

        $this->assertNotNull($row);
        $this->assertNotSame($secret, $row->value);

        $this->actingAs($admin)->get(route('admin.integrations.edit', 'openai'))
            ->assertOk()
            ->assertDontSee($secret);
    }

    public function test_admin_can_run_mocked_openai_connection_test(): void
    {
        Http::fake([
            'https://api.openai.com/v1/models/*' => Http::response(['id' => 'gpt-4.1-mini'], 200),
        ]);

        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Active]);

        $this->actingAs($admin)->put(route('admin.integrations.update', 'openai'), [
            'enabled' => '1',
            'api_key' => 'sk-proj-123456789ABCDE',
            'image_model' => 'gpt-4.1-mini',
            'text_model' => 'gpt-4.1-mini',
            'structuring_model' => 'gpt-4.1-mini',
            'timeout' => 10,
            'retries' => 1,
            'priority' => 1,
        ]);

        $this->actingAs($admin)->post(route('admin.integrations.test', 'openai'))
            ->assertRedirect()
            ->assertSessionHas('status', 'OpenAI API erfolgreich verbunden.');

        $this->assertDatabaseHas('api_activity_logs', [
            'provider' => 'openai',
            'status' => 'success',
        ]);
    }

    public function test_admin_can_update_settings_but_user_cannot(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Active]);
        $user = User::factory()->create(['role' => UserRole::User, 'status' => UserStatus::Active]);

        $this->actingAs($admin)->put(route('admin.settings.update', 'general'), [
            'app_name' => 'Wein-App Betrieb',
            'app_subtitle' => 'Admin Test',
            'language' => 'de',
            'timezone' => 'Europe/Zurich',
            'date_format' => 'd.m.Y',
            'per_page' => 15,
            'default_wine_view' => 'cards',
        ])->assertRedirect();

        $this->assertDatabaseHas('settings', [
            'group' => 'general',
            'key' => 'app_name',
        ]);

        $this->actingAs($user)->put(route('admin.settings.update', 'general'), [
            'app_name' => 'Nope',
            'language' => 'de',
            'timezone' => 'Europe/Zurich',
            'date_format' => 'd.m.Y',
            'per_page' => 15,
            'default_wine_view' => 'cards',
        ])->assertForbidden();
    }

    public function test_general_settings_are_applied_to_layout_and_collection_pagination(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Active]);

        $this->actingAs($admin)->put(route('admin.settings.update', 'general'), [
            'app_name' => 'Wein-App Betrieb',
            'app_subtitle' => 'Betriebsmodus aktiv',
            'language' => 'de',
            'timezone' => 'Europe/Zurich',
            'date_format' => 'd.m.Y',
            'per_page' => 5,
            'default_wine_view' => 'list',
        ])->assertRedirect();

        $producer = Producer::query()->create(['name' => 'Testhaus', 'normalized_name' => 'testhaus']);
        $wine = Wine::query()->create([
            'producer_id' => $producer->id,
            'name' => 'Linie',
            'normalized_name' => 'linie',
        ]);

        for ($i = 0; $i < 6; $i++) {
            $vintage = WineVintage::query()->create([
                'wine_id' => $wine->id,
                'vintage' => (string) (2020 + $i),
            ]);

            $admin->userWines()->create([
                'wine_vintage_id' => $vintage->id,
                'preference' => 'unrated',
            ]);
        }

        $response = $this->actingAs($admin)->get(route('collection.index'));

        $response->assertOk()
            ->assertSee('Betriebsmodus aktiv')
            ->assertSee('Liste')
            ->assertSee('?page=2', false);
    }

    public function test_invalid_matching_weight_sum_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Active]);

        $this->actingAs($admin)->put(route('admin.settings.update', 'matching'), [
            'exact_threshold' => 0.88,
            'candidate_threshold' => 0.72,
            'producer_weight' => 0.5,
            'wine_name_weight' => 0.3,
            'vintage_weight' => 0.2,
            'region_weight' => 0.1,
            'visual_weight' => 0.1,
            'other_weight' => 0.1,
        ])->assertSessionHasErrors('producer_weight');
    }

    public function test_admin_can_send_mail_test_with_mocked_mailer(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Active]);

        $this->actingAs($admin)->put(route('admin.settings.update', 'mail'), [
            'host' => 'smtp.example.test',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'user',
            'password' => 'secret-mail-password',
            'from_name' => 'Wein-App',
            'from_address' => 'wein@example.test',
            'test_recipient' => 'ops@example.test',
        ]);

        $this->actingAs($admin)->post(route('admin.settings.mail.test'), [
            'recipient' => 'ops@example.test',
        ])->assertRedirect()->assertSessionHas('status', 'Testmail erfolgreich versendet.');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'mail_test_sent',
        ]);
    }

    public function test_image_settings_hide_nonfunctional_fields_and_show_fixed_exif_rule(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Active]);

        $this->actingAs($admin)->get(route('admin.settings.edit', 'image'))
            ->assertOk()
            ->assertDontSee('Thumbnail Width')
            ->assertDontSee('Keep Original')
            ->assertSee('EXIF-Daten werden aus Sicherheits- und Datenschutzgruenden immer entfernt');
    }

    public function test_system_diagnosis_flags_non_production_debug_and_missing_secure_cookie(): void
    {
        Config::set('app.env', 'local');
        Config::set('app.debug', true);
        Config::set('app.url', 'http://wein-app.test');
        Config::set('session.secure', false);
        Config::set('session.http_only', true);
        Config::set('session.same_site', 'lax');

        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Active]);

        $this->actingAs($admin)->get(route('admin.system.show'))
            ->assertOk()
            ->assertSee('APP_ENV ist aktuell auf local gesetzt')
            ->assertSee('SESSION_SECURE_COOKIE ist nicht aktiv')
            ->assertSee('HEIC/HEIF');
    }
}
