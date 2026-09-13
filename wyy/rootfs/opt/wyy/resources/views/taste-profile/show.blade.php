@extends('layouts.app', ['title' => 'Mein Genussprofil'])

@section('content')
    <div class="space-y-6">
        <div class="section-head">
            <div><p class="text-xs uppercase tracking-[0.35em] text-[var(--color-gold)]">Mein Genussprofil</p><h1 class="mt-2 font-serif text-3xl">Was dir wahrscheinlich schmeckt</h1></div>
            <a href="{{ route('more.index') }}" class="btn-secondary">Zurueck</a>
        </div>
        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="metric-card"><span>Status</span><strong class="text-lg">{{ $summary['status'] }}</strong></div>
            <div class="metric-card"><span>Bewertete Weine</span><strong>{{ $summary['rated_count'] }}</strong></div>
            <div class="metric-card"><span>Top / Unpassend</span><strong>{{ $summary['top_count'] }} / {{ $summary['unsuitable_count'] }}</strong></div>
            <div class="metric-card"><span>Confidence</span><strong>{{ $summary['confidence_label'] }}</strong></div>
        </section>
        <section class="card-grid">
            <h2 class="font-serif text-2xl">Deine bisherige Tendenz</h2>
            <p class="mt-3 leading-7 text-[var(--color-mist)]">{{ $summary['description'] }}</p>
            @if ($summary['needed'] > 0)
                <p class="mt-4 rounded-2xl border border-white/8 bg-black/15 px-4 py-3 text-sm text-[var(--color-mist)]">Bewerte noch {{ $summary['needed'] }} Weine fuer persoenliche Empfehlungen.</p>
            @endif
        </section>
        <section class="card-grid">
            <div class="section-head"><h2>Geschmacksmerkmale</h2><span class="text-xs text-[var(--color-mist)]">0 bis 100</span></div>
            <div class="space-y-4">
                @foreach (['body' => 'Koerper', 'fruit' => 'Frucht', 'acidity' => 'Saeure', 'tannin' => 'Tannin', 'oak' => 'Holz', 'spice' => 'Wuerze', 'mineral' => 'Mineralitaet', 'sweetness' => 'Suesse'] as $key => $label)
                    @if (($summary['features'][$key] ?? null) !== null)
                        <div><div class="mb-1 flex justify-between text-sm"><span>{{ $label }}</span><span>{{ round($summary['features'][$key] * 100) }} %</span></div><div class="h-2 rounded-full bg-white/10"><div class="h-2 rounded-full bg-[var(--color-gold)]" style="width: {{ round($summary['features'][$key] * 100) }}%"></div></div></div>
                    @endif
                @endforeach
            </div>
        </section>
        <div class="grid gap-6 md:grid-cols-2">
            <section class="card-grid"><div class="section-head"><h2>Bevorzugte Rebsorten</h2></div>@forelse ($summary['grapes'] as $grape)<div class="flex items-center justify-between border-b border-white/8 py-3 text-sm"><span>{{ $grape['name'] }}</span><span class="text-[var(--color-mist)]">{{ $grape['count'] }} Bewertungen · {{ round($grape['affinity'] * 100) }} %</span></div>@empty<p class="text-sm text-[var(--color-mist)]">Noch nicht genügend Daten.</p>@endforelse</section>
            <section class="card-grid"><div class="section-head"><h2>Bevorzugte Regionen</h2></div>@forelse ($summary['regions'] as $region)<div class="flex items-center justify-between border-b border-white/8 py-3 text-sm"><span>{{ $region['name'] }}</span><span class="text-[var(--color-mist)]">{{ $region['count'] }} Bewertungen · {{ round($region['affinity'] * 100) }} %</span></div>@empty<p class="text-sm text-[var(--color-mist)]">Noch nicht genügend Daten.</p>@endforelse</section>
        </div>
    </div>
@endsection
