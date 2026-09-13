<?php

namespace App\Http\Controllers;

use App\Services\InstallationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Throwable;

class InstallController extends Controller
{
    public function __construct(private readonly InstallationService $installation) {}

    public function welcome(Request $request): View
    {
        return view('install.welcome', [
            'installed' => $this->installation->isInstalled(),
            'steps' => $this->steps(),
        ]);
    }

    public function system(): View
    {
        return view('install.system', [
            'installed' => $this->installation->isInstalled(),
            'report' => $this->installation->systemChecks(),
            'steps' => $this->steps(),
        ]);
    }

    public function url(Request $request): View
    {
        $detected = $this->installation->detectedUrl($request);
        $stored = session('installer.url', []);

        return view('install.url', [
            'installed' => $this->installation->isInstalled(),
            'steps' => $this->steps(),
            'detected' => $detected,
            'values' => array_merge($detected, $stored),
        ]);
    }

    public function storeUrl(Request $request): RedirectResponse
    {
        $detected = $this->installation->detectedUrl($request);
        $data = $request->validate([
            'app_url' => ['required', 'url', 'max:255'],
        ]);

        $url = rtrim($data['app_url'], '/');
        $parsed = parse_url($url);
        $basePath = trim($parsed['path'] ?? '', '/');

        session()->put('installer.url', [
            'scheme' => $parsed['scheme'] ?? $detected['scheme'],
            'host' => $parsed['host'] ?? $detected['host'],
            'port' => $parsed['port'] ?? $detected['port'],
            'base_path' => $basePath === '' ? '' : '/'.$basePath,
            'app_url' => $url,
            'https' => ($parsed['scheme'] ?? $detected['scheme']) === 'https',
        ]);

        return redirect()->route('install.database');
    }

    public function database(): View
    {
        return view('install.database', [
            'installed' => $this->installation->isInstalled(),
            'steps' => $this->steps(),
            'values' => session('installer.database', [
                'host' => 'localhost',
                'port' => 3306,
                'database' => '',
                'username' => '',
                'password' => '',
            ]),
        ]);
    }

    public function storeDatabase(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'database' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
        ]);

        $test = $this->installation->testDatabase($data);

        if (! $test['ok']) {
            return back()->withErrors(['host' => $test['message']])->withInput();
        }

        if ($test['existing_tables'] !== []) {
            return back()->withErrors([
                'database' => 'Es wurden bereits Anwendungstabellen gefunden: '.implode(', ', $test['existing_tables']).'. Die Installation wurde aus Sicherheitsgruenden blockiert.',
            ])->withInput();
        }

        session()->put('installer.database', $data);

        return redirect()->route('install.admin')->with('status', 'Datenbankverbindung erfolgreich getestet.');
    }

    public function admin(): View
    {
        return view('install.admin', [
            'installed' => $this->installation->isInstalled(),
            'steps' => $this->steps(),
            'values' => session('installer.admin', [
                'name' => '',
                'email' => '',
            ]),
        ]);
    }

    public function storeAdmin(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        session()->put('installer.admin', Arr::except($data, ['password', 'password_confirmation']) + [
            'password' => $data['password'],
        ]);

        return redirect()->route('install.settings');
    }

    public function settings(Request $request): View
    {
        $detected = $this->installation->detectedUrl($request);

        return view('install.settings', [
            'installed' => $this->installation->isInstalled(),
            'steps' => $this->steps(),
            'values' => session('installer.settings', [
                'app_name' => 'Wein-App',
                'timezone' => 'Europe/Zurich',
                'language' => 'de',
                'from_address' => '',
                'detected_app_url' => $detected['app_url'],
            ]),
            'canWriteConfig' => $this->installation->installerCanWriteConfig(),
        ]);
    }

    public function storeSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'app_name' => ['required', 'string', 'max:255'],
            'timezone' => ['required', 'timezone:all'],
            'language' => ['required', 'in:de,en'],
            'from_address' => ['nullable', 'email', 'max:255'],
        ]);

        session()->put('installer.settings', $data);

        return redirect()->route('install.run');
    }

    public function run(): View
    {
        return view('install.run', [
            'installed' => $this->installation->isInstalled(),
            'steps' => $this->steps(),
            'installer' => session('installer', []),
        ]);
    }

    public function perform(Request $request): RedirectResponse
    {
        if ($this->installation->isInstalled()) {
            return redirect()->route('install.welcome')->with('warning', 'Die Anwendung wurde bereits installiert.');
        }

        $payload = [
            'url' => session('installer.url'),
            'database' => session('installer.database'),
            'admin' => session('installer.admin'),
            'settings' => session('installer.settings'),
        ];

        foreach (['url', 'database', 'admin', 'settings'] as $step) {
            if (empty($payload[$step])) {
                return redirect()->route('install.'.($step === 'url' ? 'url' : $step))
                    ->with('warning', 'Bitte den Installationsassistenten vollstaendig durchlaufen.');
            }
        }

        try {
            $this->installation->install($payload);
            $this->installation->resetInstallerSessionData();
        } catch (Throwable $exception) {
            report($exception);

            $message = match (true) {
                $exception instanceof QueryException => $this->databaseErrorMessage($exception),
                $exception instanceof \RuntimeException => $exception->getMessage(),
                default => 'Die Installation konnte auf dem Server nicht abgeschlossen werden. Bitte pruefe PHP-Version, Erweiterungen und Schreibrechte.',
            };

            return back()->with('error', $message);
        }

        return redirect()->route('login')->with('status', 'Installation erfolgreich abgeschlossen. Bitte anmelden.');
    }

    private function databaseErrorMessage(QueryException $exception): string
    {
        $technical = $exception->getPrevious()?->getMessage() ?: $exception->getMessage();
        $technical = preg_replace('/(password|passwd|pwd)\s*[=:]\s*[^\s,;]+/i', '$1=[redacted]', $technical) ?: '';
        $technical = trim(preg_replace('/\s+/', ' ', $technical));

        return 'Datenbankmigration fehlgeschlagen. Ursache: '.\Illuminate\Support\Str::limit($technical, 500).'. Verwende eine wirklich leere MySQL-Datenbank und pruefe CREATE, ALTER, INDEX und FOREIGN KEY.';
    }

    public function complete(): View
    {
        return view('install.complete', [
            'installed' => $this->installation->isInstalled(),
            'steps' => $this->steps(),
        ]);
    }

    private function steps(): array
    {
        return [
            1 => ['label' => 'Willkommen', 'route' => 'install.welcome'],
            2 => ['label' => 'System', 'route' => 'install.system'],
            3 => ['label' => 'URL', 'route' => 'install.url'],
            4 => ['label' => 'Datenbank', 'route' => 'install.database'],
            5 => ['label' => 'Administrator', 'route' => 'install.admin'],
            6 => ['label' => 'Einstellungen', 'route' => 'install.settings'],
            7 => ['label' => 'Installation', 'route' => 'install.run'],
        ];
    }
}
