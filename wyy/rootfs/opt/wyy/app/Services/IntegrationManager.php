<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class IntegrationManager
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly ApiActivityLogService $apiLogs,
        private readonly AuditLogService $auditLog,
        private readonly HttpFactory $http,
    ) {}

    public function providers(): Collection
    {
        return collect([
            'openai' => [
                'name' => 'OpenAI',
                'description' => 'Bilderkennung, Textverarbeitung und Wein-Datenstrukturierung.',
                'group' => 'integration.openai',
                'fields' => [
                    'enabled', 'api_key', 'image_model', 'text_model', 'structuring_model', 'timeout', 'retries',
                ],
            ],
            'wine_provider' => [
                'name' => 'Wine-Searcher',
                'description' => 'Externe Weindaten fuer Recherche und Anreicherung.',
                'group' => 'integration.wine_provider',
                'fields' => [
                    'enabled', 'api_key', 'endpoint', 'region', 'timeout', 'cache_minutes',
                ],
            ],
            'google_vision' => [
                'name' => 'Google Vision',
                'description' => 'Optionale OCR- und Web-Detection-Ergaenzung.',
                'group' => 'integration.google_vision',
                'fields' => [
                    'enabled', 'credentials_json', 'endpoint', 'ocr_enabled', 'web_detection_enabled', 'timeout',
                ],
            ],
        ]);
    }

    public function overview(): Collection
    {
        return $this->providers()->map(fn (array $provider, string $slug) => $this->provider($slug));
    }

    public function provider(string $slug): array
    {
        $provider = $this->providers()->get($slug);

        if (! $provider) {
            throw new RuntimeException('Unbekannter Provider.');
        }

        $config = $this->config($slug);
        $keyField = $slug === 'google_vision' ? 'credentials_json' : 'api_key';
        $hasSecret = filled($config[$keyField] ?? null);
        $lastTestStatus = $config['last_test_status'] ?? null;

        $status = ! $hasSecret && ! filled($config['endpoint'] ?? null)
            ? 'not_configured'
            : (! ($config['enabled'] ?? false) ? 'disabled' : ($lastTestStatus === 'success' ? 'active' : ($lastTestStatus === 'error' ? 'error' : 'warning')));

        return array_merge($provider, [
            'slug' => $slug,
            'status' => $status,
            'status_label' => match ($status) {
                'active' => 'Aktiv',
                'disabled' => 'Deaktiviert',
                'error' => 'Fehler',
                'warning' => 'Warnung',
                default => 'Nicht konfiguriert',
            },
            'config' => $config,
            'masked_secret' => $hasSecret ? $this->maskSecret((string) $config[$keyField]) : null,
        ]);
    }

    public function config(string $slug): array
    {
        return match ($slug) {
            'openai' => [
                'enabled' => (bool) $this->settings->getWithFallback('integration.openai', 'enabled', null, false),
                'api_key' => $this->settings->getWithFallback('integration.openai', 'api_key', env('OPENAI_API_KEY')),
                'image_model' => $this->settings->getWithFallback('integration.openai', 'image_model', env('OPENAI_IMAGE_MODEL'), 'gpt-4.1-mini'),
                'text_model' => $this->settings->getWithFallback('integration.openai', 'text_model', env('OPENAI_TEXT_MODEL'), 'gpt-4.1-mini'),
                'structuring_model' => $this->settings->getWithFallback('integration.openai', 'structuring_model', env('OPENAI_STRUCTURING_MODEL'), 'gpt-4.1-mini'),
                'timeout' => (int) $this->settings->getWithFallback('integration.openai', 'timeout', env('OPENAI_TIMEOUT'), 10),
                'retries' => (int) $this->settings->getWithFallback('integration.openai', 'retries', env('OPENAI_RETRIES'), 1),
                'priority' => (int) $this->settings->get('integration.openai', 'priority', 1),
                'fallback_provider' => $this->settings->get('integration.openai', 'fallback_provider'),
                'last_test_status' => $this->settings->get('integration.openai', 'last_test_status'),
                'last_test_at' => $this->settings->get('integration.openai', 'last_test_at'),
                'last_test_message' => $this->settings->get('integration.openai', 'last_test_message'),
                'last_test_model' => $this->settings->get('integration.openai', 'last_test_model'),
                'last_test_response_ms' => $this->settings->get('integration.openai', 'last_test_response_ms'),
                'last_error' => $this->settings->get('integration.openai', 'last_error'),
            ],
            'wine_provider' => [
                'enabled' => (bool) $this->settings->getWithFallback('integration.wine_provider', 'enabled', null, false),
                'api_key' => $this->settings->getWithFallback('integration.wine_provider', 'api_key', env('WINE_SEARCHER_API_KEY')),
                'endpoint' => $this->settings->getWithFallback('integration.wine_provider', 'endpoint', env('WINE_SEARCHER_ENDPOINT')),
                'region' => $this->settings->getWithFallback('integration.wine_provider', 'region', env('WINE_SEARCHER_REGION')),
                'timeout' => (int) $this->settings->getWithFallback('integration.wine_provider', 'timeout', env('WINE_SEARCHER_TIMEOUT'), 10),
                'cache_minutes' => (int) $this->settings->getWithFallback('integration.wine_provider', 'cache_minutes', env('WINE_SEARCHER_CACHE_MINUTES'), 1440),
                'priority' => (int) $this->settings->get('integration.wine_provider', 'priority', 1),
                'fallback_provider' => $this->settings->get('integration.wine_provider', 'fallback_provider'),
                'last_test_status' => $this->settings->get('integration.wine_provider', 'last_test_status'),
                'last_test_at' => $this->settings->get('integration.wine_provider', 'last_test_at'),
                'last_test_message' => $this->settings->get('integration.wine_provider', 'last_test_message'),
                'last_test_response_ms' => $this->settings->get('integration.wine_provider', 'last_test_response_ms'),
                'last_error' => $this->settings->get('integration.wine_provider', 'last_error'),
            ],
            'google_vision' => [
                'enabled' => (bool) $this->settings->getWithFallback('integration.google_vision', 'enabled', null, false),
                'credentials_json' => $this->settings->getWithFallback('integration.google_vision', 'credentials_json'),
                'endpoint' => $this->settings->getWithFallback('integration.google_vision', 'endpoint', env('GOOGLE_VISION_ENDPOINT'), 'https://vision.googleapis.com/v1/images:annotate'),
                'ocr_enabled' => (bool) $this->settings->getWithFallback('integration.google_vision', 'ocr_enabled', null, true),
                'web_detection_enabled' => (bool) $this->settings->getWithFallback('integration.google_vision', 'web_detection_enabled', null, false),
                'timeout' => (int) $this->settings->getWithFallback('integration.google_vision', 'timeout', env('GOOGLE_VISION_TIMEOUT'), 10),
                'priority' => (int) $this->settings->get('integration.google_vision', 'priority', 2),
                'fallback_provider' => $this->settings->get('integration.google_vision', 'fallback_provider'),
                'last_test_status' => $this->settings->get('integration.google_vision', 'last_test_status'),
                'last_test_at' => $this->settings->get('integration.google_vision', 'last_test_at'),
                'last_test_message' => $this->settings->get('integration.google_vision', 'last_test_message'),
                'last_test_response_ms' => $this->settings->get('integration.google_vision', 'last_test_response_ms'),
                'last_error' => $this->settings->get('integration.google_vision', 'last_error'),
            ],
            default => throw new RuntimeException('Unbekannter Provider.'),
        };
    }

    public function save(string $slug, array $data, User $admin): void
    {
        $group = 'integration.'.$slug;
        $entries = [];

        foreach ($data as $key => $value) {
            if (in_array($key, ['api_key', 'credentials_json'], true)) {
                if ($value === '__keep__' || $value === null || $value === '') {
                    continue;
                }

                $entries[$key] = [
                    'value' => $value,
                    'type' => 'string',
                    'encrypted' => true,
                ];

                continue;
            }

            $entries[$key] = [
                'value' => $value,
                'type' => is_bool($value) ? 'bool' : (is_int($value) ? 'int' : 'string'),
                'encrypted' => false,
            ];
        }

        if ($entries !== []) {
            $this->settings->setMany($group, $entries);
        }

        $this->auditLog->log($admin, $admin, 'integration_updated', ['provider' => $slug]);
    }

    public function test(string $slug, User $admin): array
    {
        $config = $this->config($slug);
        $startedAt = microtime(true);

        try {
            $result = match ($slug) {
                'openai' => $this->testOpenAi($config),
                'wine_provider' => $this->testWineProvider($config),
                'google_vision' => $this->testGoogleVision($config),
                default => throw new RuntimeException('Unbekannter Provider.'),
            };

            $elapsed = (int) round((microtime(true) - $startedAt) * 1000);
            $group = 'integration.'.$slug;

            $this->settings->setMany($group, [
                'last_test_status' => ['value' => 'success'],
                'last_test_at' => ['value' => now()->toIso8601String()],
                'last_test_message' => ['value' => $result['message']],
                'last_test_response_ms' => ['value' => $elapsed, 'type' => 'int'],
                'last_test_model' => ['value' => $result['model'] ?? null],
                'last_error' => ['value' => null],
            ]);

            $this->apiLogs->log($slug, 'connection_test', 'success', $elapsed, $result['http_status'] ?? 200, $result['message'], $admin);
            $this->auditLog->log($admin, $admin, 'integration_tested', ['provider' => $slug, 'status' => 'success']);

            return [
                'ok' => true,
                'message' => $result['message'],
                'response_time_ms' => $elapsed,
            ];
        } catch (ConnectionException) {
            return $this->recordFailure($slug, $admin, 'Timeout.');
        } catch (RequestException $exception) {
            $message = $exception->response?->status() === 401
                ? 'API-Key wurde abgelehnt.'
                : ($exception->response?->status() === 404 ? 'Endpoint oder Modell nicht verfuegbar.' : 'Verbindung fehlgeschlagen.');

            return $this->recordFailure($slug, $admin, $message, (int) ($exception->response?->status() ?? 500));
        } catch (RuntimeException $exception) {
            return $this->recordFailure($slug, $admin, $exception->getMessage());
        }
    }

    public function recognitionOrder(): array
    {
        $providers = ['openai'];

        if ((bool) $this->settings->get('scan', 'use_ocr', true)) {
            $providers[] = 'google_vision';
        }

        return $this->sortedProviders($providers);
    }

    public function enrichmentOrder(): array
    {
        if (! (bool) $this->settings->get('scan', 'external_research', true)) {
            return [];
        }

        return $this->sortedProviders(['wine_provider', 'openai']);
    }

    public function recognizeWineLabel(UploadedFile $file, ?User $user = null): array
    {
        $errors = [];

        foreach ($this->recognitionOrder() as $slug) {
            try {
                return match ($slug) {
                    'openai' => $this->recognizeWithOpenAi($file, $user),
                    'google_vision' => $this->recognizeWithGoogleVision($file, $user),
                    default => throw new RuntimeException('Dieser Erkennungs-Provider ist zur Laufzeit noch nicht angebunden.'),
                };
            } catch (ConnectionException) {
                $errors[] = ['provider' => $slug, 'message' => 'Timeout.'];
            } catch (RequestException $exception) {
                $errors[] = ['provider' => $slug, 'message' => $this->httpFailureMessage($exception)];
            } catch (RuntimeException $exception) {
                $errors[] = ['provider' => $slug, 'message' => $exception->getMessage()];
            }
        }

        return [
            'status' => 'manual_required',
            'provider' => null,
            'message' => $errors === []
                ? 'Automatische Weinerkennung ist derzeit nicht vollstaendig eingerichtet. Du kannst den Wein manuell erfassen.'
                : 'Die automatische Weinerkennung war fuer dieses Bild nicht verfuegbar. Du kannst den Wein manuell erfassen.',
            'errors' => $errors,
        ];
    }

    public function enrichWineData(array $recognized, ?User $user = null): array
    {
        if (! (bool) $this->settings->get('scan', 'auto_enrichment', true)) {
            return $recognized + [
                'enrichment_status' => 'disabled',
                'enrichment_message' => 'Automatische Datenanreicherung ist derzeit deaktiviert.',
                'enrichment_errors' => [],
            ];
        }

        $errors = [];

        foreach ($this->enrichmentOrder() as $slug) {
            try {
                return match ($slug) {
                    'openai' => $this->enrichWithOpenAi($recognized, $user),
                    'wine_provider' => $this->enrichWithWineProvider($recognized, $user),
                    default => throw new RuntimeException('Dieser Enrichment-Provider ist zur Laufzeit noch nicht angebunden.'),
                };
            } catch (ConnectionException) {
                $errors[] = ['provider' => $slug, 'message' => 'Timeout.'];
            } catch (RequestException $exception) {
                $errors[] = ['provider' => $slug, 'message' => $this->httpFailureMessage($exception)];
            } catch (RuntimeException $exception) {
                $errors[] = ['provider' => $slug, 'message' => $exception->getMessage()];
            }
        }

        return $recognized + [
            'enrichment_status' => 'not_available',
            'enrichment_message' => $errors === []
                ? 'Keine externe Anreicherung konfiguriert.'
                : 'Externe Anreicherung derzeit nicht verfuegbar.',
            'enrichment_errors' => $errors,
        ];
    }

    public function applyMailConfiguration(): array
    {
        $host = $this->settings->getWithFallback('mail', 'host', env('MAIL_HOST'));
        $port = (int) $this->settings->getWithFallback('mail', 'port', env('MAIL_PORT'), 587);
        $username = $this->settings->getWithFallback('mail', 'username', env('MAIL_USERNAME'));
        $password = $this->settings->getWithFallback('mail', 'password', env('MAIL_PASSWORD'));
        $encryption = $this->settings->getWithFallback('mail', 'encryption', env('MAIL_ENCRYPTION'));
        $fromName = $this->settings->getWithFallback('mail', 'from_name', env('MAIL_FROM_NAME'), config('app.name'));
        $fromAddress = $this->settings->getWithFallback('mail', 'from_address', env('MAIL_FROM_ADDRESS'));

        config([
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => $host,
            'mail.mailers.smtp.port' => $port,
            'mail.mailers.smtp.encryption' => $encryption,
            'mail.mailers.smtp.username' => $username,
            'mail.mailers.smtp.password' => $password,
            'mail.default' => 'smtp',
            'mail.from.address' => $fromAddress,
            'mail.from.name' => $fromName,
        ]);

        return compact('host', 'port', 'username', 'encryption', 'fromName', 'fromAddress');
    }

    public function testMail(string $recipient, User $admin): string
    {
        $config = $this->applyMailConfiguration();

        if (! filled($config['host']) || ! filled($config['fromAddress'])) {
            throw new RuntimeException('E-Mail ist noch nicht vollstaendig konfiguriert.');
        }

        Mail::raw('Dies ist eine Testmail aus der Wein-App Administration.', function ($message) use ($recipient): void {
            $message->to($recipient)->subject('Wein-App Testmail');
        });

        $this->auditLog->log($admin, $admin, 'mail_test_sent', ['recipient' => $recipient]);

        return 'Testmail erfolgreich versendet.';
    }

    private function testOpenAi(array $config): array
    {
        if (! filled($config['api_key'])) {
            throw new RuntimeException('OpenAI ist noch nicht konfiguriert.');
        }

        $model = $config['text_model'];
        $response = $this->http
            ->withToken($config['api_key'])
            ->timeout($config['timeout'])
            ->retry($config['retries'], 200)
            ->get('https://api.openai.com/v1/models/'.$model)
            ->throw();

        return [
            'message' => 'OpenAI API erfolgreich verbunden.',
            'model' => Arr::get($response->json(), 'id', $model),
            'http_status' => $response->status(),
        ];
    }

    private function testWineProvider(array $config): array
    {
        if (! filled($config['endpoint'])) {
            throw new RuntimeException('Wine Provider Endpoint fehlt.');
        }

        $request = $this->http->timeout($config['timeout']);

        if (filled($config['api_key'])) {
            $request = $request->withHeaders(['Authorization' => 'Bearer '.$config['api_key']]);
        }

        $response = $request->get($config['endpoint'], array_filter([
            'region' => $config['region'],
            'healthcheck' => 1,
        ]))->throw();

        return [
            'message' => 'Wine Provider erfolgreich verbunden.',
            'http_status' => $response->status(),
        ];
    }

    private function testGoogleVision(array $config): array
    {
        if (! filled($config['credentials_json'])) {
            throw new RuntimeException('Google Vision Credentials fehlen.');
        }
        $apiKey = $this->googleVisionApiKey($config['credentials_json']);

        if (! filled($apiKey)) {
            throw new RuntimeException('Google Vision benoetigt derzeit einen API-Key oder JSON mit api_key.');
        }

        if (! filled($config['endpoint'])) {
            throw new RuntimeException('Google Vision Endpoint fehlt.');
        }

        $response = $this->http
            ->timeout($this->runtimeTimeout($config))
            ->acceptJson()
            ->post($config['endpoint'].'?key='.urlencode($apiKey), [
                'requests' => [],
                'validateOnly' => true,
            ])
            ->throw();

        return [
            'message' => 'Google Vision Verbindung erfolgreich geprueft.',
            'http_status' => $response->status(),
        ];
    }

    private function recordFailure(string $slug, User $admin, string $message, ?int $httpStatus = null): array
    {
        $group = 'integration.'.$slug;
        $this->settings->setMany($group, [
            'last_test_status' => ['value' => 'error'],
            'last_test_at' => ['value' => now()->toIso8601String()],
            'last_test_message' => ['value' => $message],
            'last_error' => ['value' => $message],
        ]);

        $this->apiLogs->log($slug, 'connection_test', 'error', null, $httpStatus, $message, $admin);
        $this->auditLog->log($admin, $admin, 'integration_tested', ['provider' => $slug, 'status' => 'error']);

        return [
            'ok' => false,
            'message' => $message,
            'response_time_ms' => null,
        ];
    }

    private function maskSecret(string $secret): string
    {
        $length = mb_strlen($secret);

        if ($length <= 6) {
            return str_repeat('•', max($length - 1, 1)).mb_substr($secret, -1);
        }

        return mb_substr($secret, 0, 7).str_repeat('•', max($length - 11, 4)).mb_substr($secret, -4);
    }

    private function sortedProviders(array $slugs): array
    {
        return collect($slugs)
            ->map(fn (string $slug) => ['slug' => $slug, 'config' => $this->config($slug)])
            ->filter(fn (array $item) => (bool) ($item['config']['enabled'] ?? false))
            ->sortBy(fn (array $item) => (int) ($item['config']['priority'] ?? 99))
            ->flatMap(function (array $item): array {
                $fallback = $item['config']['fallback_provider'] ?? null;

                return array_values(array_filter([$item['slug'], $fallback]));
            })
            ->unique()
            ->values()
            ->all();
    }

    private function recognizeWithOpenAi(UploadedFile $file, ?User $user): array
    {
        $config = $this->config('openai');

        if (! $config['enabled'] || ! filled($config['api_key'])) {
            throw new RuntimeException('OpenAI ist nicht aktiv konfiguriert.');
        }

        $startedAt = microtime(true);
        $payload = [
            'model' => $config['image_model'],
            'input' => [[
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'input_text',
                        'text' => 'Analysiere dieses Weinetikett. Antworte ausschliesslich als JSON mit den Feldern producer, wine_name, vintage, country, region, appellation, wine_type, grape_varieties, visible_text, confidence. Verwende null fuer unbekannte Werte und ein Array fuer grape_varieties.',
                    ],
                    [
                        'type' => 'input_image',
                        'image_url' => 'data:'.$file->getMimeType().';base64,'.base64_encode(file_get_contents($file->getRealPath())),
                    ],
                ],
            ]],
        ];

        $response = $this->http
            ->withToken($config['api_key'])
            ->timeout($this->runtimeTimeout($config))
            ->retry($this->runtimeRetries($config), 200)
            ->post('https://api.openai.com/v1/responses', $payload)
            ->throw();

        $json = $response->json();
        $content = trim((string) (Arr::get($json, 'output_text')
            ?? Arr::get($json, 'output.0.content.0.text')
            ?? Arr::get($json, 'choices.0.message.content')
            ?? ''));

        if ($content === '') {
            throw new RuntimeException('OpenAI lieferte keine verwertbare Antwort.');
        }

        $recognized = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $elapsed = (int) round((microtime(true) - $startedAt) * 1000);

        $this->apiLogs->log('openai', 'recognize_label', 'success', $elapsed, $response->status(), 'Weinetikett erkannt.', $user);

        return [
            'status' => 'recognized',
            'provider' => 'openai',
            'producer' => $recognized['producer'] ?? null,
            'wine_name' => $recognized['wine_name'] ?? null,
            'vintage' => $recognized['vintage'] ?? null,
            'country' => $recognized['country'] ?? null,
            'region' => $recognized['region'] ?? null,
            'appellation' => $recognized['appellation'] ?? null,
            'wine_type' => $recognized['wine_type'] ?? null,
            'grape_varieties' => is_array($recognized['grape_varieties'] ?? null) ? $recognized['grape_varieties'] : [],
            'visible_text' => $recognized['visible_text'] ?? null,
            'confidence' => (float) ($recognized['confidence'] ?? 0),
            'message' => 'Weinetikett automatisch erkannt.',
        ];
    }

    private function recognizeWithGoogleVision(UploadedFile $file, ?User $user): array
    {
        $config = $this->config('google_vision');

        if (! $config['enabled']) {
            throw new RuntimeException('Google Vision ist nicht aktiv konfiguriert.');
        }

        $apiKey = $this->googleVisionApiKey($config['credentials_json'] ?? null);

        if (! filled($apiKey)) {
            throw new RuntimeException('Google Vision benoetigt derzeit einen API-Key oder JSON mit api_key.');
        }

        $features = [];

        if ($config['ocr_enabled']) {
            $features[] = ['type' => 'TEXT_DETECTION'];
        }

        if ($config['web_detection_enabled']) {
            $features[] = ['type' => 'WEB_DETECTION'];
        }

        if ($features === []) {
            throw new RuntimeException('Google Vision ist aktiv, aber ohne OCR/Web Detection konfiguriert.');
        }

        $startedAt = microtime(true);
        $response = $this->http
            ->timeout($this->runtimeTimeout($config))
            ->acceptJson()
            ->post($config['endpoint'].'?key='.urlencode($apiKey), [
                'requests' => [[
                    'image' => ['content' => base64_encode(file_get_contents($file->getRealPath()))],
                    'features' => $features,
                ]],
            ])
            ->throw();

        $payload = $response->json();
        $ocrText = trim((string) (Arr::get($payload, 'responses.0.fullTextAnnotation.text')
            ?? Arr::get($payload, 'responses.0.textAnnotations.0.description')
            ?? ''));

        if ($ocrText === '') {
            throw new RuntimeException('Google Vision lieferte keinen OCR-Text.');
        }

        $elapsed = (int) round((microtime(true) - $startedAt) * 1000);
        $this->apiLogs->log('google_vision', 'recognize_label', 'success', $elapsed, $response->status(), 'OCR-Text erkannt.', $user);

        return $this->parseWineFromOcrText($ocrText);
    }

    private function enrichWithOpenAi(array $recognized, ?User $user): array
    {
        $config = $this->config('openai');

        if (! $config['enabled'] || ! filled($config['api_key'])) {
            throw new RuntimeException('OpenAI ist nicht aktiv konfiguriert.');
        }

        if (! filled($recognized['producer'] ?? null) || ! filled($recognized['wine_name'] ?? null)) {
            throw new RuntimeException('Zu wenige Daten fuer eine externe Anreicherung.');
        }

        $cacheKey = 'openai.enrich.'.md5(json_encode(Arr::only($recognized, ['producer', 'wine_name', 'vintage', 'region', 'country'])));
        $cacheMinutes = max(1, (int) $this->settings->get('cache', 'provider_minutes', 120));

        return Cache::remember($cacheKey, now()->addMinutes($cacheMinutes), function () use ($config, $recognized, $user) {
            $startedAt = microtime(true);
            $response = $this->http
                ->withToken($config['api_key'])
                ->timeout($this->runtimeTimeout($config))
                ->retry($this->runtimeRetries($config), 200)
                ->post('https://api.openai.com/v1/responses', [
                    'model' => $config['structuring_model'],
                    'input' => [[
                        'role' => 'user',
                        'content' => [[
                            'type' => 'input_text',
                            'text' => 'Verifiziere diese Weindaten gegen deine verfuegbaren globalen Quellen. Antworte ausschliesslich als JSON. Liefere nur sicher erkennbare Werte. Erlaubte Felder: producer, wine_name, cuvee, vintage, country, region, appellation, wine_type, grape_varieties, description, body, tannin, acidity, sweetness, oak, fruit_intensity, mineral, earthy, spicy, floral, pairing_suggestions, confidence, source_url, candidates. Unbekannte Werte muessen null sein. candidates darf nur bei mehreren plausiblen Identitaeten als Array strukturierter Kandidaten verwendet werden. Daten: '.json_encode($recognized, JSON_UNESCAPED_UNICODE),
                        ]],
                    ]],
                ])
                ->throw();

            $json = $response->json();
            $content = trim((string) (Arr::get($json, 'output_text')
                ?? Arr::get($json, 'output.0.content.0.text')
                ?? Arr::get($json, 'choices.0.message.content')
                ?? ''));

            if ($content === '') {
                throw new RuntimeException('OpenAI lieferte keine Enrichment-Daten.');
            }

            $enriched = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            $elapsed = (int) round((microtime(true) - $startedAt) * 1000);

            $this->apiLogs->log('openai', 'enrich_wine', 'success', $elapsed, $response->status(), 'Weindaten angereichert.', $user);

            $identity = collect(Arr::only($enriched, ['producer', 'wine_name', 'cuvee', 'vintage', 'country', 'region', 'appellation', 'wine_type', 'grape_varieties']))
                ->filter(fn ($value): bool => $value !== null && $value !== '')
                ->all();

            return array_merge($recognized, $identity, Arr::only($enriched, [
                'description', 'body', 'tannin', 'acidity', 'sweetness', 'oak', 'fruit_intensity', 'mineral', 'earthy', 'spicy', 'floral', 'pairing_suggestions', 'confidence', 'source_url', 'candidates',
            ]), [
                'source' => [
                    'source_type' => 'provider',
                    'source_name' => 'OpenAI',
                    'source_url' => $enriched['source_url'] ?? null,
                    'confidence' => (float) ($enriched['confidence'] ?? $recognized['confidence'] ?? 0),
                ],
                'enrichment_status' => 'success',
                'enrichment_message' => 'Weindaten wurden extern angereichert.',
            ]);
        });
    }

    private function enrichWithWineProvider(array $recognized, ?User $user): array
    {
        $config = $this->config('wine_provider');

        if (! $config['enabled'] || ! filled($config['endpoint'])) {
            throw new RuntimeException('Wine Provider ist nicht aktiv konfiguriert.');
        }

        if (! filled($recognized['producer'] ?? null) || ! filled($recognized['wine_name'] ?? null)) {
            throw new RuntimeException('Zu wenige Daten fuer den Wine Provider.');
        }

        $cacheKey = 'wine-provider.enrich.'.md5(json_encode(Arr::only($recognized, ['producer', 'wine_name', 'vintage', 'region', 'country'])));
        $cacheMinutes = max(1, (int) ($config['cache_minutes'] ?? $this->settings->get('cache', 'provider_minutes', 120)));

        return Cache::remember($cacheKey, now()->addMinutes($cacheMinutes), function () use ($config, $recognized, $user) {
            $startedAt = microtime(true);
            $request = $this->http->timeout($this->runtimeTimeout($config))->acceptJson();

            if (filled($config['api_key'])) {
                $request = $request->withToken($config['api_key']);
            }

            $response = $request->get($config['endpoint'], array_filter([
                'producer' => $recognized['producer'] ?? null,
                'wine_name' => $recognized['wine_name'] ?? null,
                'vintage' => $recognized['vintage'] ?? null,
                'region' => $recognized['region'] ?? null,
                'market' => $config['region'] ?? null,
            ]))->throw();

            $payload = $response->json();

            if (! is_array($payload)) {
                throw new RuntimeException('Wine Provider lieferte kein gueltiges JSON.');
            }

            $elapsed = (int) round((microtime(true) - $startedAt) * 1000);
            $this->apiLogs->log('wine_provider', 'enrich_wine', 'success', $elapsed, $response->status(), 'Weindaten vom Wine Provider geladen.', $user);

            $identity = collect(Arr::only($payload, ['producer', 'wine_name', 'cuvee', 'vintage', 'country', 'region', 'appellation', 'wine_type', 'grape_varieties']))
                ->filter(fn ($value): bool => $value !== null && $value !== '')
                ->all();

            return array_merge($recognized, $identity, Arr::only($payload, [
                'description', 'body', 'tannin', 'acidity', 'sweetness', 'oak', 'fruit_intensity', 'mineral', 'earthy', 'spicy', 'floral', 'pairing_suggestions', 'confidence', 'source_url', 'candidates',
            ]), [
                'source' => [
                    'source_type' => 'provider',
                    'source_name' => 'Wine Provider',
                    'source_url' => $payload['source_url'] ?? null,
                    'confidence' => (float) ($payload['confidence'] ?? $recognized['confidence'] ?? 0),
                ],
                'enrichment_status' => 'success',
                'enrichment_message' => 'Weindaten wurden ueber den Wine Provider angereichert.',
            ]);
        });
    }

    private function httpFailureMessage(RequestException $exception): string
    {
        return match ($exception->response?->status()) {
            401 => 'API-Key wurde abgelehnt.',
            403 => 'Zugriff wurde vom Provider verweigert.',
            404 => 'Endpoint oder Modell nicht verfuegbar.',
            429 => 'Rate Limit erreicht.',
            500, 503 => 'Provider derzeit nicht verfuegbar.',
            default => 'Verbindung fehlgeschlagen.',
        };
    }

    private function googleVisionApiKey(?string $credentials): ?string
    {
        if (! filled($credentials)) {
            return null;
        }

        $trimmed = trim($credentials);

        if ($trimmed === '') {
            return null;
        }

        if (str_starts_with($trimmed, '{')) {
            $decoded = json_decode($trimmed, true);

            return is_array($decoded) ? ($decoded['api_key'] ?? null) : null;
        }

        return $trimmed;
    }

    private function parseWineFromOcrText(string $ocrText): array
    {
        $lines = collect(preg_split('/\R+/', $ocrText) ?: [])
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->values();

        preg_match('/(19|20)\d{2}/', $ocrText, $vintageMatch);
        $vintage = $vintageMatch[0] ?? null;
        $producer = $lines->get(0);
        $wineName = trim(str_replace($vintage ?? '', '', $lines->slice(1)->implode(' ')));
        $lowerText = mb_strtolower($ocrText);

        return [
            'status' => 'recognized',
            'provider' => 'google_vision',
            'producer' => $producer !== '' ? $producer : null,
            'wine_name' => $wineName !== '' ? $wineName : null,
            'vintage' => $vintage,
            'country' => null,
            'region' => null,
            'appellation' => null,
            'wine_type' => str_contains($lowerText, 'rose') ? 'Rose' : (str_contains($lowerText, 'blanc') || str_contains($lowerText, 'white') ? 'Weisswein' : (str_contains($lowerText, 'champagne') || str_contains($lowerText, 'sparkling') ? 'Schaumwein' : 'Rotwein')),
            'grape_varieties' => [],
            'visible_text' => $ocrText,
            'confidence' => 0.45,
            'message' => 'OCR-Text ueber Google Vision erkannt. Bitte Angaben vor dem Speichern pruefen.',
        ];
    }

    private function runtimeTimeout(array $config): int
    {
        return max(1, (int) $this->settings->get('scan', 'provider_timeout', $config['timeout'] ?? 10));
    }

    private function runtimeRetries(array $config): int
    {
        return max(0, (int) $this->settings->get('scan', 'retry_attempts', $config['retries'] ?? 1));
    }
}
