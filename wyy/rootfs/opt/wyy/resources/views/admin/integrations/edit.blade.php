@extends('layouts.app', ['title' => $provider['name']])

@section('content')
    <div class="space-y-6">
        <div class="section-head">
            <div><p class="text-xs uppercase tracking-[0.35em] text-[var(--color-gold)]">Administration</p><h1 class="mt-2 font-serif text-3xl">{{ $provider['name'] }}</h1></div>
            <a href="{{ route('admin.integrations.index') }}" class="btn-secondary">Zur Uebersicht</a>
        </div>
        @include('admin.partials.nav')
        <form method="post" action="{{ route('admin.integrations.update', $provider['slug']) }}" class="card-grid space-y-5">
            @csrf
            @method('put')
            <label class="field"><span>Status</span><input type="checkbox" name="enabled" value="1" @checked(old('enabled', $provider['config']['enabled']))><small>Aktivieren</small></label>
            @if ($provider['slug'] === 'openai')
                <label class="field"><span>API Key</span><input type="password" name="api_key" placeholder="{{ $provider['masked_secret'] ? 'Vorhandener Key bleibt erhalten' : 'sk-...' }}"></label>
                <label class="field"><span>Bildmodell</span><input type="text" name="image_model" value="{{ old('image_model', $provider['config']['image_model']) }}"></label>
                <label class="field"><span>Textmodell</span><input type="text" name="text_model" value="{{ old('text_model', $provider['config']['text_model']) }}"></label>
                <label class="field"><span>Strukturierungsmodell</span><input type="text" name="structuring_model" value="{{ old('structuring_model', $provider['config']['structuring_model']) }}"></label>
                <label class="field"><span>Fallback</span><select name="fallback_provider"><option value="">Kein Fallback</option><option value="google_vision" @selected(old('fallback_provider', $provider['config']['fallback_provider'] ?? '') === 'google_vision')>Google Vision</option><option value="wine_provider" @selected(old('fallback_provider', $provider['config']['fallback_provider'] ?? '') === 'wine_provider')>Wine Provider</option></select></label>
            @elseif ($provider['slug'] === 'wine_provider')
                <label class="field"><span>API Key</span><input type="password" name="api_key" placeholder="{{ $provider['masked_secret'] ? 'Vorhandener Key bleibt erhalten' : 'API Key' }}"></label>
                <label class="field"><span>Endpoint</span><input type="url" name="endpoint" value="{{ old('endpoint', $provider['config']['endpoint']) }}"></label>
                <label class="field"><span>Region / Markt</span><input type="text" name="region" value="{{ old('region', $provider['config']['region']) }}"></label>
                <label class="field"><span>Cache Minuten</span><input type="number" name="cache_minutes" value="{{ old('cache_minutes', $provider['config']['cache_minutes']) }}"></label>
                <label class="field"><span>Fallback</span><select name="fallback_provider"><option value="">Kein Fallback</option><option value="openai" @selected(old('fallback_provider', $provider['config']['fallback_provider'] ?? '') === 'openai')>OpenAI</option></select></label>
            @else
                <label class="field"><span>Credentials JSON</span><textarea name="credentials_json" rows="7" placeholder="{{ $provider['masked_secret'] ? 'Vorhandene Credentials bleiben erhalten' : '{ ... }' }}"></textarea></label>
                <label class="field"><span>Endpoint</span><input type="url" name="endpoint" value="{{ old('endpoint', $provider['config']['endpoint']) }}"></label>
                <label class="field"><span>OCR aktiv</span><input type="checkbox" name="ocr_enabled" value="1" @checked(old('ocr_enabled', $provider['config']['ocr_enabled']))></label>
                <label class="field"><span>Web Detection aktiv</span><input type="checkbox" name="web_detection_enabled" value="1" @checked(old('web_detection_enabled', $provider['config']['web_detection_enabled']))></label>
                <label class="field"><span>Fallback</span><select name="fallback_provider"><option value="">Kein Fallback</option><option value="openai" @selected(old('fallback_provider', $provider['config']['fallback_provider'] ?? '') === 'openai')>OpenAI</option></select></label>
            @endif
            <div class="grid gap-4 md:grid-cols-2">
                <label class="field"><span>Timeout</span><input type="number" name="timeout" value="{{ old('timeout', $provider['config']['timeout']) }}"></label>
                <label class="field"><span>Prioritaet</span><input type="number" name="priority" value="{{ old('priority', $provider['config']['priority'] ?? 1) }}"></label>
            </div>
            @if ($provider['slug'] === 'openai')
                <label class="field"><span>Retries</span><input type="number" name="retries" value="{{ old('retries', $provider['config']['retries']) }}"></label>
            @endif
            <div class="flex flex-wrap gap-3">
                <button class="btn-primary">Speichern</button>
            </div>
            <div class="rounded-[24px] border border-white/8 bg-black/15 p-4 text-sm text-[var(--color-mist)]">
                Status: {{ $provider['status_label'] }}<br>
                Letzter Test: {{ $provider['config']['last_test_at'] ? \Illuminate\Support\Carbon::parse($provider['config']['last_test_at'])->format(config('app.date_format', 'd.m.Y').' H:i') : 'Noch nie' }}<br>
                Letzte Meldung: {{ $provider['config']['last_test_message'] ?? 'Keine' }}
            </div>
        </form>
        <div class="flex flex-wrap gap-3">
            <form method="post" action="{{ route('admin.integrations.test', $provider['slug']) }}">@csrf<button class="btn-secondary">Verbindung testen</button></form>
            @if ($provider['masked_secret'])
                <form method="post" action="{{ route('admin.integrations.secret.destroy', $provider['slug']) }}">@csrf @method('delete')<button class="btn-secondary">Secret loeschen</button></form>
            @endif
        </div>
    </div>
@endsection
