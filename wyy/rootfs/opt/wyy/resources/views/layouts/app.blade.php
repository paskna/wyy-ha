<!DOCTYPE html>
<html lang="de">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="{{ $branding['pwa']['theme_color'] ?? '#201615' }}">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="app-base-path" content="{{ request()->getBaseUrl() }}">
        @if (!app(\App\Services\DeploymentMode::class)->isIngressRequest(request()))
            <meta name="app-sw-url" content="{{ route('pwa.worker') }}">
        @endif
        <meta name="description" content="{{ $branding['general']['description'] ?? config('app.description', 'Persoenlicher Wein-Kompass fuer Alltag, Laden und Restaurant.') }}">
        @if (!app(\App\Services\DeploymentMode::class)->isIngressRequest(request()))
            <link rel="manifest" href="{{ route('pwa.manifest') }}">
        @endif
        <link rel="icon" type="image/png" sizes="32x32" href="{{ $branding['icons']['favicon'] ?? route('branding.favicon') }}">
        <link rel="apple-touch-icon" href="{{ $branding['icons']['apple_touch_icon'] ?? route('branding.apple-touch-icon') }}">
        <title>{{ isset($title) ? $title.' | '.config('app.name', 'Weinassistent') : config('app.name', 'Weinassistent') }}</title>
        <style>
            :root {
                @foreach (($branding['css_variables'] ?? []) as $name => $value)
                    {{ $name }}: {{ $value }};
                @endforeach
            }
        </style>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    @php($isAuthView = request()->routeIs('login') || request()->routeIs('password.*'))
    <body class="min-h-screen bg-[var(--color-ink)] text-[var(--color-cream)]">
        @if ($isAuthView)
            @php($loginBranding = $branding['login'] ?? [])
            @php($loginIcons = $branding['icons'] ?? [])
            @php($loginStyles = $branding['login_styles'] ?? [])
            @php($logoUrl = $loginIcons['login_logo'] ?? $loginIcons['main_logo'] ?? null)
            @php($backgroundUrl = !empty($loginBranding['background_enabled']) ? ($loginIcons['login_background'] ?? null) : null)
            @php($layout = $loginBranding['layout'] ?? 'centered')
            @php($widthClass = match($loginBranding['box_width'] ?? 'standard') { 'compact' => 'max-w-sm', 'wide' => 'max-w-xl', default => 'max-w-md' })
            @php($radiusClass = match($loginBranding['box_radius'] ?? 'rounded') { 'pill' => 'rounded-[32px]', 'soft' => 'rounded-[20px]', default => 'rounded-[28px]' })
            @php($shadowClass = match($loginBranding['box_shadow'] ?? 'soft') { 'medium' => 'shadow-2xl shadow-black/35', 'none' => '', default => 'shadow-xl shadow-black/20' })
            @php($alignClass = match($loginBranding['logo_alignment'] ?? 'center') { 'left' => 'items-start text-left', 'right' => 'items-end text-right', default => 'items-center text-center' })
            <div class="relative min-h-screen overflow-hidden" style="background-color: {{ $loginBranding['background_color'] ?? '#120d0d' }};">
                @if ($backgroundUrl)
                    <div class="absolute inset-0 bg-no-repeat" style="background-image: url('{{ $backgroundUrl }}'); background-size: {{ $loginBranding['background_fit'] === 'original' ? 'auto' : ($loginBranding['background_fit'] ?? 'cover') }}; background-position: {{ $loginBranding['background_position'] ?? 'center' }};"></div>
                @endif
                @if (!empty($loginBranding['overlay_enabled']))
                    <div class="absolute inset-0" style="background-color: {{ $loginStyles['overlay_rgba'] ?? 'rgba(18, 13, 13, 0.48)' }};"></div>
                @endif
                <div class="relative z-10 mx-auto flex min-h-screen w-full max-w-7xl items-center px-5 py-10 md:px-10 {{ str_starts_with($layout, 'split') ? 'justify-between gap-8' : ($layout === 'left' ? 'justify-start' : ($layout === 'right' ? 'justify-end' : 'justify-center')) }}">
                    @if (str_starts_with($layout, 'split'))
                        <div class="hidden flex-1 rounded-[32px] border border-white/10 bg-black/15 p-8 text-[var(--color-cream)] md:block">
                            <p class="text-xs uppercase tracking-[0.35em] text-[var(--color-gold)]">{{ $branding['general']['app_name'] ?? config('app.name') }}</p>
                            <h1 class="mt-4 font-serif text-5xl leading-tight">{{ $loginBranding['title'] ?? 'Willkommen beim Weinassistenten' }}</h1>
                            <p class="mt-5 max-w-xl text-base leading-7 text-[var(--color-mist)]">{{ $loginBranding['welcome_text'] ?? ($branding['general']['description'] ?? '') }}</p>
                        </div>
                    @endif
                    <div class="w-full {{ $widthClass }}">
                        <div class="border border-white/10 p-6 md:p-8 {{ $radiusClass }} {{ $shadowClass }}" style="background-color: {{ $loginStyles['box_rgba'] ?? 'rgba(32, 22, 21, 0.84)' }};">
                            <div class="mb-6 flex flex-col {{ $alignClass }}">
                                @if ($logoUrl)
                                    <img src="{{ $logoUrl }}" alt="{{ $branding['general']['app_name'] ?? config('app.name') }} Logo" class="mb-4 h-auto" style="max-width: {{ (int) ($loginBranding['logo_width'] ?? 180) }}px;">
                                @endif
                                <p class="text-xs uppercase tracking-[0.35em] text-[var(--color-gold)]">{{ $branding['general']['short_name'] ?? 'Wein' }}</p>
                                <h1 class="mt-3 font-serif text-4xl leading-tight">{{ $loginBranding['title'] ?? 'Willkommen beim Weinassistenten' }}</h1>
                                <p class="mt-3 text-sm leading-6 text-[var(--color-mist)]">{{ $loginBranding['subtitle'] ?? ($branding['general']['subtitle'] ?? '') }}</p>
                                @if (!empty($loginBranding['welcome_text']))
                                    <p class="mt-3 text-sm leading-6 text-[var(--color-mist)]">{{ $loginBranding['welcome_text'] }}</p>
                                @endif
                            </div>
                            @yield('content')
                        </div>
                    </div>
                </div>
            </div>
        @else
        <div class="mx-auto flex min-h-screen max-w-[430px] flex-col bg-[radial-gradient(circle_at_top,_rgba(111,46,42,0.35),_transparent_38%),linear-gradient(180deg,_rgba(39,26,24,0.96),_rgba(17,12,12,1))] md:max-w-5xl md:flex-row md:bg-[linear-gradient(135deg,_rgba(35,23,21,1),_rgba(17,12,12,1))]">
            <aside class="hidden w-80 border-r border-white/8 bg-black/12 p-8 md:flex md:flex-col">
                @if (!empty($branding['icons']['admin_logo']) && auth()->check() && auth()->user()->isAdmin())
                    <img src="{{ $branding['icons']['admin_logo'] }}" alt="{{ config('app.name', 'Weinassistent') }} Logo" class="h-auto max-w-[180px]">
                @elseif (!empty($branding['icons']['app_logo']))
                    <img src="{{ $branding['icons']['app_logo'] }}" alt="{{ config('app.name', 'Weinassistent') }} Logo" class="h-auto max-w-[180px]">
                @endif
                <p class="mt-4 text-xs uppercase tracking-[0.35em] text-[var(--color-gold)]">{{ config('app.name', 'Weinassistent') }}</p>
                <h1 class="mt-4 font-serif text-4xl leading-tight">{{ config('app.subtitle', 'Persoenlicher Wein-Kompass fuer Alltag, Laden und Restaurant.') }}</h1>
                <p class="mt-6 text-sm leading-6 text-[var(--color-mist)]">{{ $branding['general']['description'] ?? 'Persoenlicher Wein-Kompass fuer Alltag, Laden und Restaurant.' }}</p>
            </aside>
            <div class="flex min-h-screen flex-1 flex-col">
                <main class="flex-1 px-5 pb-28 pt-6 md:px-10 md:pb-10 md:pt-10">
                    @yield('page_header')
                    @if (session('status'))
                        <div class="mb-5 rounded-2xl border border-emerald-400/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">
                            {{ session('status') }}
                        </div>
                    @endif
                    @if (session('error'))
                        <div class="mb-5 rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
                            {{ session('error') }}
                        </div>
                    @endif
                    @if (session('warning'))
                        <div class="mb-5 rounded-2xl border border-amber-400/20 bg-amber-500/10 px-4 py-3 text-sm text-amber-100">
                            {{ session('warning') }}
                        </div>
                    @endif
                    @if ($errors->any())
                        <div class="mb-5 rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
                            <ul class="space-y-1">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    @yield('content')
                </main>
                @auth
                    <nav class="safe-bottom fixed inset-x-0 bottom-0 mx-auto flex max-w-[430px] items-center justify-between border-t border-white/8 px-4 py-3 backdrop-blur md:static md:max-w-none md:justify-center md:gap-5 md:border-0 md:bg-transparent md:px-10 md:py-0" style="background-color: rgba(17, 12, 12, 0.92);">
                        @php($items = [
                            ['label' => 'Start', 'route' => 'dashboard'],
                            ['label' => 'Sammlung', 'route' => 'collection.index'],
                            ['label' => 'Scannen', 'route' => 'scans.create', 'center' => true],
                            ['label' => 'Top-Weine', 'route' => 'top-wines.index'],
                            ['label' => 'Mehr', 'route' => 'more.index'],
                        ])
                        @foreach ($items as $item)
                            <a href="{{ route($item['route']) }}" @class([
                                'nav-pill',
                                'nav-pill-active' => request()->routeIs($item['route']),
                                'nav-pill-center' => $item['center'] ?? false,
                            ])>{{ $item['label'] }}</a>
                        @endforeach
                    </nav>
                @endauth
            </div>
        </div>
        @endif
    </body>
</html>
