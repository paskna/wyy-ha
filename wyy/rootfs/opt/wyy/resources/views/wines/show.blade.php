@extends('layouts.app', ['title' => 'Weindetail'])

@section('content')
    <div class="space-y-6">
        <div class="rounded-[36px] border border-white/8 bg-white/6 p-6">
            <p class="text-xs uppercase tracking-[0.35em] text-[var(--color-gold)]">{{ $wineVintage->wine->producer->name }}</p>
            <h1 class="mt-3 font-serif text-4xl leading-tight">{{ $wineVintage->wine->name }}</h1>
            <p class="mt-2 text-lg text-[var(--color-mist)]">{{ $wineVintage->vintage ?? 'NV' }} · {{ $wineVintage->wine->appellation ?: $wineVintage->wine->region ?: 'Herkunft folgt' }}</p>
        </div>
        @if (!$userWine)
            <section class="card-grid">
                <div class="section-head"><h2>Noch nicht in deiner Sammlung</h2></div>
                <p class="text-sm leading-6 text-[var(--color-mist)]">Die allgemeinen Weindaten und deine persönliche Einschätzung sind sichtbar. Persönliche Bewertung, Notiz und Bestand werden erst für dich angelegt, wenn du den Wein hinzufügst.</p>
                <form method="post" action="{{ route('wines.collection.add', $wineVintage) }}">
                    @csrf
                    <button class="btn-primary">Zu meiner Sammlung hinzufügen</button>
                </form>
            </section>
        @else
        <section class="card-grid">
            <div class="section-head"><h2>Meine Meinung</h2></div>
            <form method="post" action="{{ route('wines.preference', $wineVintage) }}" class="grid gap-3 md:grid-cols-4">
                @csrf
                @method('PATCH')
                @foreach (\App\Enums\Preference::cases() as $preference)
                    <button name="preference" value="{{ $preference->value }}" class="choice-chip {{ $userWine->preference === $preference ? 'choice-chip-active' : '' }}">{{ $preference->label() }}</button>
                @endforeach
            </form>
            <form method="post" action="{{ route('wines.note', $wineVintage) }}" class="space-y-3">
                @csrf
                @method('PATCH')
                <label class="field"><span>Persoenliche Notiz</span><textarea name="personal_note" rows="4">{{ old('personal_note', $userWine->personal_note) }}</textarea></label>
                <button class="btn-secondary">Notiz speichern</button>
            </form>
        </section>
        @endif
        <section class="card-grid">
            <div class="section-head"><h2>Passt zu meinem Geschmack</h2></div>
            <p class="text-3xl font-medium">{{ $tasteMatch['label'] }}</p>
            <p class="text-sm text-[var(--color-mist)]">{{ $tasteMatch['score'] !== null ? $tasteMatch['score'].' % Uebereinstimmung' : $tasteMatch['confidence'] }}</p>
            <p class="text-sm leading-6 text-[var(--color-mist)]">{{ $tasteMatch['explanation'] }}</p>
            @if (!empty($tasteMatch['breakdown']))
                <div class="mt-4 grid grid-cols-2 gap-2 text-xs text-[var(--color-mist)]">@foreach ($tasteMatch['breakdown'] as $key => $value)<span class="rounded-xl bg-black/15 px-3 py-2">{{ str($key)->title() }}: {{ $value }} %</span>@endforeach</div>
            @endif
            @if (($tasteMatch['similar_top_wines'] ?? collect())->isNotEmpty())
                <p class="mt-4 text-sm font-medium">Aehnelt deinen Top-Weinen</p><div class="mt-2 space-y-2">@foreach ($tasteMatch['similar_top_wines'] as $similarTop)<a href="{{ route('wines.show', $similarTop['wine']->wineVintage) }}" class="flex justify-between rounded-xl bg-black/15 px-3 py-2 text-sm"><span>{{ $similarTop['wine']->wineVintage->wine->name }}</span><span>{{ $similarTop['score'] }} %</span></a>@endforeach</div>
            @endif
        </section>
        @if ($userWine)
            <section class="card-grid">
                <div class="section-head"><h2>Mein Bestand</h2></div>
                <form method="post" action="{{ route('wines.quantity', $wineVintage) }}" class="space-y-3">
                    @csrf
                    @method('PATCH')
                    <label class="field"><span>Flaschen</span><input type="number" min="0" max="999" name="quantity" value="{{ old('quantity', $userWine->quantity) }}"></label>
                    <button class="btn-secondary">Bestand speichern</button>
                </form>
            </section>
        @endif
        <section class="card-grid">
            <div class="section-head"><h2>Aehnliche Weine</h2></div>
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @forelse ($similar as $item)
                    <x-wine-card :item="$item" />
                @empty
                    <div class="empty-card md:col-span-2 xl:col-span-3">Noch keine aehnlichen Weine in deiner Sammlung.</div>
                @endforelse
            </div>
        </section>
    </div>
@endsection
