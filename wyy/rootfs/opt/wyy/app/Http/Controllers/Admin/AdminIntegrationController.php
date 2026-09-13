<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\IntegrationManager;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminIntegrationController extends Controller
{
    public function __construct(private readonly IntegrationManager $integrations) {}

    public function index(): View
    {
        return view('admin.integrations.index', [
            'providers' => $this->integrations->overview(),
        ]);
    }

    public function edit(string $provider): View
    {
        return view('admin.integrations.edit', [
            'provider' => $this->integrations->provider($provider),
            'providers' => $this->integrations->providers(),
        ]);
    }

    public function update(Request $request, string $provider): RedirectResponse
    {
        $data = match ($provider) {
            'openai' => $request->validate([
                'enabled' => ['nullable', 'boolean'],
                'api_key' => ['nullable', 'string', 'max:500'],
                'image_model' => ['required', 'string', 'max:255'],
                'text_model' => ['required', 'string', 'max:255'],
                'structuring_model' => ['required', 'string', 'max:255'],
                'timeout' => ['required', 'integer', 'min:1', 'max:60'],
                'retries' => ['required', 'integer', 'min:0', 'max:5'],
                'priority' => ['nullable', 'integer', 'min:1', 'max:9'],
                'fallback_provider' => ['nullable', Rule::in(['', 'google_vision', 'wine_provider'])],
            ]),
            'wine_provider' => $request->validate([
                'enabled' => ['nullable', 'boolean'],
                'api_key' => ['nullable', 'string', 'max:500'],
                'endpoint' => ['required', 'url', 'max:500'],
                'region' => ['nullable', 'string', 'max:100'],
                'timeout' => ['required', 'integer', 'min:1', 'max:60'],
                'cache_minutes' => ['required', 'integer', 'min:0', 'max:10080'],
                'priority' => ['nullable', 'integer', 'min:1', 'max:9'],
                'fallback_provider' => ['nullable', Rule::in(['', 'openai'])],
            ]),
            'google_vision' => $request->validate([
                'enabled' => ['nullable', 'boolean'],
                'credentials_json' => ['nullable', 'string'],
                'endpoint' => ['required', 'url', 'max:500'],
                'ocr_enabled' => ['nullable', 'boolean'],
                'web_detection_enabled' => ['nullable', 'boolean'],
                'timeout' => ['required', 'integer', 'min:1', 'max:60'],
                'priority' => ['nullable', 'integer', 'min:1', 'max:9'],
                'fallback_provider' => ['nullable', Rule::in(['', 'openai'])],
            ]),
            default => abort(404),
        };

        $normalized = $this->normalizeCheckboxes($provider, $data, $request);
        $this->integrations->save($provider, $normalized, $request->user());

        return redirect()->route('admin.integrations.edit', $provider)->with('status', 'Einstellungen gespeichert. Noch nicht getestet.');
    }

    public function test(Request $request, string $provider): RedirectResponse
    {
        $result = $this->integrations->test($provider, $request->user());

        return back()->with($result['ok'] ? 'status' : 'error', $result['message']);
    }

    public function destroySecret(Request $request, string $provider): RedirectResponse
    {
        $field = $provider === 'google_vision' ? 'credentials_json' : 'api_key';
        app(SettingsService::class)->forget('integration.'.$provider, $field);

        return back()->with('status', 'Geheimer Wert entfernt.');
    }

    private function normalizeCheckboxes(string $provider, array $data, Request $request): array
    {
        $data['enabled'] = $request->boolean('enabled');

        if ($provider === 'google_vision') {
            $data['ocr_enabled'] = $request->boolean('ocr_enabled');
            $data['web_detection_enabled'] = $request->boolean('web_detection_enabled');
        }

        return $data;
    }
}
