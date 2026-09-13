<?php

namespace App\Http\Controllers;

use App\Services\BrandingService;
use App\Services\DeploymentMode;
use App\Services\InstallationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PwaController extends Controller
{
    public function __construct(
        private readonly InstallationService $installation,
        private readonly BrandingService $branding,
        private readonly DeploymentMode $deployment,
    ) {}

    public function manifest(Request $request): JsonResponse
    {
        $url = $this->installation->detectedUrl($request);
        $scope = ($url['base_path'] ?: '').'/';
        $branding = $this->branding->viewData();

        return response()->json([
            'name' => $branding['pwa']['name'] ?? config('app.name', 'Weinassistent'),
            'short_name' => $branding['pwa']['short_name'] ?? 'Wein',
            'start_url' => $this->installation->isInstalled() ? $scope : (($url['base_path'] ?: '').'/install'),
            'scope' => $scope,
            'display' => 'standalone',
            'background_color' => $branding['pwa']['background_color'] ?? '#120d0d',
            'theme_color' => $branding['pwa']['theme_color'] ?? '#201615',
            'lang' => 'de',
            'description' => $branding['pwa']['description'] ?? ($branding['general']['description'] ?? null),
            'icons' => [
                [
                    'src' => route('branding.icon-192', ['v' => $branding['version'] ?? 'default']),
                    'sizes' => '192x192',
                    'type' => 'image/png',
                ],
                [
                    'src' => route('branding.icon-512', ['v' => $branding['version'] ?? 'default']),
                    'sizes' => '512x512',
                    'type' => 'image/png',
                ],
                [
                    'src' => route('branding.asset', ['slot' => 'pwa_maskable_icon', 'v' => $branding['version'] ?? 'default']),
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
            ],
        ])->header('Content-Type', 'application/manifest+json');
    }

    public function worker(Request $request): Response
    {
        if ($this->deployment->isIngressRequest($request)) {
            return response("self.addEventListener('install', () => self.skipWaiting()); self.addEventListener('activate', event => event.waitUntil(self.registration.unregister()));", 200, ['Content-Type' => 'application/javascript', 'Cache-Control' => 'no-store']);
        }

        $url = $this->installation->detectedUrl($request);
        $basePath = $url['base_path'];
        $branding = $this->branding->viewData();
        $cacheName = 'weinassistent-'.substr(sha1($basePath.'|'.config('app.url', '').'|'.($branding['version'] ?? 'default')), 0, 12).'-v1';
        $manifestUrl = route('pwa.manifest');
        $faviconUrl = route('branding.favicon', ['v' => $branding['version'] ?? 'default']);
        $scope = ($basePath ?: '').'/';
        $script = <<<JS
const CACHE_NAME = '{$cacheName}';
const STATIC_URLS = ['{$manifestUrl}', '{$faviconUrl}'];
const STATIC_PATH_PATTERNS = [/\/build\//, /\/icon-192\.png$/, /\/icon-512\.png$/, /\/apple-touch-icon\.png$/, /\/favicon\.ico$/, /\/branding\/assets\//];
const OFFLINE_HTML = `<!DOCTYPE html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Offline</title></head><body style="font-family:sans-serif;background:#120d0d;color:#f7f0e6;padding:24px"><h1>Offline</h1><p>Diese Ansicht ist ohne Netzwerk gerade nicht verfuegbar. Bitte verbinde dich erneut und versuche es noch einmal.</p></body></html>`;

self.addEventListener('install', event => {
    event.waitUntil(caches.open(CACHE_NAME).then(cache => cache.addAll(STATIC_URLS)));
    self.skipWaiting();
});

self.addEventListener('activate', event => {
    event.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(key => key !== CACHE_NAME).map(key => caches.delete(key)))));
    self.clients.claim();
});

self.addEventListener('fetch', event => {
    if (event.request.method !== 'GET') {
        return;
    }

    const request = event.request;
    const url = new URL(request.url);

    if (!url.pathname.startsWith('{$scope}')) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => new Response(OFFLINE_HTML, {
            headers: {'Content-Type': 'text/html; charset=utf-8'},
            status: 503,
        })));

        return;
    }

    if (url.origin !== self.location.origin) {
        return;
    }

    const isStaticAsset = STATIC_URLS.includes(url.href) || STATIC_PATH_PATTERNS.some(pattern => pattern.test(url.pathname));

    if (!isStaticAsset) {
        event.respondWith(fetch(request));
        return;
    }

    event.respondWith(caches.match(request).then(cached => cached || fetch(request).then(response => {
        if (!response.ok) {
            return response;
        }

        const clone = response.clone();
        caches.open(CACHE_NAME).then(cache => cache.put(request, clone));

        return response;
    })));
});
JS;

        return response($script, 200, ['Content-Type' => 'application/javascript']);
    }
}
