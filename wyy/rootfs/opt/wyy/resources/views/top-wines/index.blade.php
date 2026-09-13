@extends('layouts.app', ['title' => 'Top-Weine'])

@section('content')
    <div class="space-y-6">
        <div class="section-head"><div><p class="text-xs uppercase tracking-[0.35em] text-[var(--color-gold)]">Top-Weine</p><h1 class="mt-2 font-serif text-3xl">Deine klaren Wiederkauf-Kandidaten</h1></div></div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('top-wines.index', array_merge(request()->query(), ['view' => 'cards'])) }}" class="btn-secondary {{ $viewMode === 'cards' ? '!border-[var(--color-gold)]' : '' }}">Karten</a>
            <a href="{{ route('top-wines.index', array_merge(request()->query(), ['view' => 'list'])) }}" class="btn-secondary {{ $viewMode === 'list' ? '!border-[var(--color-gold)]' : '' }}">Liste</a>
        </div>
        <div class="{{ $viewMode === 'list' ? 'grid gap-4' : 'grid gap-4 md:grid-cols-2 xl:grid-cols-3' }}">
            @forelse ($wines as $item)
                <x-wine-card :item="$item" />
            @empty
                <div class="empty-card {{ $viewMode === 'cards' ? 'md:col-span-2 xl:col-span-3' : '' }}">Noch keine Top-Weine markiert.</div>
            @endforelse
        </div>
        {{ $wines->links() }}
    </div>
@endsection
