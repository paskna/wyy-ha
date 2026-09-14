<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\PwaController;
use App\Middleware\ApplyRuntimeSettings;
use App\Models\User;
use App\Services\InstallationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SharedHostingInstallationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        File::ensureDirectoryExists(storage_path('app'));
    }

    protected function tearDown(): void
    {
        File::delete(storage_path('app/installed.lock'));
        File::delete(base_path('bootstrap/cache/wein-installed.lock'));
        File::delete(base_path('bootstrap/cache/wein-runtime.php'));
        config(['app.deployment' => 'shared']);

        parent::tearDown();
    }

    public function test_login_page_generates_subfolder_safe_manifest_and_service_worker_urls(): void
    {
        $response = $this->withServerVariables([
            'HTTP_HOST' => 'example.test',
            'HTTPS' => 'on',
            'SCRIPT_NAME' => '/visualiz/wyy/index.php',
            'PHP_SELF' => '/visualiz/wyy/index.php',
            'REQUEST_URI' => '/visualiz/wyy/login',
        ])->get('/login');

        $response->assertOk()
            ->assertSee('/visualiz/wyy/manifest.json', false)
            ->assertSee('/visualiz/wyy/sw.js', false)
            ->assertSee('/visualiz/wyy/apple-touch-icon.png', false);
    }

    public function test_service_worker_uses_subfolder_safe_scope_and_cache_names(): void
    {
        $response = $this->withServerVariables([
            'HTTP_HOST' => 'example.test',
            'HTTPS' => 'on',
            'SCRIPT_NAME' => '/apps/wine/private/index.php',
            'PHP_SELF' => '/apps/wine/private/index.php',
            'REQUEST_URI' => '/apps/wine/private/sw.js',
        ])->get('/sw.js');

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/javascript')
            ->assertSee('/apps/wine/private/', false)
            ->assertSee('CACHE_NAME', false);
    }

    public function test_login_redirect_keeps_subfolder_context(): void
    {
        File::put(storage_path('app/installed.lock'), '{}');

        User::factory()->create([
            'email' => 'subdir@example.test',
            'password' => 'password',
            'role' => UserRole::User,
            'status' => UserStatus::Active,
        ]);

        $response = $this->withServerVariables([
            'HTTP_HOST' => 'example.test',
            'HTTPS' => 'on',
            'SCRIPT_NAME' => '/visualiz/wyy/index.php',
            'PHP_SELF' => '/visualiz/wyy/index.php',
            'REQUEST_URI' => '/visualiz/wyy/login',
        ])->post('/login', [
            'email' => 'subdir@example.test',
            'password' => 'password',
        ]);

        $response->assertRedirectContains('/visualiz/wyy/start');
    }

    public function test_dynamic_manifest_uses_current_subfolder_as_scope_and_start_url(): void
    {
        File::put(storage_path('app/installed.lock'), '{}');
        User::factory()->create(['status' => UserStatus::Active]);

        $response = $this->withServerVariables([
            'HTTP_HOST' => 'example.test',
            'HTTPS' => 'on',
            'SCRIPT_NAME' => '/wyy/index.php',
            'PHP_SELF' => '/wyy/index.php',
            'REQUEST_URI' => '/wyy/manifest.json',
        ])->get('/manifest.json');

        $response->assertOk()
            ->assertJsonPath('scope', '/wyy/')
            ->assertJsonPath('start_url', '/wyy/');
    }

    public function test_pwa_has_no_competing_static_manifest_or_service_worker(): void
    {
        $this->assertFalse(File::exists(public_path('manifest.json')));
        $this->assertFalse(File::exists(public_path('sw.js')));

        $this->get(route('pwa.manifest'))->assertOk()->assertHeader('Content-Type', 'application/manifest+json');
        $this->get(route('pwa.worker'))->assertOk()->assertHeader('Content-Type', 'application/javascript');
    }

    public function test_database_with_an_admin_is_not_marked_installed_without_lock_file(): void
    {
        File::delete(storage_path('app/installed.lock'));
        User::factory()->create(['status' => UserStatus::Active]);

        $this->assertFalse(app(InstallationService::class)->isInstalled());
    }

    public function test_testing_environment_keeps_its_isolated_session_drivers(): void
    {
        File::delete(storage_path('app/installed.lock'));

        $this->get(route('install.system'))->assertOk();

        $this->assertSame('array', config('session.driver'));
        $this->assertSame('array', config('cache.default'));
        $this->assertSame('sync', config('queue.default'));
    }

    public function test_stale_lock_does_not_block_installation_when_database_has_no_users(): void
    {
        File::put(storage_path('app/installed.lock'), '{}');

        $this->assertTrue(app(InstallationService::class)->isInstalled());
    }

    public function test_installation_configuration_can_boot_from_lock_fallback(): void
    {
        File::put(storage_path('app/installed.lock'), json_encode([
            'environment' => [
                'DB_CONNECTION' => 'mysql',
                'DB_DATABASE' => 'shared_hosting_app',
                'DB_USERNAME' => 'shared_hosting_user',
                'DB_PASSWORD' => 'secret',
            ],
        ], JSON_THROW_ON_ERROR));

        $configuration = app(InstallationService::class)->runtimeConfiguration();

        $this->assertSame('mysql', $configuration['DB_CONNECTION']);
        $this->assertSame('shared_hosting_app', $configuration['DB_DATABASE']);
    }

    public function test_bootstrap_cache_marker_is_a_persistent_installation_fallback(): void
    {
        File::delete(storage_path('app/installed.lock'));
        File::put(base_path('bootstrap/cache/wein-installed.lock'), json_encode([
            'environment' => [
                'DB_CONNECTION' => 'mysql',
                'DB_DATABASE' => 'shared_hosting_app',
            ],
        ], JSON_THROW_ON_ERROR));

        $this->assertTrue(app(InstallationService::class)->isInstalled());
        $this->assertSame('shared_hosting_app', app(InstallationService::class)->runtimeConfiguration()['DB_DATABASE']);
    }

    public function test_installation_marker_routes_base_url_to_login_and_blocks_installer_flow(): void
    {
        File::put(storage_path('app/installed.lock'), '{}');

        $this->get('/')->assertRedirect(route('login'));
        $this->get(route('install.welcome'))
            ->assertOk()
            ->assertSee('Die Anwendung ist bereits installiert.');
    }

    public function test_bootstrap_cache_marker_keeps_the_installer_blocked_on_a_new_request(): void
    {
        File::delete(storage_path('app/installed.lock'));
        File::put(base_path('bootstrap/cache/wein-installed.lock'), '{}');

        $this->get(route('install.welcome'))
            ->assertOk()
            ->assertSee('Die Anwendung ist bereits installiert.');

        $this->withServerVariables([
            'HTTP_HOST' => 'example.test',
            'HTTPS' => 'on',
            'SCRIPT_NAME' => '/visualiz/wyy/index.php',
            'PHP_SELF' => '/visualiz/wyy/index.php',
            'REQUEST_URI' => '/visualiz/wyy/',
        ])->get('/')
            ->assertRedirectContains('/visualiz/wyy/login');
    }

    public function test_home_assistant_first_run_redirects_login_to_wyy_onboarding(): void
    {
        config(['app.deployment' => 'homeassistant']);

        $this->get(route('login'))
            ->assertRedirect(route('ha.setup.create'));

        $this->get(route('ha.setup.create'))
            ->assertOk()
            ->assertSee('Die technische Einrichtung ist abgeschlossen');
    }

    public function test_home_assistant_onboarding_creates_the_first_active_administrator(): void
    {
        config(['app.deployment' => 'homeassistant']);

        $this->post(route('ha.setup.store'), [
            'name' => 'WYY Admin',
            'email' => 'admin@wyy.test',
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
            'app_name' => 'WYY',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseHas('users', [
            'email' => 'admin@wyy.test',
            'role' => UserRole::Admin->value,
            'status' => UserStatus::Active->value,
        ]);
    }

    public function test_home_assistant_ingress_uses_ingress_path_and_disables_service_worker(): void
    {
        config(['app.deployment' => 'homeassistant']);
        $request = Request::create('/sw.js', 'GET', server: [
            'HTTP_HOST' => '192.168.2.220:8123',
            'SERVER_PORT' => 8099,
            'HTTP_X_FORWARDED_PROTO' => 'http',
            'HTTP_X_INGRESS_PATH' => '/api/hassio_ingress/token-123',
        ]);

        $detected = app(InstallationService::class)->detectedUrl($request);

        $this->assertSame('/api/hassio_ingress/token-123', $detected['base_path']);
        $this->assertSame(8123, $detected['port']);
        $this->assertSame('http://192.168.2.220:8123/api/hassio_ingress/token-123', $detected['app_url']);

        app(ApplyRuntimeSettings::class)->handle($request, fn () => response('ok'));
        $this->assertSame('/', config('session.path'));
        $this->assertSame('laravel-ha-session', config('session.cookie'));

        $response = app(PwaController::class)->worker($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('unregister', $response->getContent());
    }

    public function test_home_assistant_ingress_prefers_the_forwarded_external_origin(): void
    {
        config(['app.deployment' => 'homeassistant']);
        $request = Request::create('/setup', 'GET', server: [
            'HTTP_HOST' => '172.30.32.2:8099',
            'SERVER_PORT' => 8099,
            'HTTP_X_FORWARDED_HOST' => 'ha.example.test',
            'HTTP_X_FORWARDED_PORT' => '443',
            'HTTP_X_FORWARDED_PROTO' => 'https, http',
            'HTTP_X_INGRESS_PATH' => '/api/hassio_ingress/token-456',
        ]);

        $detected = app(InstallationService::class)->detectedUrl($request);

        $this->assertSame('https', $detected['scheme']);
        $this->assertNull($detected['port']);
        $this->assertSame('https://ha.example.test/api/hassio_ingress/token-456', $detected['app_url']);
    }

    public function test_home_assistant_ingress_redirects_and_assets_keep_the_external_origin(): void
    {
        config(['app.deployment' => 'homeassistant']);
        $server = [
            'HTTP_HOST' => '192.168.2.220:8123',
            'SERVER_PORT' => 8099,
            'HTTP_X_FORWARDED_PROTO' => 'http',
            'HTTP_X_INGRESS_PATH' => '/api/hassio_ingress/token-browser',
        ];

        $this->withServerVariables($server)
            ->get('http://192.168.2.220:8123/login')
            ->assertRedirect('http://192.168.2.220:8123/api/hassio_ingress/token-browser/setup');

        $this->withServerVariables($server)
            ->get('http://192.168.2.220:8123/setup')
            ->assertOk()
            ->assertSee('http://192.168.2.220:8123/api/hassio_ingress/token-browser/build/assets/app-D7vHc2rM.js', false)
            ->assertDontSee('meta name="app-sw-url"', false);

        $compiledJavascript = File::get(public_path('build/assets/app-D7vHc2rM.js'));

        $this->assertStringContainsString('meta[name="app-sw-url"]', $compiledJavascript);
        $this->assertStringNotContainsString('register(`/sw.js`)', $compiledJavascript);
    }
}
