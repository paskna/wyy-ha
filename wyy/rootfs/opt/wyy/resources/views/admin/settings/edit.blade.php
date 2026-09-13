@extends('layouts.app', ['title' => 'Systemeinstellungen'])

@section('content')
    <div class="space-y-6">
        <div><p class="text-xs uppercase tracking-[0.35em] text-[var(--color-gold)]">Administration</p><h1 class="mt-2 font-serif text-3xl">Systemeinstellungen</h1></div>
        @include('admin.partials.nav')
        <div class="flex gap-2 overflow-x-auto pb-2">
            @foreach ($sections as $key => $label)
                <a href="{{ route('admin.settings.edit', $key) }}" class="rounded-full border px-4 py-2 text-sm {{ $section === $key ? 'border-[var(--color-gold)] bg-[var(--color-burgundy-soft)]' : 'border-white/10 bg-black/20 text-[var(--color-mist)]' }}">{{ $label }}</a>
            @endforeach
        </div>
        <form method="post" action="{{ route('admin.settings.update', $section) }}" class="card-grid space-y-5">
            @csrf
            @method('put')
            @foreach ($values as $key => $value)
                @if ($section === 'image' && $key === 'remove_exif')
                    <div class="rounded-[24px] border border-white/8 bg-black/15 px-4 py-4 text-sm text-[var(--color-mist)]">
                        <strong class="block text-[var(--color-cream)]">EXIF Entfernung</strong>
                        <p class="mt-2">EXIF-Daten werden aus Sicherheits- und Datenschutzgruenden immer entfernt und koennen nicht deaktiviert werden.</p>
                    </div>
                @elseif (is_bool($value))
                    <label class="field"><span>{{ str($key)->replace('_', ' ')->title() }}</span><input type="checkbox" name="{{ $key }}" value="1" @checked(old($key, $value))></label>
                @elseif ($section === 'mail' && $key === 'password')
                    <label class="field"><span>{{ str($key)->replace('_', ' ')->title() }}</span><input type="password" name="{{ $key }}" placeholder="{{ $value ? 'Vorhandenes Passwort bleibt erhalten' : '' }}"></label>
                @elseif (str_contains($key, 'subtitle') || str_contains($key, 'description'))
                    <label class="field"><span>{{ str($key)->replace('_', ' ')->title() }}</span><textarea name="{{ $key }}" rows="4">{{ old($key, $value) }}</textarea></label>
                @else
                    <label class="field"><span>{{ str($key)->replace('_', ' ')->title() }}</span><input type="{{ is_numeric($value) ? 'number' : 'text' }}" step="{{ is_float($value) ? '0.01' : '1' }}" name="{{ $key }}" value="{{ old($key, $value) }}"></label>
                @endif
            @endforeach
            <div class="flex flex-wrap gap-3">
                <button class="btn-primary">Speichern</button>
            </div>
        </form>
        @if ($section === 'cache')
            <div class="flex flex-wrap gap-3">
                <form method="post" action="{{ route('admin.settings.cache.clear') }}">@csrf<input type="hidden" name="scope" value="settings"><button class="btn-secondary">Settings-Cache loeschen</button></form>
                <form method="post" action="{{ route('admin.settings.cache.clear') }}">@csrf<input type="hidden" name="scope" value="application"><button class="btn-secondary">Anwendungscache loeschen</button></form>
            </div>
        @endif
        @if ($section === 'mail')
            <form method="post" action="{{ route('admin.settings.mail.test') }}" class="card-grid space-y-4">
                @csrf
                <div class="section-head"><h2>Testmail</h2></div>
                <label class="field"><span>Empfaenger</span><input type="email" name="recipient" value="{{ old('recipient', $values['test_recipient'] ?? '') }}"></label>
                <button class="btn-secondary">Testmail senden</button>
            </form>
        @endif
    </div>
@endsection
