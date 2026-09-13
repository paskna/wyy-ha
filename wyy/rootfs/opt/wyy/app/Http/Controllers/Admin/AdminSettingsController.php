<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminSettingsService;
use App\Services\AuditLogService;
use App\Services\IntegrationManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminSettingsController extends Controller
{
    public function __construct(
        private readonly AdminSettingsService $settings,
        private readonly IntegrationManager $integrations,
        private readonly AuditLogService $auditLog,
    ) {}

    public function edit(string $section = 'general'): View
    {
        return view('admin.settings.edit', [
            'section' => $section,
            'sections' => $this->sections(),
            'values' => $this->settings->valuesFor($section),
        ]);
    }

    public function update(Request $request, string $section): RedirectResponse
    {
        $data = match ($section) {
            'general' => $request->validate([
                'app_name' => ['required', 'string', 'max:255'],
                'app_subtitle' => ['nullable', 'string', 'max:255'],
                'language' => ['required', 'string', 'max:10'],
                'timezone' => ['required', 'timezone'],
                'date_format' => ['required', 'string', 'max:30'],
                'per_page' => ['required', 'integer', 'min:5', 'max:100'],
                'default_wine_view' => ['required', 'in:cards,list'],
            ]),
            'scan' => $this->normalizeBooleans($request, $request->validate([
                'max_image_size' => ['required', 'integer', 'min:512', 'max:20000'],
                'jpeg_quality' => ['required', 'integer', 'min:40', 'max:100'],
                'max_image_dimension' => ['required', 'integer', 'min:500', 'max:6000'],
                'provider_timeout' => ['required', 'integer', 'min:1', 'max:60'],
                'retry_attempts' => ['required', 'integer', 'min:0', 'max:5'],
            ]), ['use_ocr', 'external_research', 'auto_enrichment']),
            'matching' => $this->validatedMatching($request),
            'taste_profile' => $this->validatedTasteProfile($request),
            'image' => $this->normalizeBooleans($request, $request->validate([
                'max_upload_size' => ['required', 'integer', 'min:512', 'max:20000'],
                'optimized_width' => ['required', 'integer', 'min:400', 'max:5000'],
                'jpeg_quality' => ['required', 'integer', 'min:40', 'max:100'],
            ]), []) + ['remove_exif' => true],
            'cache' => $request->validate([
                'wine_data_minutes' => ['required', 'integer', 'min:0', 'max:10080'],
                'web_research_minutes' => ['required', 'integer', 'min:0', 'max:10080'],
                'provider_minutes' => ['required', 'integer', 'min:0', 'max:10080'],
            ]),
            'mail' => $this->validatedMail($request),
            'security' => $this->normalizeBooleans($request, $request->validate([
                'max_login_attempts' => ['required', 'integer', 'min:3', 'max:20'],
                'lockout_minutes' => ['required', 'integer', 'min:1', 'max:120'],
                'session_timeout' => ['required', 'integer', 'min:5', 'max:1440'],
                'password_min_length' => ['required', 'integer', 'min:8', 'max:64'],
                'remember_days' => ['required', 'integer', 'min:0', 'max:365'],
            ]), ['force_password_change', 'persistent_login_enabled', 'remember_default', 'invalidate_sessions_on_password_change']),
            default => abort(404),
        };

        $this->settings->store($section, $data);
        $this->auditLog->log($request->user(), $request->user(), 'system_setting_changed', ['section' => $section]);

        return back()->with('status', 'Einstellungen gespeichert.');
    }

    public function clearCache(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'scope' => ['required', 'in:settings,application'],
        ]);

        $message = $this->settings->clearCaches($data['scope']);
        $this->auditLog->log($request->user(), $request->user(), 'admin_cache_cleared', ['scope' => $data['scope']]);

        return back()->with('status', $message);
    }

    public function testMail(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'recipient' => ['required', 'email'],
        ]);

        try {
            $message = $this->integrations->testMail($data['recipient'], $request->user());

            return back()->with('status', $message);
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    private function sections(): array
    {
        return [
            'general' => 'Allgemein',
            'scan' => 'Scan',
            'matching' => 'Matching',
            'taste_profile' => 'Geschmacksprofil',
            'image' => 'Bilder',
            'cache' => 'Cache',
            'mail' => 'E-Mail',
            'security' => 'Sicherheit',
        ];
    }

    private function validatedMatching(Request $request): array
    {
        $data = $request->validate([
            'exact_threshold' => ['required', 'numeric', 'between:0,1'],
            'candidate_threshold' => ['required', 'numeric', 'between:0,1'],
            'producer_weight' => ['required', 'numeric', 'between:0,1'],
            'wine_name_weight' => ['required', 'numeric', 'between:0,1'],
            'vintage_weight' => ['required', 'numeric', 'between:0,1'],
            'region_weight' => ['required', 'numeric', 'between:0,1'],
            'visual_weight' => ['required', 'numeric', 'between:0,1'],
            'other_weight' => ['required', 'numeric', 'between:0,1'],
        ]);

        $sum = collect($data)->only(['producer_weight', 'wine_name_weight', 'vintage_weight', 'region_weight', 'visual_weight', 'other_weight'])->sum();

        if (abs($sum - 1.0) > 0.001) {
            throw ValidationException::withMessages(['producer_weight' => 'Die Summe der Matching-Gewichtungen muss 1.0 ergeben.']);
        }

        return array_map(fn ($value) => (float) $value, $data);
    }

    private function validatedTasteProfile(Request $request): array
    {
        $data = $request->validate([
            'first_signal_at' => ['required', 'integer', 'min:1', 'max:100'],
            'recommendation_at' => ['required', 'integer', 'min:1', 'max:100'],
            'mature_profile_at' => ['required', 'integer', 'min:1', 'max:100'],
            'top_weight' => ['required', 'numeric', 'between:0,2'],
            'average_weight' => ['required', 'numeric', 'between:0,1'],
            'unsuitable_weight' => ['required', 'numeric', 'between:-2,0'],
            'feature_weight' => ['required', 'numeric', 'between:0,1'],
            'grape_weight' => ['required', 'numeric', 'between:0,1'],
            'region_weight' => ['required', 'numeric', 'between:0,1'],
            'type_weight' => ['required', 'numeric', 'between:0,1'],
            'top_wine_similarity_weight' => ['required', 'numeric', 'between:0,1'],
        ]);

        if ($data['recommendation_at'] < $data['first_signal_at'] || $data['mature_profile_at'] < $data['recommendation_at']) {
            throw ValidationException::withMessages(['recommendation_at' => 'Die Mindestanzahlen muessen aufsteigend sein.']);
        }

        $sum = collect($data)->only(['feature_weight', 'grape_weight', 'region_weight', 'type_weight', 'top_wine_similarity_weight'])->sum();

        if (abs($sum - 1.0) > 0.001) {
            throw ValidationException::withMessages(['feature_weight' => 'Die Summe der Profil-Gewichtungen muss 1.0 ergeben.']);
        }

        return array_map(fn ($value) => is_numeric($value) && str_contains((string) $value, '.') ? (float) $value : (int) $value, $data);
    }

    private function validatedMail(Request $request): array
    {
        $data = $request->validate([
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'encryption' => ['nullable', 'in:tls,ssl'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string'],
            'from_name' => ['nullable', 'string', 'max:255'],
            'from_address' => ['nullable', 'email'],
            'test_recipient' => ['nullable', 'email'],
        ]);

        if (($data['password'] ?? null) === '__keep__') {
            unset($data['password']);
        }

        if (($data['password'] ?? null) === null || ($data['password'] ?? null) === '') {
            unset($data['password']);
        }

        return $data;
    }

    private function normalizeBooleans(Request $request, array $data, array $keys): array
    {
        foreach ($keys as $key) {
            $data[$key] = $request->boolean($key);
        }

        return $data;
    }
}
