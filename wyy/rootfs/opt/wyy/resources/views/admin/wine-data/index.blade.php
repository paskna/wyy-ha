@extends('layouts.app', ['title' => 'Wein-Daten'])

@section('content')
    <div class="space-y-6">
        <div><p class="text-xs uppercase tracking-[0.35em] text-[var(--color-gold)]">Administration</p><h1 class="mt-2 font-serif text-3xl">Wein-Daten</h1></div>
        @include('admin.partials.nav')
        <div class="flex gap-2 overflow-x-auto pb-2">
            @foreach ($sections as $key => $label)
                <a href="{{ route('admin.wine-data.index', ['section' => $key]) }}" class="rounded-full border px-4 py-2 text-sm {{ $section === $key ? 'border-[var(--color-gold)] bg-[var(--color-burgundy-soft)]' : 'border-white/10 bg-black/20 text-[var(--color-mist)]' }}">{{ $label }}</a>
            @endforeach
        </div>
        @if ($section !== 'duplicates')
            <form method="get" class="rounded-[24px] border border-white/8 bg-white/6 p-4">
                <label class="field"><span>Suche</span><input type="search" name="q" value="{{ $search }}" placeholder="Name oder Jahrgang"></label>
            </form>
        @endif
        <div class="space-y-3">
            @if ($section === 'wines')
                @foreach ($records as $wine)
                    <div class="rounded-[24px] border border-white/8 bg-white/6 p-4">
                        <strong>{{ $wine->name }}</strong>
                        <p class="mt-1 text-sm text-[var(--color-mist)]">{{ $wine->producer?->name }} · {{ $wine->region ?: 'ohne Region' }}</p>
                        <a href="{{ route('admin.wine-data.wines.edit', $wine) }}" class="btn-secondary mt-3">Bearbeiten</a>
                    </div>
                @endforeach
                {{ $records->links() }}
            @elseif ($section === 'producers')
                @foreach ($records as $producer)
                    <div class="rounded-[24px] border border-white/8 bg-white/6 p-4">
                        <strong>{{ $producer->name }}</strong>
                        <p class="mt-1 text-sm text-[var(--color-mist)]">{{ $producer->country ?: 'ohne Land' }} · {{ $producer->region ?: 'ohne Region' }}</p>
                        <a href="{{ route('admin.wine-data.producers.edit', $producer) }}" class="btn-secondary mt-3">Bearbeiten</a>
                    </div>
                @endforeach
                {{ $records->links() }}
            @elseif ($section === 'vintages')
                @foreach ($records as $vintage)
                    <div class="rounded-[24px] border border-white/8 bg-white/6 p-4">
                        <strong>{{ $vintage->wine?->producer?->name }} {{ $vintage->wine?->name }}</strong>
                        <p class="mt-1 text-sm text-[var(--color-mist)]">Jahrgang {{ $vintage->vintage ?: 'o. J.' }}</p>
                        <a href="{{ route('admin.wine-data.vintages.edit', $vintage) }}" class="btn-secondary mt-3">Bearbeiten</a>
                    </div>
                @endforeach
                {{ $records->links() }}
            @elseif ($section === 'grapes')
                @foreach ($records as $grape)
                    <div class="rounded-[24px] border border-white/8 bg-white/6 p-4">
                        <strong>{{ $grape->name }}</strong>
                        <a href="{{ route('admin.wine-data.grapes.edit', $grape) }}" class="btn-secondary mt-3">Bearbeiten</a>
                    </div>
                @endforeach
                {{ $records->links() }}
            @else
                @forelse ($records as $pair)
                    <div class="rounded-[24px] border border-white/8 bg-white/6 p-4">
                        <strong>{{ $pair['left']->wine->producer->name }} {{ $pair['left']->wine->name }} {{ $pair['left']->vintage }}</strong>
                        <p class="mt-1 text-sm text-[var(--color-mist)]">vs. {{ $pair['right']->wine->producer->name }} {{ $pair['right']->wine->name }} {{ $pair['right']->vintage }} · Score {{ $pair['score'] }}</p>
                        <p class="mt-2 text-sm text-[var(--color-mist)]">{{ $pair['reason'] }}</p>
                        <form method="post" action="{{ route('admin.wine-data.merge') }}" class="mt-3 flex flex-wrap gap-3">
                            @csrf
                            <input type="hidden" name="source_id" value="{{ $pair['right']->id }}">
                            <input type="hidden" name="target_id" value="{{ $pair['left']->id }}">
                            <button class="btn-secondary" onclick="return confirm('Diese Jahrgaenge wirklich zusammenfuehren?')">Zusammenfuehren</button>
                        </form>
                    </div>
                @empty
                    <div class="empty-card">Keine moeglichen Duplikate erkannt.</div>
                @endforelse
            @endif
        </div>
    </div>
@endsection
