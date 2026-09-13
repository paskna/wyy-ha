@extends('layouts.app', ['title' => 'Erscheinungsbild'])

@section('content')
    <div class="space-y-6">
        <div>
            <p class="text-xs uppercase tracking-[0.35em] text-[var(--color-gold)]">Administration</p>
            <h1 class="mt-2 font-serif text-3xl">Erscheinungsbild</h1>
        </div>

        @include('admin.partials.nav')

        <div class="flex gap-2 overflow-x-auto pb-2">
            @foreach ($sections as $key => $label)
                <a href="{{ route('admin.branding.edit', $key) }}" class="rounded-full border px-4 py-2 text-sm whitespace-nowrap {{ $section === $key ? 'border-[var(--color-gold)] bg-[var(--color-burgundy-soft)] text-[var(--color-cream)]' : 'border-white/10 bg-black/20 text-[var(--color-mist)]' }}">{{ $label }}</a>
            @endforeach
        </div>

        @if ($contrastWarnings)
            <div class="rounded-[24px] border border-amber-400/20 bg-amber-500/10 p-4 text-sm text-amber-100">
                @foreach ($contrastWarnings as $warning)
                    <p>{{ $warning }}</p>
                @endforeach
            </div>
        @endif

        @if ($section === 'preview')
            <div class="grid gap-5 xl:grid-cols-[1.05fr_0.95fr]">
                <section class="card-grid space-y-4">
                    <div class="section-head"><h2>Login-Vorschau</h2></div>
                    <div class="overflow-hidden rounded-[32px] border border-white/10">
                        <div class="relative min-h-[520px]" style="background-color: {{ $branding['login']['background_color'] }};">
                            @if (!empty($branding['icons']['login_background']) && !empty($branding['login']['background_enabled']))
                                <div class="absolute inset-0 bg-no-repeat" style="background-image:url('{{ $branding['icons']['login_background'] }}');background-size:{{ $branding['login']['background_fit'] === 'original' ? 'auto' : $branding['login']['background_fit'] }};background-position:{{ $branding['login']['background_position'] }};"></div>
                            @endif
                            @if (!empty($branding['login']['overlay_enabled']))
                                <div class="absolute inset-0" style="background-color: {{ $branding['login_styles']['overlay_rgba'] }};"></div>
                            @endif
                            <div class="relative z-10 flex min-h-[520px] items-center justify-center p-6">
                                <div class="w-full max-w-md rounded-[28px] border border-white/10 p-6" style="background-color: {{ $branding['login_styles']['box_rgba'] }};">
                                    <div class="text-center">
                                        @if (!empty($branding['icons']['login_logo']))
                                            <img src="{{ $branding['icons']['login_logo'] }}" alt="{{ $branding['general']['app_name'] }} Logo" class="mx-auto mb-4 h-auto" style="max-width: {{ $branding['login']['logo_width'] }}px;">
                                        @endif
                                        <p class="text-xs uppercase tracking-[0.35em] text-[var(--color-gold)]">{{ $branding['general']['short_name'] }}</p>
                                        <h3 class="mt-3 font-serif text-3xl">{{ $branding['login']['title'] }}</h3>
                                        <p class="mt-3 text-sm text-[var(--color-mist)]">{{ $branding['login']['subtitle'] }}</p>
                                    </div>
                                    <div class="mt-5 space-y-3">
                                        <div class="rounded-[20px] border border-white/10 bg-black/20 px-4 py-3 text-sm text-[var(--color-mist)]">E-Mail</div>
                                        <div class="rounded-[20px] border border-white/10 bg-black/20 px-4 py-3 text-sm text-[var(--color-mist)]">Passwort</div>
                                        <div class="btn-primary w-full justify-center">{{ $branding['login']['button_text'] }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
                <section class="card-grid space-y-4">
                    <div class="section-head"><h2>App-Vorschau</h2></div>
                    <div class="space-y-4">
                        <div class="rounded-[28px] border border-white/10 p-5" style="background-color: {{ $branding['colors']['surface'] }};">
                            <div class="flex items-center gap-4">
                                @if (!empty($branding['icons']['main_logo']))
                                    <img src="{{ $branding['icons']['main_logo'] }}" alt="{{ $branding['general']['app_name'] }} Logo" class="h-auto max-w-[120px]">
                                @endif
                                <div>
                                    <p class="text-xs uppercase tracking-[0.35em]" style="color: {{ $branding['colors']['accent'] }}">{{ $branding['general']['short_name'] }}</p>
                                    <h3 class="font-serif text-2xl">{{ $branding['general']['app_name'] }}</h3>
                                    <p class="text-sm text-[var(--color-mist)]">{{ $branding['general']['subtitle'] }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="rounded-[28px] border border-white/10 p-5" style="background-color: {{ $branding['colors']['surface'] }};">
                            <div class="mb-4 flex gap-2">
                                <span class="rounded-full px-4 py-2 text-xs" style="background-color: {{ $branding['colors']['navigation'] }}; color: {{ $branding['colors']['text_muted'] }};">Sammlung</span>
                                <span class="rounded-full px-4 py-2 text-xs" style="background-color: {{ $branding['colors']['navigation_active'] }}; color: {{ $branding['colors']['button_text'] }};">Top-Weine</span>
                            </div>
                            <div class="btn-primary">{{ $branding['login']['button_text'] }}</div>
                        </div>
                        <div class="rounded-[28px] border border-white/10 p-5" style="background-color: {{ $branding['colors']['surface'] }};">
                            <p class="text-sm text-[var(--color-mist)]">PWA</p>
                            <h3 class="mt-2 font-serif text-2xl">{{ $branding['pwa']['name'] }}</h3>
                            <p class="mt-2 text-sm text-[var(--color-mist)]">{{ $branding['pwa']['description'] }}</p>
                        </div>
                    </div>
                </section>
            </div>
        @else
            <div class="grid gap-5 xl:grid-cols-[1.1fr_0.9fr]">
                <div class="card-grid space-y-5">
                <form method="post" action="{{ route('admin.branding.update', $section) }}" enctype="multipart/form-data" class="space-y-5">
                    @csrf
                    @method('put')

                    @if ($section === 'general')
                        <label class="field"><span>App Name</span><input name="app_name" value="{{ old('app_name', $values['app_name']) }}"></label>
                        <label class="field"><span>Kurzname</span><input name="short_name" value="{{ old('short_name', $values['short_name']) }}"></label>
                        <label class="field"><span>Untertitel</span><input name="subtitle" value="{{ old('subtitle', $values['subtitle']) }}"></label>
                        <label class="field"><span>Beschreibung</span><textarea name="description" rows="4">{{ old('description', $values['description']) }}</textarea></label>
                        <label class="field"><span>Copyright Text</span><input name="copyright_text" value="{{ old('copyright_text', $values['copyright_text']) }}"></label>
                        <label class="field"><span>Betreibername</span><input name="operator_name" value="{{ old('operator_name', $values['operator_name']) }}"></label>
                        <label class="field"><span>Website URL</span><input name="website_url" type="url" value="{{ old('website_url', $values['website_url']) }}"></label>
                    @elseif ($section === 'login')
                        <label class="field"><span>Login Hintergrund</span><input type="file" name="login_background" accept=".png,.jpg,.jpeg,.webp"><small>Empfehlung: mindestens 1920 × 1080 px, maximal 15 MB.</small></label>
                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="field"><span>Hintergrundfarbe</span><input type="color" name="background_color" value="{{ old('background_color', $values['background_color']) }}"></label>
                            <label class="field"><span>Overlay Farbe</span><input type="color" name="overlay_color" value="{{ old('overlay_color', $values['overlay_color']) }}"></label>
                            <label class="field"><span>Overlay Staerke</span><input type="number" name="overlay_strength" value="{{ old('overlay_strength', $values['overlay_strength']) }}"></label>
                            <label class="field"><span>Login Box Hintergrund</span><input type="color" name="box_background_color" value="{{ old('box_background_color', $values['box_background_color']) }}"></label>
                            <label class="field"><span>Login Box Transparenz</span><input type="number" name="box_opacity" value="{{ old('box_opacity', $values['box_opacity']) }}"></label>
                            <label class="field"><span>Logo Breite</span><input type="number" name="logo_width" value="{{ old('logo_width', $values['logo_width']) }}"></label>
                        </div>
                        <label class="field"><span>Layout</span><select name="layout">@foreach (['centered' => 'Zentriert', 'left' => 'Links', 'right' => 'Rechts', 'split-left' => 'Split Bild links', 'split-right' => 'Split Bild rechts'] as $key => $label)<option value="{{ $key }}" @selected(old('layout', $values['layout']) === $key)>{{ $label }}</option>@endforeach</select></label>
                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="field"><span>Hintergrunddarstellung</span><select name="background_fit">@foreach (['cover' => 'Cover', 'contain' => 'Contain', 'original' => 'Original'] as $key => $label)<option value="{{ $key }}" @selected(old('background_fit', $values['background_fit']) === $key)>{{ $label }}</option>@endforeach</select></label>
                            <label class="field"><span>Hintergrundposition</span><select name="background_position">@foreach (['center' => 'Zentriert', 'top' => 'Oben', 'bottom' => 'Unten', 'left' => 'Links', 'right' => 'Rechts'] as $key => $label)<option value="{{ $key }}" @selected(old('background_position', $values['background_position']) === $key)>{{ $label }}</option>@endforeach</select></label>
                            <label class="field"><span>Logo Ausrichtung</span><select name="logo_alignment">@foreach (['left' => 'Links', 'center' => 'Zentriert', 'right' => 'Rechts'] as $key => $label)<option value="{{ $key }}" @selected(old('logo_alignment', $values['logo_alignment']) === $key)>{{ $label }}</option>@endforeach</select></label>
                            <label class="field"><span>Login Box Breite</span><select name="box_width">@foreach (['compact' => 'Kompakt', 'standard' => 'Standard', 'wide' => 'Breit'] as $key => $label)<option value="{{ $key }}" @selected(old('box_width', $values['box_width']) === $key)>{{ $label }}</option>@endforeach</select></label>
                            <label class="field"><span>Radius</span><select name="box_radius">@foreach (['soft' => 'Soft', 'rounded' => 'Rund', 'pill' => 'Stark gerundet'] as $key => $label)<option value="{{ $key }}" @selected(old('box_radius', $values['box_radius']) === $key)>{{ $label }}</option>@endforeach</select></label>
                            <label class="field"><span>Schatten</span><select name="box_shadow">@foreach (['none' => 'Kein', 'soft' => 'Dezent', 'medium' => 'Mittel'] as $key => $label)<option value="{{ $key }}" @selected(old('box_shadow', $values['box_shadow']) === $key)>{{ $label }}</option>@endforeach</select></label>
                        </div>
                        <label class="field"><span><input type="checkbox" name="background_enabled" value="1" @checked(old('background_enabled', $values['background_enabled']))> Hintergrundbild aktiv</span></label>
                        <label class="field"><span><input type="checkbox" name="overlay_enabled" value="1" @checked(old('overlay_enabled', $values['overlay_enabled']))> Overlay aktiv</span></label>
                        <label class="field"><span>Login Titel</span><input name="title" value="{{ old('title', $values['title']) }}"></label>
                        <label class="field"><span>Login Untertitel</span><input name="subtitle" value="{{ old('subtitle', $values['subtitle']) }}"></label>
                        <label class="field"><span>Begruessungstext</span><textarea name="welcome_text" rows="3">{{ old('welcome_text', $values['welcome_text']) }}</textarea></label>
                        <label class="field"><span>Button Text</span><input name="button_text" value="{{ old('button_text', $values['button_text']) }}"></label>
                        <label class="field"><span>Passwort vergessen Text</span><input name="forgot_password_text" value="{{ old('forgot_password_text', $values['forgot_password_text']) }}"></label>
                        <label class="field"><span>Footer Text</span><input name="footer_text" value="{{ old('footer_text', $values['footer_text']) }}"></label>
                    @elseif ($section === 'logos')
                        <label class="field"><span>Hauptlogo</span><input type="file" name="main_logo" accept=".png,.jpg,.jpeg,.webp"><small>Empfohlen: transparentes PNG oder WEBP, maximal 5 MB.</small></label>
                        <label class="field"><span>Login Logo</span><input type="file" name="login_logo" accept=".png,.jpg,.jpeg,.webp"></label>
                        <label class="field"><span>Admin Logo</span><input type="file" name="admin_logo" accept=".png,.jpg,.jpeg,.webp"></label>
                        <label class="field"><span>App Header Logo</span><select name="logo_display">@foreach (['main_logo' => 'Hauptlogo', 'login_logo' => 'Login Logo', 'admin_logo' => 'Admin Logo'] as $key => $label)<option value="{{ $key }}" @selected(old('logo_display', $values['logo_display']) === $key)>{{ $label }}</option>@endforeach</select></label>
                    @elseif ($section === 'colors')
                        <div class="grid gap-4 md:grid-cols-2">
                            @foreach (['primary' => 'Primärfarbe', 'secondary' => 'Sekundärfarbe', 'accent' => 'Akzentfarbe', 'background' => 'Hintergrund', 'surface' => 'Karten Hintergrund', 'text' => 'Textfarbe', 'text_muted' => 'Sekundäre Textfarbe', 'navigation' => 'Navigation Hintergrund', 'navigation_active' => 'Navigation aktiv', 'button_primary' => 'Button Primär', 'button_text' => 'Button Text', 'login_box_background' => 'Login Box Hintergrund'] as $key => $label)
                                <label class="field"><span>{{ $label }}</span><input type="color" name="{{ $key }}" value="{{ old($key, $values[$key]) }}"></label>
                            @endforeach
                        </div>
                    @elseif ($section === 'icons')
                        <label class="field"><span>Favicon</span><input type="file" name="favicon" accept=".png,.jpg,.jpeg,.webp"><small>Empfohlen: quadratisches Bild, 1024 × 1024 px als Ausgangsbasis.</small></label>
                        <label class="field"><span>Apple Touch Icon</span><input type="file" name="apple_touch_icon" accept=".png,.jpg,.jpeg,.webp"></label>
                    @elseif ($section === 'pwa')
                        <label class="field"><span>PWA Name</span><input name="pwa_name" value="{{ old('pwa_name', $values['pwa_name']) }}"></label>
                        <label class="field"><span>PWA Kurzname</span><input name="pwa_short_name" value="{{ old('pwa_short_name', $values['pwa_short_name']) }}"></label>
                        <label class="field"><span>PWA Beschreibung</span><textarea name="pwa_description" rows="3">{{ old('pwa_description', $values['pwa_description']) }}</textarea></label>
                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="field"><span>Theme Color</span><input type="color" name="theme_color" value="{{ old('theme_color', $values['theme_color']) }}"></label>
                            <label class="field"><span>Background Color</span><input type="color" name="background_color" value="{{ old('background_color', $values['background_color']) }}"></label>
                        </div>
                        <label class="field"><span>App Icon</span><input type="file" name="app_icon" accept=".png,.jpg,.jpeg,.webp"><small>Empfohlen: quadratisch, ideal 1024 × 1024 px. Daraus werden 192 und 512 px PWA-Icons erzeugt.</small></label>
                        <label class="field"><span>Maskable Icon</span><input type="file" name="maskable_icon" accept=".png,.jpg,.jpeg,.webp"></label>
                    @endif

                    <div class="flex flex-wrap gap-3">
                        <button class="btn-primary">Speichern</button>
                    </div>
                </form>
                <form method="post" action="{{ route('admin.branding.reset', $section) }}" onsubmit="return confirm('Diesen Bereich wirklich auf Standard zuruecksetzen?')">
                    @csrf
                    <button class="btn-secondary">Bereich zuruecksetzen</button>
                </form>
                </div>

                <section class="card-grid space-y-4">
                    <div class="section-head"><h2>Aktuelle Vorschau</h2></div>
                    @if ($section === 'logos')
                        <div class="space-y-4">
                            @foreach (['main_logo' => 'Hauptlogo', 'login_logo' => 'Login Logo', 'admin_logo' => 'Admin Logo'] as $slot => $label)
                                <div class="rounded-[24px] border border-white/8 bg-black/15 p-4">
                                    <p class="mb-3 text-sm text-[var(--color-mist)]">{{ $label }}</p>
                                    @if (!empty($branding['icons'][$slot]))
                                        <img src="{{ $branding['icons'][$slot] }}" alt="{{ $label }}" class="max-h-24 max-w-full">
                                        <form method="post" action="{{ route('admin.branding.assets.destroy', $slot) }}" class="mt-3">@csrf @method('delete')<button class="btn-secondary">Bild loeschen</button></form>
                                    @else
                                        <div class="empty-card">Aktuell kein individuelles Bild vorhanden.</div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @elseif ($section === 'icons')
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="rounded-[24px] border border-white/8 bg-black/15 p-4">
                                <p class="mb-3 text-sm text-[var(--color-mist)]">Favicon</p>
                                <img src="{{ $branding['icons']['favicon'] }}" alt="Favicon" class="size-12">
                                <form method="post" action="{{ route('admin.branding.assets.destroy', 'favicon') }}" class="mt-3">@csrf @method('delete')<button class="btn-secondary">Favicon loeschen</button></form>
                            </div>
                            <div class="rounded-[24px] border border-white/8 bg-black/15 p-4">
                                <p class="mb-3 text-sm text-[var(--color-mist)]">Apple Touch Icon</p>
                                <img src="{{ $branding['icons']['apple_touch_icon'] }}" alt="Apple Touch Icon" class="size-20 rounded-[18px]">
                                <form method="post" action="{{ route('admin.branding.assets.destroy', 'apple_touch_icon') }}" class="mt-3">@csrf @method('delete')<button class="btn-secondary">Apple Touch Icon loeschen</button></form>
                            </div>
                        </div>
                    @else
                        <a href="{{ route('admin.branding.edit', 'preview') }}" class="rounded-[28px] border border-white/8 bg-black/15 p-5 block">
                            <p class="text-sm text-[var(--color-mist)]">Zur vollstaendigen Branding-Vorschau</p>
                            <h3 class="mt-2 font-serif text-2xl">Login, Navigation, Buttons und PWA ansehen</h3>
                        </a>
                    @endif

                    <form method="post" action="{{ route('admin.branding.reset-all') }}" onsubmit="return confirm('Alle individuellen Branding-Einstellungen wirklich zuruecksetzen?')">
                        @csrf
                        <button class="btn-secondary">Gesamtes Branding zuruecksetzen</button>
                    </form>
                </section>
            </div>
        @endif
    </div>
@endsection
