@extends('layouts.app', ['title' => 'Sammlung'])

@section('content')
    <div class="space-y-6">
        <div class="section-head"><div><p class="text-xs uppercase tracking-[0.35em] text-[var(--color-gold)]">Sammlung</p><h1 class="mt-2 font-serif text-3xl">Alle gespeicherten Weine</h1></div></div>
        <form method="get" class="grid gap-3 rounded-[28px] border border-white/8 bg-white/6 p-4 md:grid-cols-[1fr_220px_auto]">
            <label class="field"><span>Suche</span><input type="search" name="q" value="{{ request('q') }}" placeholder="Wein, Produzent, Region, Notiz"></label>
            <label class="field">
                <span>Bewertung</span>
                <select name="preference">
                    <option value="">Alle</option>
                    @foreach ($preferences as $preference)
                        <option value="{{ $preference->value }}" @selected(request('preference') === $preference->value)>{{ $preference->label() }}</option>
                    @endforeach
                </select>
            </label>
            <input type="hidden" name="view" value="{{ $viewMode }}">
            <button class="btn-secondary md:self-end">Filtern</button>
        </form>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('collection.index', array_merge(request()->query(), ['view' => 'cards'])) }}" class="btn-secondary {{ $viewMode === 'cards' ? '!border-[var(--color-gold)]' : '' }}">Karten</a>
            <a href="{{ route('collection.index', array_merge(request()->query(), ['view' => 'list'])) }}" class="btn-secondary {{ $viewMode === 'list' ? '!border-[var(--color-gold)]' : '' }}">Liste</a>
        </div>
        <div class="{{ $viewMode === 'list' ? 'grid gap-4' : 'grid gap-4 md:grid-cols-2 xl:grid-cols-3' }}">
            @forelse ($wines as $item)
                <x-wine-card :item="$item" />
            @empty
                <div class="empty-card {{ $viewMode === 'cards' ? 'md:col-span-2 xl:col-span-3' : '' }}">Noch keine Weine gespeichert.</div>
            @endforelse
        </div>
        {{ $wines->links() }}
    </div>
@endsection
