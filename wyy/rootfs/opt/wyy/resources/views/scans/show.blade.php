@extends('layouts.app', ['title' => 'Scan-Ergebnis'])

@section('content')
    <div class="space-y-6">
        <div class="card-grid">
            <p class="text-xs uppercase tracking-[0.35em] text-[var(--color-gold)]">Scan-Ergebnis</p>
            <h1 class="font-serif text-3xl">{{ $match ? 'Bereits gespeichert oder bestaetigbar' : 'Neuer oder unbekannter Wein' }}</h1>
            <div class="scan-steps">
                <span class="active">Etikett wird gelesen</span>
                <span class="active">Eigene Sammlung wird geprueft</span>
                <span class="{{ filled($scan->recognition_data_json['producer'] ?? null) ? 'active' : '' }}">Wein wird identifiziert</span>
                <span class="{{ ($scan->recognition_data_json['enrichment_status'] ?? null) === 'success' ? 'active' : '' }}">Informationen werden ergaenzt</span>
                <span class="{{ $match ? 'active' : '' }}">Geschmacksprofil wird verglichen</span>
            </div>
            @if ($scan->recognition_data_json['message'] ?? null)
                <p class="mt-4 text-sm leading-6 text-[var(--color-mist)]">{{ $scan->recognition_data_json['message'] }}</p>
            @endif
            @if (!empty($scan->recognition_data_json['external_candidates']))
                <div class="mt-4 rounded-[20px] border border-amber-300/20 bg-amber-500/10 px-4 py-3">
                    <p class="font-medium text-amber-100">Mehrere globale Treffer sind moeglich. Bitte pruefe die Angaben vor dem Speichern.</p>
                    <ul class="mt-2 space-y-1 text-sm text-amber-100/80">
                        @foreach ($scan->recognition_data_json['external_candidates'] as $candidate)
                            <li>{{ is_array($candidate) ? collect($candidate)->filter(fn ($value) => is_scalar($value) && $value !== '')->values()->implode(' · ') : $candidate }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @if (($scan->recognition_data_json['known_other_vintage_label'] ?? null) && ! $match)
                <p class="mt-3 rounded-[20px] border border-amber-300/20 bg-amber-500/10 px-4 py-3 text-sm text-amber-100">Du kennst diesen Wein bereits in einem anderen Jahrgang: {{ $scan->recognition_data_json['known_other_vintage_label'] }}.</p>
            @endif
        </div>
        @if ($match)
            <div class="card-grid">
                <h2 class="font-serif text-2xl">{{ $match->wine->producer->name }}</h2>
                <p class="text-lg">{{ $match->wine->name }} · {{ $match->vintage ?? 'NV' }}</p>
                @if ($matchUserWine)
                    <p class="mt-3 text-sm text-[var(--color-mist)]">Bereits in deiner Sammlung · {{ $matchUserWine->preference->label() }} · Bestand: {{ $matchUserWine->quantity ?? 'nicht gepflegt' }}</p>
                    @if ($matchUserWine->personal_note)
                        <p class="mt-2 text-sm leading-6 text-[var(--color-mist)]">{{ $matchUserWine->personal_note }}</p>
                    @endif
                @endif
                @if ($tasteMatch)
                    <p class="text-sm text-[var(--color-mist)]">{{ $tasteMatch['label'] }}{{ $tasteMatch['score'] ? ' · '.$tasteMatch['score'].' % Uebereinstimmung' : '' }}</p>
                    <p class="text-sm leading-6 text-[var(--color-mist)]">{{ $tasteMatch['explanation'] }}</p>
                @endif
                <a href="{{ route('wines.show', $match) }}" class="btn-primary">Weindetail oeffnen</a>
            </div>
        @endif
        <form method="post" action="{{ route('scans.save', $scan) }}" class="card-grid">
            @csrf
            <div class="grid gap-4 md:grid-cols-2">
                <label class="field"><span>Produzent</span><input type="text" name="producer" value="{{ old('producer', $scan->recognition_data_json['producer'] ?? '') }}" required></label>
                <label class="field"><span>Weinname</span><input type="text" name="wine_name" value="{{ old('wine_name', $scan->recognition_data_json['wine_name'] ?? '') }}" required></label>
                <label class="field"><span>Jahrgang</span><input type="text" name="vintage" value="{{ old('vintage', $scan->recognition_data_json['vintage'] ?? '') }}"></label>
                <label class="field"><span>Weintyp</span><input type="text" name="wine_type" value="{{ old('wine_type', $scan->recognition_data_json['wine_type'] ?? 'Rotwein') }}"></label>
                <label class="field"><span>Land</span><input type="text" name="country" value="{{ old('country', $scan->recognition_data_json['country'] ?? '') }}"></label>
                <label class="field"><span>Region</span><input type="text" name="region" value="{{ old('region', $scan->recognition_data_json['region'] ?? '') }}"></label>
            </div>
            <label class="field">
                <span>Persoenliche Einstufung</span>
                <select name="preference">
                    @foreach (\App\Enums\Preference::cases() as $preference)
                        <option value="{{ $preference->value }}" @selected(old('preference', $matchUserWine?->preference?->value ?? \App\Enums\Preference::Unrated->value) === $preference->value)>{{ $preference->label() }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field"><span>Notiz</span><textarea name="personal_note" rows="4">{{ old('personal_note', $matchUserWine?->personal_note) }}</textarea></label>
            <label class="field"><span>Bestand</span><input type="number" min="0" max="999" name="quantity" value="{{ old('quantity', $matchUserWine?->quantity) }}"></label>
            <button class="btn-primary">In Sammlung speichern</button>
        </form>
        @if ($similar->isNotEmpty())
            <section class="space-y-4">
                <div class="section-head"><h2>Aehnliche Weine aus deiner Sammlung</h2></div>
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($similar as $item)
                        <x-wine-card :item="$item" />
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@endsection
