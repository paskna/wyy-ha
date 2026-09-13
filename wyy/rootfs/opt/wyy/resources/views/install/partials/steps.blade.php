<div class="mb-6 grid grid-cols-2 gap-2 md:grid-cols-7">
    @foreach ($steps as $number => $step)
        <div class="rounded-2xl border border-white/10 px-3 py-3 text-center text-xs {{ request()->routeIs($step['route']) ? 'bg-[var(--color-gold)]/15 text-[var(--color-cream)]' : 'bg-white/5 text-[var(--color-mist)]' }}">
            <div class="font-semibold">{{ $number }}</div>
            <div class="mt-1">{{ $step['label'] }}</div>
        </div>
    @endforeach
</div>
