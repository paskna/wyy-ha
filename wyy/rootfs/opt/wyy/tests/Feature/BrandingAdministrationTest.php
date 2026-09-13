<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\BrandingAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandingAdministrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_admin_can_open_branding_but_user_cannot(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Active]);
        $user = User::factory()->create(['role' => UserRole::User, 'status' => UserStatus::Active]);

        $this->actingAs($admin)->get(route('admin.branding.edit', 'general'))
            ->assertOk()
            ->assertSee('Erscheinungsbild');

        $this->actingAs($user)->get(route('admin.branding.edit', 'general'))
            ->assertForbidden();
    }

    public function test_app_name_and_login_title_can_be_changed_and_are_visible(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Active]);

        $this->actingAs($admin)->put(route('admin.branding.update', 'general'), [
            'app_name' => 'WYY',
            'short_name' => 'WYY',
            'subtitle' => 'Meine persoenliche Weinwelt',
            'description' => 'Branding Test',
            'copyright_text' => '© 2026 WYY',
            'operator_name' => 'WYY AG',
            'website_url' => 'https://example.test',
        ])->assertRedirect();

        $this->actingAs($admin)->put(route('admin.branding.update', 'login'), [
            'background_color' => '#120d0d',
            'overlay_color' => '#120d0d',
            'overlay_strength' => 48,
            'box_background_color' => '#201615',
            'box_opacity' => 84,
            'box_width' => 'standard',
            'layout' => 'centered',
            'background_fit' => 'cover',
            'background_position' => 'center',
            'logo_width' => 180,
            'logo_alignment' => 'center',
            'title' => 'Willkommen bei WYY',
            'subtitle' => 'Deine persoenliche Weinwelt',
            'welcome_text' => 'Testtext',
            'button_text' => 'Einloggen',
            'forgot_password_text' => 'Passwort vergessen',
            'footer_text' => 'Footer',
            'box_radius' => 'rounded',
            'box_shadow' => 'soft',
            'overlay_enabled' => '1',
        ])->assertRedirect();

        auth()->logout();

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Willkommen bei WYY')
            ->assertSee('WYY')
            ->assertSee('Einloggen');
    }

    public function test_logo_upload_and_invalid_files_are_handled(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Active]);

        $this->actingAs($admin)->put(route('admin.branding.update', 'logos'), [
            'logo_display' => 'main_logo',
            'main_logo' => UploadedFile::fake()->image('main-logo.png', 500, 200),
        ])->assertRedirect();

        $asset = BrandingAsset::query()->where('type', 'main_logo')->first();
        $this->assertNotNull($asset);
        Storage::disk('local')->assertExists($asset->storage_path);

        $this->get(route('branding.asset', ['slot' => 'main_logo']))
            ->assertOk();

        $this->actingAs($admin)->put(route('admin.branding.update', 'logos'), [
            'logo_display' => 'main_logo',
            'main_logo' => UploadedFile::fake()->create('logo.txt', 10, 'text/plain'),
        ])->assertSessionHasErrors('main_logo');

        $this->actingAs($admin)->put(route('admin.branding.update', 'logos'), [
            'logo_display' => 'main_logo',
            'main_logo' => UploadedFile::fake()->create('huge.png', 6000, 'image/png'),
        ])->assertSessionHasErrors('main_logo');
    }

    public function test_invalid_color_is_rejected_and_xss_text_is_escaped(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Active]);

        $this->actingAs($admin)->put(route('admin.branding.update', 'colors'), [
            'primary' => 'url(javascript:alert(1))',
            'secondary' => '#6f2e2a',
            'accent' => '#f0c985',
            'background' => '#120d0d',
            'surface' => '#201615',
            'text' => '#f7f0e6',
            'text_muted' => '#cabfb8',
            'navigation' => '#110c0c',
            'navigation_active' => '#f0c985',
            'button_primary' => '#d1a15f',
            'button_text' => '#221614',
            'login_box_background' => '#201615',
        ])->assertSessionHasErrors('primary');

        $this->actingAs($admin)->put(route('admin.branding.update', 'login'), [
            'background_color' => '#120d0d',
            'overlay_color' => '#120d0d',
            'overlay_strength' => 48,
            'box_background_color' => '#201615',
            'box_opacity' => 84,
            'box_width' => 'standard',
            'layout' => 'centered',
            'background_fit' => 'cover',
            'background_position' => 'center',
            'logo_width' => 180,
            'logo_alignment' => 'center',
            'title' => '<script>alert(1)</script>',
            'subtitle' => 'Safe',
            'welcome_text' => 'Safe',
            'button_text' => 'Anmelden',
            'forgot_password_text' => 'Passwort vergessen',
            'footer_text' => 'Footer',
            'box_radius' => 'rounded',
            'box_shadow' => 'soft',
            'overlay_enabled' => '1',
        ])->assertRedirect();

        auth()->logout();

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_manifest_uses_current_branding_and_base_path_and_fallback_asset_survives_missing_file(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Active]);

        $this->actingAs($admin)->put(route('admin.branding.update', 'pwa'), [
            'pwa_name' => 'WYY App',
            'pwa_short_name' => 'WYY',
            'pwa_description' => 'Meine Wein-App',
            'theme_color' => '#123456',
            'background_color' => '#654321',
            'app_icon' => UploadedFile::fake()->image('icon.png', 1024, 1024),
        ])->assertRedirect();

        $favicon = BrandingAsset::query()->where('type', 'favicon')->firstOrFail();
        Storage::disk('local')->delete($favicon->storage_path);

        $this->get(route('branding.asset', ['slot' => 'favicon']))
            ->assertOk();

        $response = $this->withServerVariables([
            'HTTP_HOST' => 'example.test',
            'HTTPS' => 'on',
            'SCRIPT_NAME' => '/visualiz/wyy/index.php',
            'PHP_SELF' => '/visualiz/wyy/index.php',
            'REQUEST_URI' => '/visualiz/wyy/manifest.json',
        ])->get('/manifest.json');

        $response->assertOk()
            ->assertJsonPath('name', 'WYY App')
            ->assertJsonPath('theme_color', '#123456')
            ->assertJsonPath('scope', '/visualiz/wyy/');
    }
}
