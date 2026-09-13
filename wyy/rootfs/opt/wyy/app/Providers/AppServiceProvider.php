<?php

namespace App\Providers;

use App\Auth\PathAwareSessionGuard;
use App\Models\Scan;
use App\Models\WineImage;
use App\Models\WineVintage;
use App\Policies\ScanPolicy;
use App\Policies\WineImagePolicy;
use App\Policies\WineVintagePolicy;
use App\Services\AdminUserService;
use App\Services\ApiActivityLogService;
use App\Services\AuditLogService;
use App\Services\BrandingService;
use App\Services\DeploymentMode;
use App\Services\IntegrationManager;
use App\Services\ScanService;
use App\Services\SettingsService;
use App\Services\UserSessionService;
use App\Services\TasteProfileService;
use App\Services\WineEnrichmentService;
use App\Services\WineMatchingService;
use App\Services\WineNormalizationService;
use App\Services\WineRecognitionService;
use App\Services\WineSimilarityService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(WineNormalizationService::class);
        $this->app->singleton(WineMatchingService::class);
        $this->app->singleton(WineRecognitionService::class);
        $this->app->singleton(WineEnrichmentService::class);
        $this->app->singleton(WineSimilarityService::class);
        $this->app->singleton(TasteProfileService::class);
        $this->app->singleton(ScanService::class);
        $this->app->singleton(AuditLogService::class);
        $this->app->singleton(AdminUserService::class);
        $this->app->singleton(SettingsService::class);
        $this->app->singleton(ApiActivityLogService::class);
        $this->app->singleton(IntegrationManager::class);
        $this->app->singleton(BrandingService::class);
        $this->app->singleton(DeploymentMode::class);
        $this->app->singleton(UserSessionService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Keep utf8mb4 indexes compatible with older shared-hosting MySQL/MariaDB.
        Schema::defaultStringLength(191);

        Auth::extend('path-session', function ($app, string $name, array $config) {
            $guard = new PathAwareSessionGuard(
                $name,
                $app['auth']->createUserProvider($config['provider'] ?? null),
                $app['session.store'],
                rehashOnLogin: $app['config']->get('hashing.rehash_on_login', true),
                timeboxDuration: $app['config']->get('auth.timebox_duration', 200000),
                hashKey: $app['config']->get('app.key'),
            );

            $guard->setCookieJar($app['cookie']);
            $guard->setDispatcher($app['events']);
            $guard->setRequest($app->refresh('request', $guard, 'setRequest'));

            if (isset($config['remember'])) {
                $guard->setRememberDuration((int) $config['remember']);
            }

            return $guard;
        });

        Gate::policy(Scan::class, ScanPolicy::class);
        Gate::policy(WineVintage::class, WineVintagePolicy::class);
        Gate::policy(WineImage::class, WineImagePolicy::class);

        RateLimiter::for('login', function (Request $request): Limit {
            $settings = $this->app->make(SettingsService::class);
            $maxAttempts = max(3, (int) $settings->get('security', 'max_login_attempts', 5));
            $lockoutMinutes = max(1, (int) $settings->get('security', 'lockout_minutes', 1));

            return Limit::perMinutes(
                $lockoutMinutes,
                $maxAttempts,
            )->by(mb_strtolower($request->input('email', '')).'|'.$request->ip());
        });

        try {
            if (Schema::hasTable('settings')) {
                $settings = $this->app->make(SettingsService::class);
                config([
                    'app.name' => $settings->getWithFallback('general', 'app_name', config('app.name'), config('app.name')),
                    'app.subtitle' => $settings->get('general', 'app_subtitle', 'Persoenlicher Wein-Kompass fuer Alltag, Laden und Restaurant.'),
                    'app.timezone' => $settings->getWithFallback('general', 'timezone', config('app.timezone'), config('app.timezone')),
                    'app.locale' => $settings->getWithFallback('general', 'language', config('app.locale'), config('app.locale')),
                    'app.date_format' => $settings->get('general', 'date_format', config('app.date_format', 'd.m.Y')),
                    'session.lifetime' => (int) $settings->getWithFallback('security', 'session_timeout', config('session.lifetime'), config('session.lifetime')),
                    'auth.guards.web.remember' => max(0, (int) $settings->get('security', 'remember_days', 180) * 1440),
                ]);

                date_default_timezone_set((string) config('app.timezone'));
            }
        } catch (Throwable) {
            // Installer and first-run bootstrap must stay reachable without a working database.
        }

        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }
}
