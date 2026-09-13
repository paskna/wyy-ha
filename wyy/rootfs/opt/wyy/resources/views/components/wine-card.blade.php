@props(['item'])

@php
    $userWine = $item instanceof \App\Models\UserWine ? $item : $item['vintage']->userWines->first();
    $vintage = $item instanceof \App\Models\UserWine ? $item->wineVintage : $item['vintage'];
    $score = $item['score'] ?? null;
@endphp

<a href="{{ route('wines.show', $vintage) }}" class="block rounded-[28px] border border-white/8 bg-white/6 p-4 shadow-[0_20px_50px_rgba(0,0,0,0.18)]">
    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="text-xs uppercase tracking-[0.3em] text-[var(--color-gold)]">{{ $vintage->wine->producer->name }}</p>
            <h3 class="mt-2 font-serif text-xl leading-tight">{{ $vintage->wine->name }}</h3>
            <p class="mt-1 text-sm text-[var(--color-mist)]">{{ $vintage->vintage ?? 'NV' }} · {{ $vintage->wine->region ?: 'Region folgt' }}</p>
        </div>
        @if ($userWine)
            <span class="rounded-full border border-white/8 px-3 py-1 text-xs text-white/80">{{ $userWine->preference->label() }}</span>
        @endif
    </div>
    <div class="mt-4 flex flex-wrap gap-2 text-xs text-[var(--color-mist)]">
        @foreach ($vintage->grapes->take(3) as $grape)
            <span class="rounded-full bg-black/20 px-3 py-1">{{ $grape->name }}</span>
        @endforeach
        @if ($score !== null)
            <span class="rounded-full bg-[var(--color-burgundy-soft)] px-3 py-1">{{ round($score * 100) }} % {{ isset($item['match']) ? 'passt' : 'aehnlich' }}</span>
        @endif
    </div>
    @if ($userWine?->personal_note)
        <p class="mt-4 text-sm leading-6 text-[var(--color-mist)]">{{ \Illuminate\Support\Str::limit($userWine->personal_note, 96) }}</p>
    @endif
</a>
