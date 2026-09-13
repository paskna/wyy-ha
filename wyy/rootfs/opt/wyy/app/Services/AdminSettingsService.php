<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class AdminSettingsService
{
    public function __construct(private readonly SettingsService $settings) {}

    public function valuesFor(string $section): array
    {
        return match ($section) {
            'general' => [
                'app_name' => $this->settings->getWithFallback('general', 'app_name', config('app.name'), 'Weinassistent'),
                'app_subtitle' => $this->settings->get('general', 'app_subtitle', 'Persoenlicher Wein-Kompass fuer Alltag, Laden und Restaurant.'),
                'language' => $this->settings->get('general', 'language', 'de'),
                'timezone' => $this->settings->getWithFallback('general', 'timezone', config('app.timezone'), 'Europe/Zurich'),
                'date_format' => $this->settings->get('general', 'date_format', 'd.m.Y'),
                'per_page' => (int) $this->settings->get('general', 'per_page', 12),
                'default_wine_view' => $this->settings->get('general', 'default_wine_view', 'cards'),
            ],
            'scan' => [
                'max_image_size' => (int) $this->settings->get('scan', 'max_image_size', 8192),
                'jpeg_quality' => (int) $this->settings->get('scan', 'jpeg_quality', 82),
                'max_image_dimension' => (int) $this->settings->get('scan', 'max_image_dimension', 2200),
                'use_ocr' => (bool) $this->settings->get('scan', 'use_ocr', true),
                'external_research' => (bool) $this->settings->get('scan', 'external_research', true),
                'auto_enrichment' => (bool) $this->settings->get('scan', 'auto_enrichment', true),
                'provider_timeout' => (int) $this->settings->get('scan', 'provider_timeout', 10),
                'retry_attempts' => (int) $this->settings->get('scan', 'retry_attempts', 1),
            ],
            'matching' => [
                'exact_threshold' => (float) $this->settings->getWithFallback('matching', 'exact_threshold', config('wine.matching.exact_threshold'), 0.88),
                'candidate_threshold' => (float) $this->settings->getWithFallback('matching', 'candidate_threshold', config('wine.matching.candidate_threshold'), 0.72),
                'producer_weight' => (float) $this->settings->getWithFallback('matching', 'producer_weight', config('wine.matching.weights.producer'), 0.30),
                'wine_name_weight' => (float) $this->settings->getWithFallback('matching', 'wine_name_weight', config('wine.matching.weights.wine_name'), 0.30),
                'vintage_weight' => (float) $this->settings->getWithFallback('matching', 'vintage_weight', config('wine.matching.weights.vintage'), 0.15),
                'region_weight' => (float) $this->settings->getWithFallback('matching', 'region_weight', config('wine.matching.weights.region'), 0.10),
                'visual_weight' => (float) $this->settings->getWithFallback('matching', 'visual_weight', config('wine.matching.weights.visual'), 0.10),
                'other_weight' => (float) $this->settings->getWithFallback('matching', 'other_weight', config('wine.matching.weights.other'), 0.05),
            ],
            'taste_profile' => [
                'first_signal_at' => (int) $this->settings->getWithFallback('taste_profile', 'first_signal_at', config('wine.taste_profile.first_signal_at'), 3),
                'recommendation_at' => (int) $this->settings->getWithFallback('taste_profile', 'recommendation_at', config('wine.taste_profile.recommendation_at'), 5),
                'mature_profile_at' => (int) $this->settings->getWithFallback('taste_profile', 'mature_profile_at', config('wine.taste_profile.mature_profile_at'), 10),
                'top_weight' => (float) $this->settings->get('taste_profile', 'top_weight', 1.0),
                'average_weight' => (float) $this->settings->get('taste_profile', 'average_weight', 0.25),
                'unsuitable_weight' => (float) $this->settings->get('taste_profile', 'unsuitable_weight', -1.0),
                'feature_weight' => (float) $this->settings->get('taste_profile', 'feature_weight', 0.45),
                'grape_weight' => (float) $this->settings->get('taste_profile', 'grape_weight', 0.20),
                'region_weight' => (float) $this->settings->get('taste_profile', 'region_weight', 0.10),
                'type_weight' => (float) $this->settings->get('taste_profile', 'type_weight', 0.10),
                'top_wine_similarity_weight' => (float) $this->settings->get('taste_profile', 'top_wine_similarity_weight', 0.15),
            ],
            'image' => [
                'max_upload_size' => (int) $this->settings->get('image', 'max_upload_size', 8192),
                'optimized_width' => (int) $this->settings->get('image', 'optimized_width', 1800),
                'jpeg_quality' => (int) $this->settings->get('image', 'jpeg_quality', 82),
                'remove_exif' => true,
            ],
            'cache' => [
                'wine_data_minutes' => (int) $this->settings->get('cache', 'wine_data_minutes', 1440),
                'web_research_minutes' => (int) $this->settings->get('cache', 'web_research_minutes', 720),
                'provider_minutes' => (int) $this->settings->get('cache', 'provider_minutes', 120),
            ],
            'mail' => [
                'host' => $this->settings->getWithFallback('mail', 'host', env('MAIL_HOST')),
                'port' => (int) $this->settings->getWithFallback('mail', 'port', env('MAIL_PORT'), 587),
                'encryption' => $this->settings->getWithFallback('mail', 'encryption', env('MAIL_ENCRYPTION'), 'tls'),
                'username' => $this->settings->getWithFallback('mail', 'username', env('MAIL_USERNAME')),
                'password' => $this->settings->getWithFallback('mail', 'password', env('MAIL_PASSWORD')),
                'from_name' => $this->settings->getWithFallback('mail', 'from_name', env('MAIL_FROM_NAME'), config('app.name')),
                'from_address' => $this->settings->getWithFallback('mail', 'from_address', env('MAIL_FROM_ADDRESS')),
                'test_recipient' => $this->settings->get('mail', 'test_recipient'),
            ],
            'security' => [
                'max_login_attempts' => (int) $this->settings->get('security', 'max_login_attempts', 5),
                'lockout_minutes' => (int) $this->settings->get('security', 'lockout_minutes', 1),
                'session_timeout' => (int) $this->settings->get('security', 'session_timeout', config('session.lifetime')),
                'password_min_length' => (int) $this->settings->get('security', 'password_min_length', 10),
                'force_password_change' => (bool) $this->settings->get('security', 'force_password_change', false),
                'persistent_login_enabled' => (bool) $this->settings->get('security', 'persistent_login_enabled', true),
                'remember_default' => (bool) $this->settings->get('security', 'remember_default', true),
                'remember_days' => (int) $this->settings->get('security', 'remember_days', 180),
                'invalidate_sessions_on_password_change' => (bool) $this->settings->get('security', 'invalidate_sessions_on_password_change', true),
            ],
            default => throw ValidationException::withMessages(['section' => 'Unbekannter Einstellungsbereich.']),
        };
    }

    public function store(string $section, array $data): void
    {
        $entries = [];

        foreach ($data as $key => $value) {
            $entries[$key] = [
                'value' => $value,
                'type' => is_bool($value) ? 'bool' : (is_int($value) ? 'int' : (is_float($value) ? 'float' : 'string')),
                'encrypted' => in_array($key, ['password'], true),
            ];
        }

        $this->settings->setMany($section, $entries);
    }

    public function clearCaches(string $scope): string
    {
        if ($scope === 'settings') {
            Cache::flush();

            return 'Cache erfolgreich geleert.';
        }

        if ($scope === 'application') {
            Artisan::call('cache:clear');

            return 'Anwendungscache erfolgreich geleert.';
        }

        throw ValidationException::withMessages(['scope' => 'Unbekannter Cache-Bereich.']);
    }
}
