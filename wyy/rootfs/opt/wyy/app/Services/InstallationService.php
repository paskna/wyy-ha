<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class InstallationService
{
    public const MINIMUM_PHP_VERSION = '8.3.0';

    public function isInstalled(): bool
    {
        // The marker is the single authoritative installation state. A later
        // database outage must never reopen the installer. Some shared hosts
        // handle storage/app differently from bootstrap/cache, so both files
        // are written by the installer and either one may be read here.
        return File::exists($this->lockFile()) || File::exists($this->fallbackLockFile());
    }

    public function lockFile(): string
    {
        return storage_path('app/installed.lock');
    }

    public function runtimeConfigFile(): string
    {
        return storage_path('app/config/runtime.php');
    }

    public function fallbackRuntimeConfigFile(): string
    {
        return base_path('bootstrap/cache/wein-runtime.php');
    }

    public function fallbackLockFile(): string
    {
        return base_path('bootstrap/cache/wein-installed.lock');
    }

    public function runtimeConfiguration(): array
    {
        $file = $this->runtimeConfigFile();

        if (File::exists($file)) {
            $configuration = require $file;

            if (is_array($configuration)) {
                return $configuration;
            }
        }

        if (File::exists($this->fallbackRuntimeConfigFile())) {
            $configuration = require $this->fallbackRuntimeConfigFile();

            if (is_array($configuration)) {
                return $configuration;
            }
        }

        // The lock is the last-resort bootstrap fallback for hosts that reject
        // both .env and PHP config files but allow a small status file.
        if (File::exists($this->lockFile())) {
            $lock = json_decode(File::get($this->lockFile()), true);

            if (is_array($lock) && is_array($lock['environment'] ?? null)) {
                return $lock['environment'];
            }
        }

        if (File::exists($this->fallbackLockFile())) {
            $lock = json_decode(File::get($this->fallbackLockFile()), true);

            if (is_array($lock) && is_array($lock['environment'] ?? null)) {
                return $lock['environment'];
            }
        }

        return [];
    }

    public function detectedUrl(Request $request): array
    {
        $scheme = $this->detectedScheme($request);
        $rawHost = (string) ($request->server('HTTP_HOST') ?: $request->server('SERVER_NAME') ?: $request->getHost());
        $host = $this->validatedHost((string) preg_replace('/:\d+$/', '', $rawHost));
        $port = (int) ($request->server('SERVER_PORT') ?: $request->getPort());
        $basePath = $this->basePath($request);
        $showPort = ! in_array([$scheme, $port], [['http', 80], ['https', 443]], true);
        $authority = $host.($showPort ? ':'.$port : '');
        $appUrl = rtrim($scheme.'://'.$authority.$basePath, '/');

        return [
            'scheme' => $scheme,
            'host' => $host,
            'port' => $showPort ? $port : null,
            'base_path' => $basePath,
            'app_url' => $appUrl === '' ? $scheme.'://'.$authority : $appUrl,
            'https' => $scheme === 'https',
        ];
    }

    public function basePath(Request $request): string
    {
        $ingressPath = app(DeploymentMode::class)->ingressPath($request);

        if ($ingressPath !== null) {
            return $ingressPath;
        }

        $scriptName = str_replace('\\', '/', (string) $request->server('SCRIPT_NAME', ''));
        $directory = trim(dirname($scriptName), '/.');

        return $directory === '' ? '' : '/'.$directory;
    }

    public function systemChecks(): array
    {
        $storageWritable = is_writable(storage_path());
        $bootstrapCacheWritable = is_writable(base_path('bootstrap/cache'));
        $uploadMax = ini_get('upload_max_filesize') ?: 'unbekannt';
        $postMax = ini_get('post_max_size') ?: 'unbekannt';
        $memoryLimit = ini_get('memory_limit') ?: 'unbekannt';
        $executionTime = ini_get('max_execution_time') ?: 'unbekannt';

        $checks = [
            $this->check('PHP Version', version_compare(PHP_VERSION, self::MINIMUM_PHP_VERSION, '>='), 'Version '.PHP_VERSION, 'Mindestens '.self::MINIMUM_PHP_VERSION.' erforderlich.'),
            $this->extensionCheck('PDO', 'pdo'),
            $this->extensionCheck('PDO MySQL', 'pdo_mysql'),
            $this->extensionCheck('OpenSSL', 'openssl'),
            $this->extensionCheck('Mbstring', 'mbstring'),
            $this->extensionCheck('Tokenizer', 'tokenizer'),
            $this->extensionCheck('XML', 'xml'),
            $this->extensionCheck('Ctype', 'ctype'),
            $this->extensionCheck('JSON', 'json'),
            $this->extensionCheck('Fileinfo', 'fileinfo'),
            $this->optionalExtensionCheck('Bildverarbeitung', ['gd', 'imagick']),
            $this->optionalExtensionCheck('Intl', ['intl']),
            $this->optionalExtensionCheck('ZIP', ['zip']),
            $this->extensionCheck('cURL', 'curl'),
            $this->check('Storage beschreibbar', $storageWritable, 'storage ist beschreibbar.', 'storage ist nicht beschreibbar.'),
            $this->check('Installationsstatus beschreibbar', is_writable(storage_path('app')) || is_writable(base_path('bootstrap/cache')), 'Installationsstatus kann gespeichert werden.', 'storage/app und bootstrap/cache sind nicht beschreibbar.'),
            $this->check('Bootstrap Cache beschreibbar', $bootstrapCacheWritable, 'bootstrap/cache ist beschreibbar.', 'bootstrap/cache ist nicht beschreibbar.'),
            $this->warningCheck('Upload Limit', $uploadMax, $this->normalizeIniBytes($uploadMax) < 4 * 1024 * 1024 ? 'Warnung: weniger als 4 MB Upload-Limit.' : 'Upload-Limit ausreichend.'),
            $this->warningCheck('post_max_size', $postMax, 'POST-Limit aktuell '.$postMax.'.'),
            $this->warningCheck('memory_limit', $memoryLimit, 'Memory Limit aktuell '.$memoryLimit.'.'),
            $this->warningCheck('max_execution_time', $executionTime, 'max_execution_time aktuell '.$executionTime.' Sekunden.'),
        ];

        return [
            'checks' => $checks,
            'blocking' => collect($checks)->contains(fn (array $check) => $check['status'] === 'error'),
        ];
    }

    public function testDatabase(array $data): array
    {
        $connectionName = 'installer_probe';

        Config::set("database.connections.$connectionName", [
            'driver' => 'mysql',
            'host' => $data['host'],
            'port' => (int) $data['port'],
            'database' => $data['database'],
            'username' => $data['username'],
            'password' => $data['password'],
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
        ]);

        $probeTable = null;
        $childTable = null;

        try {
            DB::purge($connectionName);
            $connection = DB::connection($connectionName);
            $connection->getPdo();

            $probeTable = 'wein_app_install_probe_'.Str::lower(Str::random(8));
            $connection->statement("CREATE TABLE `$probeTable` (id INT PRIMARY KEY AUTO_INCREMENT)");
            $connection->statement("ALTER TABLE `$probeTable` ADD COLUMN probe_value VARCHAR(191) NULL");
            $connection->statement("CREATE INDEX `{$probeTable}_value_index` ON `$probeTable` (`probe_value`)");

            $childTable = $probeTable.'_child';
            $connection->statement("CREATE TABLE `$childTable` (id INT PRIMARY KEY AUTO_INCREMENT, probe_id INT NOT NULL, CONSTRAINT `{$childTable}_probe_fk` FOREIGN KEY (`probe_id`) REFERENCES `$probeTable` (`id`) ON DELETE CASCADE)");

            $existing = $this->existingApplicationTables($connectionName);

            return [
                'ok' => true,
                'message' => $existing === [] ? 'Datenbankverbindung erfolgreich.' : 'Datenbankverbindung erfolgreich, aber bestehende Anwendungstabellen wurden erkannt.',
                'existing_tables' => $existing,
            ];
        } catch (Throwable $exception) {
            return [
                'ok' => false,
                'message' => 'Datenbankverbindung fehlgeschlagen. Bitte Zugangsdaten und Rechte pruefen.',
                'existing_tables' => [],
            ];
        } finally {
            try {
                $connection = DB::connection($connectionName);
                if ($childTable !== null) {
                    $connection->statement("DROP TABLE IF EXISTS `$childTable`");
                }
                if ($probeTable !== null) {
                    $connection->statement("DROP TABLE IF EXISTS `$probeTable`");
                }
            } catch (Throwable) {
                // The connection is purged below; cleanup failures are not fatal
                // to the diagnostic result.
            }
            DB::purge($connectionName);
            Config::offsetUnset("database.connections.$connectionName");
        }
    }

    public function install(array $payload): void
    {
        $config = $payload['database'];
        $url = $payload['url'];
        $settings = $payload['settings'];
        $admin = $payload['admin'];

        $appKey = 'base64:'.base64_encode(random_bytes(32));
        $sessionPath = $url['base_path'] === '' ? '/' : $url['base_path'];
        $sessionCookie = Str::slug($settings['app_name'], '-').'-'.substr(sha1($sessionPath), 0, 8).'-session';

        $environment = [
            'APP_NAME' => $settings['app_name'],
            'APP_ENV' => 'production',
            'APP_KEY' => $appKey,
            'APP_DEBUG' => 'false',
            'APP_URL' => $url['app_url'],
            'APP_LOCALE' => $settings['language'],
            'APP_FALLBACK_LOCALE' => $settings['language'],
            'APP_FAKER_LOCALE' => $settings['language'] === 'de' ? 'de_DE' : 'en_US',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $config['host'],
            'DB_PORT' => $config['port'],
            'DB_DATABASE' => $config['database'],
            'DB_USERNAME' => $config['username'],
            'DB_PASSWORD' => $config['password'],
            'SESSION_DRIVER' => 'database',
            'SESSION_LIFETIME' => 120,
            'SESSION_PATH' => $sessionPath,
            'SESSION_DOMAIN' => 'null',
            'SESSION_SECURE_COOKIE' => $url['https'] ? 'true' : 'false',
            'SESSION_HTTP_ONLY' => 'true',
            'SESSION_SAME_SITE' => 'lax',
            'SESSION_COOKIE' => $sessionCookie,
            'CACHE_STORE' => 'database',
            'QUEUE_CONNECTION' => 'database',
            'FILESYSTEM_DISK' => 'local',
        ];

        $this->writeEnvironment($environment);

        foreach ($environment as $key => $value) {
            putenv($key.'='.$value);
            $_ENV[$key] = (string) $value;
            $_SERVER[$key] = (string) $value;
        }

        Config::set('app.key', $appKey);
        Config::set('app.url', $url['app_url']);
        Config::set('database.default', 'mysql');
        Config::set('database.connections.mysql.host', $config['host']);
        Config::set('database.connections.mysql.port', (int) $config['port']);
        Config::set('database.connections.mysql.database', $config['database']);
        Config::set('database.connections.mysql.username', $config['username']);
        Config::set('database.connections.mysql.password', $config['password']);
        Config::set('session.path', $sessionPath);
        Config::set('session.cookie', $sessionCookie);
        Config::set('session.secure', $url['https']);

        DB::purge('mysql');
        DB::reconnect('mysql');

        $existingTables = $this->existingApplicationTables('mysql');

        if ($existingTables !== []) {
            throw new \RuntimeException('Es wurden bereits Tabellen dieser Anwendung gefunden. Die Installation wurde aus Sicherheitsgruenden abgebrochen.');
        }

        File::ensureDirectoryExists(storage_path('app/config'));
        File::ensureDirectoryExists(storage_path('app/scans'));
        File::ensureDirectoryExists(storage_path('framework/cache'));
        File::ensureDirectoryExists(storage_path('framework/sessions'));
        File::ensureDirectoryExists(storage_path('framework/views'));
        File::ensureDirectoryExists(storage_path('logs'));

        $migrationExitCode = Artisan::call('migrate', [
            '--database' => 'mysql',
            '--force' => true,
        ]);

        if ($migrationExitCode !== 0) {
            $output = trim(preg_replace('/\s+/', ' ', Artisan::output()) ?: '');
            throw new \RuntimeException('Datenbankmigration fehlgeschlagen'.($output !== '' ? ': '.Str::limit($output, 700) : '.'));
        }

        DB::transaction(function () use ($admin, $settings): void {
            $user = User::query()->create([
                'name' => $admin['name'],
                'email' => $admin['email'],
                'password' => $admin['password'],
                'timezone' => $settings['timezone'],
                'role' => UserRole::Admin,
                'status' => UserStatus::Active,
            ]);

            $settingsService = app(SettingsService::class);
            $settingsService->setMany('general', [
                'app_name' => ['value' => $settings['app_name']],
                'language' => ['value' => $settings['language']],
                'timezone' => ['value' => $settings['timezone']],
            ]);

            if (! empty($settings['from_address'])) {
                $settingsService->set('mail', 'from_address', $settings['from_address']);
            }

            app(AuditLogService::class)->log($user, $user, 'installation_completed', [
                'app_url' => config('app.url'),
            ]);
        });

        Artisan::call('optimize:clear');

        app(PostInstallationValidator::class)->assertValid();

        $lockPayload = json_encode([
            'installed_at' => now()->toIso8601String(),
            'app_url' => $url['app_url'],
            'base_path' => $url['base_path'],
            'environment' => $environment,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        File::ensureDirectoryExists(dirname($this->lockFile()));
        File::ensureDirectoryExists(dirname($this->fallbackLockFile()));

        $lockWritten = File::put($this->lockFile(), $lockPayload);
        $fallbackLockWritten = File::put($this->fallbackLockFile(), $lockPayload);

        if (($lockWritten === false || ! File::exists($this->lockFile()))
            && ($fallbackLockWritten === false || ! File::exists($this->fallbackLockFile()))) {
            throw new \RuntimeException('Installation abgeschlossen, aber der Installationsstatus konnte nicht gespeichert werden. Bitte storage/app beschreibbar machen.');
        }

        if (! $this->isInstalled()) {
            throw new \RuntimeException('Der Installationsmarker konnte nach dem Schreiben nicht gelesen werden.');
        }
    }

    public function writeEnvironment(array $pairs): void
    {
        $template = File::exists(base_path('.env.example'))
            ? File::get(base_path('.env.example'))
            : '';

        $lines = preg_split("/\r\n|\n|\r/", $template ?: '') ?: [];
        $final = [];
        $handled = [];

        foreach ($lines as $line) {
            if (! str_contains($line, '=')) {
                $final[] = $line;

                continue;
            }

            [$key] = explode('=', $line, 2);
            $key = trim($key);

            if (array_key_exists($key, $pairs)) {
                $final[] = $key.'='.$this->quoteEnvValue($pairs[$key]);
                $handled[$key] = true;

                continue;
            }

            $final[] = $line;
        }

        foreach ($pairs as $key => $value) {
            if (! isset($handled[$key])) {
                $final[] = $key.'='.$this->quoteEnvValue($value);
            }
        }

        $content = implode(PHP_EOL, $final).PHP_EOL;

        $envWritten = false;

        if (File::isWritable(base_path()) && (File::exists(base_path('.env')) ? File::isWritable(base_path('.env')) : true)) {
            $envWritten = File::put(base_path('.env'), $content) !== false
                && File::exists(base_path('.env'));
        }

        // Always keep a protected runtime fallback. Shared hosts often report
        // the project root as writable but silently reject writing .env.
        File::ensureDirectoryExists(dirname($this->runtimeConfigFile()));
        $runtimeContent = "<?php\n\nreturn ".var_export($pairs, true).";\n";
        $runtimeWritten = false;

        File::ensureDirectoryExists(dirname($this->runtimeConfigFile()));
        if (File::isWritable(dirname($this->runtimeConfigFile()))) {
            $runtimeWritten = File::put($this->runtimeConfigFile(), $runtimeContent) !== false
                && File::exists($this->runtimeConfigFile());
        }

        File::ensureDirectoryExists(dirname($this->fallbackRuntimeConfigFile()));
        if (! $runtimeWritten && File::isWritable(dirname($this->fallbackRuntimeConfigFile()))) {
            $runtimeWritten = File::put($this->fallbackRuntimeConfigFile(), $runtimeContent) !== false
                && File::exists($this->fallbackRuntimeConfigFile());
        }

        if (! $envWritten && ! $runtimeWritten) {
            throw new \RuntimeException('Die Konfiguration konnte nicht gespeichert werden. Bitte .env oder storage/app/config beschreibbar machen.');
        }
    }

    public function installerCanWriteConfig(): bool
    {
        return (File::exists(base_path('.env')) && File::isWritable(base_path('.env')))
            || (! File::exists(base_path('.env')) && File::isWritable(base_path()))
            || File::isWritable(storage_path('app'))
            || (! File::exists($this->runtimeConfigFile()) && File::isWritable(dirname($this->runtimeConfigFile())));
    }

    public function resetInstallerSessionData(): void
    {
        session()->forget('installer');
    }

    private function existingApplicationTables(string $connectionName): array
    {
        $tables = [
            'migrations',
            'users',
            'password_reset_tokens',
            'sessions',
            'cache',
            'cache_locks',
            'jobs',
            'job_batches',
            'failed_jobs',
            'producers',
            'wines',
            'wine_vintages',
            'grape_varieties',
            'wine_vintage_grapes',
            'wine_images',
            'user_wines',
            'scans',
            'wine_sources',
            'wine_taste_features',
            'user_taste_profiles',
            'settings',
            'audit_logs',
            'api_activity_logs',
            'branding_assets',
        ];

        return collect($tables)
            ->filter(fn (string $table) => Schema::connection($connectionName)->hasTable($table))
            ->values()
            ->all();
    }

    private function detectedScheme(Request $request): string
    {
        $forwardedProto = strtolower((string) $request->server('HTTP_X_FORWARDED_PROTO', ''));
        $forwardedSsl = strtolower((string) $request->server('HTTP_X_FORWARDED_SSL', ''));

        if ($forwardedProto === 'https' || $forwardedSsl === 'on') {
            return 'https';
        }

        return $request->isSecure() ? 'https' : 'http';
    }

    private function validatedHost(string $host): string
    {
        $host = trim($host);

        if ($host === '' || preg_match('/[^a-z0-9\.\-\:]/i', $host)) {
            return 'localhost';
        }

        return $host;
    }

    private function check(string $label, bool $ok, string $successMessage, string $errorMessage): array
    {
        return [
            'label' => $label,
            'status' => $ok ? 'ok' : 'error',
            'message' => $ok ? $successMessage : $errorMessage,
        ];
    }

    private function warningCheck(string $label, string $value, string $message): array
    {
        return [
            'label' => $label,
            'status' => 'warning',
            'message' => $message,
            'value' => $value,
        ];
    }

    private function extensionCheck(string $label, string $extension): array
    {
        return $this->check($label, extension_loaded($extension), $label.' ist verfuegbar.', $label.' fehlt.');
    }

    private function optionalExtensionCheck(string $label, array $extensions): array
    {
        $available = collect($extensions)->first(fn (string $extension) => extension_loaded($extension));

        return [
            'label' => $label,
            'status' => $available ? 'ok' : 'warning',
            'message' => $available ? $label.' ist ueber '.$available.' verfuegbar.' : $label.' ist optional nicht verfuegbar.',
        ];
    }

    private function normalizeIniBytes(string $value): int
    {
        $value = trim($value);
        $unit = strtolower(substr($value, -1));
        $amount = (int) $value;

        return match ($unit) {
            'g' => $amount * 1024 * 1024 * 1024,
            'm' => $amount * 1024 * 1024,
            'k' => $amount * 1024,
            default => (int) $value,
        };
    }

    private function quoteEnvValue(mixed $value): string
    {
        $string = (string) $value;

        if ($string === 'true' || $string === 'false' || $string === 'null' || preg_match('/^[A-Za-z0-9_\-\.\/:]+$/', $string)) {
            return $string;
        }

        return '"'.str_replace(
            ['\\', '"', '$'],
            ['\\\\', '\"', '\$'],
            $string,
        ).'"';
    }
}
