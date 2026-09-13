<?php

namespace App\Middleware;

use App\Services\BrandingService;
use App\Services\DeploymentMode;
use App\Services\InstallationService;
use App\Services\SettingsService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ApplyRuntimeSettings
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly InstallationService $installation,
        private readonly BrandingService $branding,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $runtime = $this->installation->runtimeConfiguration();
        if ($runtime !== []) {
            foreach ($runtime as $key => $value) {
                if (is_scalar($value)) {
                    putenv($key.'='.$value);
                    $_ENV[$key] = (string) $value;
                    $_SERVER[$key] = (string) $value;
                }
            }

            config([
                'app.key' => $runtime['APP_KEY'] ?? config('app.key'),
                'database.default' => $runtime['DB_CONNECTION'] ?? config('database.default'),
                'database.connections.mysql.host' => $runtime['DB_HOST'] ?? config('database.connections.mysql.host'),
                'database.connections.mysql.port' => (int) ($runtime['DB_PORT'] ?? config('database.connections.mysql.port')),
                'database.connections.mysql.database' => $runtime['DB_DATABASE'] ?? config('database.connections.mysql.database'),
                'database.connections.mysql.username' => $runtime['DB_USERNAME'] ?? config('database.connections.mysql.username'),
                'database.connections.mysql.password' => $runtime['DB_PASSWORD'] ?? config('database.connections.mysql.password'),
                'database.connections.sqlite.database' => $runtime['DB_DATABASE'] ?? config('database.connections.sqlite.database'),
                'session.path' => $runtime['SESSION_PATH'] ?? config('session.path'),
                'session.cookie' => $runtime['SESSION_COOKIE'] ?? config('session.cookie'),
                'session.secure' => filter_var($runtime['SESSION_SECURE_COOKIE'] ?? config('session.secure'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }

        // The installer needs a session before the database session table exists.
        // Switch only the uninstalled application to local file sessions; once the
        // install is complete, the configured production driver remains active.
        $databaseRuntimeReady = false;
        try {
            $databaseRuntimeReady = Schema::hasTable('sessions') && Schema::hasTable('cache');
        } catch (Throwable) {
            // The installer may not have a database connection yet.
        }

        if (! app()->environment('testing') && (! $this->installation->isInstalled() || ! $databaseRuntimeReady)) {
            config([
                'session.driver' => 'file',
                'session.files' => storage_path('framework/sessions'),
                'cache.default' => 'file',
                'queue.default' => 'sync',
            ]);
        }

        $detectedUrl = $this->installation->detectedUrl($request);
        $ingress = app(DeploymentMode::class)->isIngressRequest($request);
        // Home Assistant rotates ingress URLs. The container is isolated, so a
        // stable root-scoped cookie preserves WYY login across ingress tokens.
        $sessionPath = $ingress ? '/' : ($detectedUrl['base_path'] === '' ? '/' : $detectedUrl['base_path']);
        $sessionCookie = Str::slug((string) config('app.name', 'weinassistent'), '-')
            .($ingress ? '-ha-session' : '-'.substr(sha1($sessionPath), 0, 8).'-session');

        config([
            'app.url' => $detectedUrl['app_url'],
            'session.path' => $sessionPath,
            'session.cookie' => $sessionCookie,
            'session.secure' => $detectedUrl['https'] ? true : (bool) config('session.secure'),
        ]);

        URL::forceRootUrl($detectedUrl['app_url']);

        if ($detectedUrl['https']) {
            URL::forceScheme('https');
        }

        try {
            if (Schema::hasTable('settings')) {
                config([
                    'app.name' => $this->settings->getWithFallback('general', 'app_name', config('app.name'), config('app.name')),
                    'app.subtitle' => $this->settings->get('general', 'app_subtitle', config('app.subtitle', 'Persoenlicher Wein-Kompass fuer Alltag, Laden und Restaurant.')),
                    'app.timezone' => $this->settings->getWithFallback('general', 'timezone', config('app.timezone'), config('app.timezone')),
                    'app.locale' => $this->settings->getWithFallback('general', 'language', config('app.locale'), config('app.locale')),
                    'app.date_format' => $this->settings->get('general', 'date_format', config('app.date_format', 'd.m.Y')),
                    'session.lifetime' => (int) $this->settings->getWithFallback('security', 'session_timeout', config('session.lifetime'), config('session.lifetime')),
                    'auth.guards.web.remember' => max(0, (int) $this->settings->get('security', 'remember_days', 180) * 1440),
                ]);

                date_default_timezone_set((string) config('app.timezone'));
            }
        } catch (Throwable) {
            // Installer and first-run bootstrap must not fail on missing database connectivity.
        }

        try {
            $branding = $this->branding->viewData();
            config([
                'app.name' => $branding['general']['app_name'] ?: config('app.name'),
                'app.subtitle' => $branding['general']['subtitle'] ?: config('app.subtitle'),
                'app.description' => $branding['general']['description'] ?: config('app.description'),
            ]);
            View::share('branding', $branding);
        } catch (Throwable) {
            View::share('branding', [
                'general' => [
                    'app_name' => config('app.name', 'Weinassistent'),
                    'short_name' => 'Wein',
                    'subtitle' => config('app.subtitle', 'Meine persoenliche Weinwelt'),
                    'description' => config('app.description', 'Persoenlicher Wein-Kompass fuer Alltag, Laden und Restaurant.'),
                    'copyright_text' => null,
                    'operator_name' => null,
                    'website_url' => null,
                ],
                'login' => [],
                'colors' => [],
                'icons' => [],
                'pwa' => [],
                'version' => 'default',
                'css_variables' => [],
            ]);
        }

        return $next($request);
    }
}
