<?php

namespace App\Services;

use App\Models\ApiActivityLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Throwable;

class SystemDiagnosisService
{
    public function __construct(
        private readonly IntegrationManager $integrations,
        private readonly SettingsService $settings,
        private readonly BrandingService $branding,
        private readonly ScanService $scanService,
    ) {}

    public function checks(): Collection
    {
        return collect([
            $this->checkApplicationMode(),
            $this->checkInstallationContext(),
            $this->checkPhp(),
            $this->checkDatabase(),
            $this->checkStorage(),
            $this->checkCache(),
            $this->checkAssets(),
            $this->checkPwa(),
            $this->checkBranding(),
            $this->checkSessionSecurity(),
            $this->checkQueue(),
            $this->checkIntegrations(),
            $this->checkMail(),
            $this->checkMigrations(),
            $this->checkExtensions(),
            $this->checkHeic(),
        ]);
    }

    private function checkApplicationMode(): array
    {
        $environment = (string) config('app.env');
        $debug = (bool) config('app.debug');
        $url = (string) config('app.url');

        if ($environment !== 'production') {
            return [
                'label' => 'Applikationsmodus',
                'status' => 'warning',
                'message' => 'APP_ENV ist aktuell auf '.$environment.' gesetzt. Fuer Go-Live wird production empfohlen.',
            ];
        }

        if ($debug) {
            return [
                'label' => 'Applikationsmodus',
                'status' => 'error',
                'message' => 'APP_DEBUG ist aktiv. Im Produktivbetrieb muss Debug deaktiviert sein.',
            ];
        }

        if (! str_starts_with($url, 'https://')) {
            return [
                'label' => 'Applikationsmodus',
                'status' => 'warning',
                'message' => 'APP_URL ist nicht auf HTTPS gesetzt. Fuer mobile PWA und sichere Cookies wird HTTPS benoetigt.',
            ];
        }

        return [
            'label' => 'Applikationsmodus',
            'status' => 'ok',
            'message' => 'Produktionseinstellungen fuer Environment, Debug und HTTPS wirken plausibel.',
        ];
    }

    private function checkPhp(): array
    {
        $ok = version_compare(PHP_VERSION, '8.2.0', '>=');

        return [
            'label' => 'PHP',
            'status' => $ok ? 'ok' : 'warning',
            'message' => 'Version '.PHP_VERSION,
        ];
    }

    private function checkInstallationContext(): array
    {
        $baseUrl = request()?->getBaseUrl() ?: '/';
        $appUrl = (string) config('app.url');
        $message = 'Installations-URL: '.$appUrl.' | Base Path: '.($baseUrl === '' ? '/' : $baseUrl);

        return [
            'label' => 'Installationskontext',
            'status' => 'ok',
            'message' => $message,
        ];
    }

    private function checkDatabase(): array
    {
        try {
            DB::select('select 1');

            return ['label' => 'Datenbank', 'status' => 'ok', 'message' => 'Verbindung erfolgreich.'];
        } catch (Throwable) {
            return ['label' => 'Datenbank', 'status' => 'error', 'message' => 'Verbindung fehlgeschlagen.'];
        }
    }

    private function checkStorage(): array
    {
        $writable = is_writable(storage_path('app'));

        return ['label' => 'Storage', 'status' => $writable ? 'ok' : 'error', 'message' => $writable ? 'Speicherpfad ist beschreibbar.' : 'Speicherpfad ist nicht beschreibbar.'];
    }

    private function checkCache(): array
    {
        try {
            Cache::put('diagnostic_probe', 'ok', 30);
            $value = Cache::get('diagnostic_probe');
            Cache::forget('diagnostic_probe');

            return ['label' => 'Cache', 'status' => $value === 'ok' ? 'ok' : 'warning', 'message' => $value === 'ok' ? 'Cache funktioniert.' : 'Cache-Antwort unerwartet.'];
        } catch (Throwable) {
            return ['label' => 'Cache', 'status' => 'error', 'message' => 'Cache-Test fehlgeschlagen.'];
        }
    }

    private function checkPwa(): array
    {
        $manifest = \Illuminate\Support\Facades\Route::has('pwa.manifest');
        $worker = \Illuminate\Support\Facades\Route::has('pwa.worker');
        $ok = $manifest && $worker;

        return ['label' => 'PWA', 'status' => $ok ? 'ok' : 'error', 'message' => $ok ? 'Dynamisches Manifest und Service Worker sind registriert.' : 'Dynamische PWA-Routen fehlen.'];
    }

    private function checkAssets(): array
    {
        $manifest = File::exists(public_path('build/manifest.json'));

        return [
            'label' => 'Frontend-Build',
            'status' => $manifest ? 'ok' : 'error',
            'message' => $manifest ? 'Vite-Build liegt unter public/build vor.' : 'Kein Vite-Manifest gefunden. Fuehre den Frontend-Build vor dem Go-Live aus.',
        ];
    }

    private function checkSessionSecurity(): array
    {
        $secure = (bool) config('session.secure');
        $httpOnly = (bool) config('session.http_only');
        $sameSite = (string) config('session.same_site', 'lax');
        $cookiePath = (string) config('session.path', '/');
        $sessionCookie = (string) config('session.cookie', 'laravel-session');
        $rememberCookie = Auth::guard()->getRecallerName();
        $rememberDays = (int) $this->settings->get('security', 'remember_days', 180);
        $persistentLoginEnabled = (bool) $this->settings->get('security', 'persistent_login_enabled', true);
        $https = str_starts_with((string) config('app.url'), 'https://');

        if (! $httpOnly) {
            return [
                'label' => 'Session-Sicherheit',
                'status' => 'error',
                'message' => 'SESSION_HTTP_ONLY ist deaktiviert. Session-Cookies muessen per JavaScript unzugaenglich sein.',
            ];
        }

        if ($sameSite === 'none' && ! $secure) {
            return [
                'label' => 'Session-Sicherheit',
                'status' => 'error',
                'message' => 'SameSite=None ohne Secure-Cookie ist unsicher und in modernen Browsern problematisch.',
            ];
        }

        if (! $secure) {
            return [
                'label' => 'Session-Sicherheit',
                'status' => 'warning',
                'message' => 'SESSION_SECURE_COOKIE ist nicht aktiv. Cookie-Pfad: '.$cookiePath.' | Session-Cookie: '.$sessionCookie.' | Remember-Cookie: '.$rememberCookie,
            ];
        }

        if (! $https) {
            return [
                'label' => 'Session-Sicherheit',
                'status' => 'warning',
                'message' => 'HTTPS ist fuer die aktuelle App-URL nicht aktiv. Persistente Anmeldung sollte nur ueber HTTPS genutzt werden.',
            ];
        }

        return [
            'label' => 'Session-Sicherheit',
            'status' => 'ok',
            'message' => 'Secure-, HttpOnly- und SameSite-Grundschutz sind aktiv. Cookie-Pfad: '.$cookiePath.' | Session-Cookie: '.$sessionCookie.' | Remember-Cookie: '.$rememberCookie.' | Remember-Login: '.($persistentLoginEnabled ? $rememberDays.' Tage' : 'deaktiviert').'.',
        ];
    }

    private function checkQueue(): array
    {
        $driver = (string) config('queue.default');

        if ($driver !== 'database') {
            return [
                'label' => 'Queue',
                'status' => 'warning',
                'message' => 'Queue-Driver ist auf '.$driver.' gesetzt. Die produktive Worker-Konfiguration sollte separat verifiziert werden.',
            ];
        }

        $jobsTable = Schema::hasTable(config('queue.connections.database.table', 'jobs'));
        $failedJobsTable = Schema::hasTable(config('queue.failed.table', 'failed_jobs'));

        if (! $jobsTable) {
            return [
                'label' => 'Queue',
                'status' => 'error',
                'message' => 'Die Jobs-Tabelle fuer den Datenbank-Queue-Driver fehlt.',
            ];
        }

        return [
            'label' => 'Queue',
            'status' => $failedJobsTable ? 'ok' : 'warning',
            'message' => $failedJobsTable
                ? 'Queue-Tabellen fuer Jobs und Failed Jobs sind vorhanden.'
                : 'Jobs-Tabelle ist vorhanden, aber failed_jobs fehlt.',
        ];
    }

    private function checkBranding(): array
    {
        $icons = $this->branding->viewData()['icons'] ?? [];
        $local = Storage::disk('local');
        $brandingWritable = is_writable(storage_path('app/private/branding')) || is_writable(storage_path('app/private')) || ! File::exists(storage_path('app/private/branding'));
        $favicon = $this->branding->assetForSlot('favicon');
        $background = $this->branding->assetForSlot('login_background');

        if (! $brandingWritable) {
            return [
                'label' => 'Branding Storage',
                'status' => 'warning',
                'message' => 'Branding-Verzeichnis ist aktuell nicht beschreibbar.',
            ];
        }

        $messages = [];
        $messages[] = ! empty($icons['main_logo']) ? 'Logo vorhanden oder Fallback aktiv.' : 'Standardlogo ohne Datei aktiv.';
        $messages[] = $favicon && ! $local->exists($favicon->storage_path) ? 'Favicon-Datei fehlt, Fallback aktiv.' : 'Favicon erreichbar.';
        $messages[] = $background && ! $local->exists($background->storage_path) ? 'Login-Hintergrund fehlt, Fallback aktiv.' : 'Login-Hintergrund erreichbar oder deaktiviert.';

        return [
            'label' => 'Branding',
            'status' => 'ok',
            'message' => implode(' ', $messages),
        ];
    }

    private function checkIntegrations(): array
    {
        $problem = $this->integrations->overview()->first(fn (array $provider) => in_array($provider['status'], ['error', 'warning'], true));

        return [
            'label' => 'Integrationen',
            'status' => $problem ? 'warning' : 'ok',
            'message' => $problem ? $problem['name'].' meldet '.$problem['status_label'].'.' : 'Alle konfigurierten Integrationen sind stabil oder deaktiviert.',
        ];
    }

    private function checkMail(): array
    {
        $host = $this->settings->getWithFallback('mail', 'host', env('MAIL_HOST'));
        $from = $this->settings->getWithFallback('mail', 'from_address', env('MAIL_FROM_ADDRESS'));

        return ['label' => 'E-Mail', 'status' => ($host && $from) ? 'ok' : 'warning', 'message' => ($host && $from) ? 'Mailversand konfiguriert.' : 'Mailversand noch nicht vollstaendig eingerichtet.'];
    }

    private function checkMigrations(): array
    {
        $files = collect(File::files(database_path('migrations')))->count();
        $ran = DB::table('migrations')->count();

        return ['label' => 'Migrationen', 'status' => $ran >= $files ? 'ok' : 'warning', 'message' => $ran >= $files ? 'Migrationen aktuell.' : 'Es stehen wahrscheinlich Migrationen aus.'];
    }

    private function checkExtensions(): array
    {
        $required = ['pdo', 'json', 'mbstring', 'openssl', 'gd'];
        $missing = collect($required)->reject(fn (string $ext) => extension_loaded($ext));

        return ['label' => 'PHP Extensions', 'status' => $missing->isEmpty() ? 'ok' : 'error', 'message' => $missing->isEmpty() ? 'Alle benoetigten Extensions aktiv.' : 'Fehlen: '.$missing->implode(', ')];
    }

    private function checkHeic(): array
    {
        return $this->scanService->supportsHeic()
            ? ['label' => 'HEIC/HEIF', 'status' => 'ok', 'message' => 'HEIC/HEIF kann serverseitig in JPEG umgewandelt werden.']
            : ['label' => 'HEIC/HEIF', 'status' => 'warning', 'message' => 'HEIC/HEIF wird auf diesem Server nicht dekodiert. JPEG, PNG oder WEBP verwenden.'];
    }

    public function recentApiErrors(): Collection
    {
        return ApiActivityLog::query()
            ->where('status', 'error')
            ->latest()
            ->take(5)
            ->get();
    }
}
