@extends('layouts.app', ['title' => 'API & Integrationen'])

@section('content')
    <div class="space-y-6">
        <div><p class="text-xs uppercase tracking-[0.35em] text-[var(--color-gold)]">Administration</p><h1 class="mt-2 font-serif text-3xl">API & Integrationen</h1></div>
        @include('admin.partials.nav')
        <div class="grid gap-4 lg:grid-cols-3">
            @foreach ($providers as $provider)
                <div class="card-grid space-y-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="font-serif text-2xl">{{ $provider['name'] }}</h2>
                            <p class="mt-2 text-sm text-[var(--color-mist)]">{{ $provider['description'] }}</p>
                        </div>
                        <span class="rounded-full px-3 py-1 text-xs {{ $provider['status'] === 'active' ? 'bg-emerald-500/20 text-emerald-100' : ($provider['status'] === 'error' ? 'bg-rose-500/20 text-rose-100' : 'bg-white/10 text-[var(--color-cream)]') }}">{{ $provider['status_label'] }}</span>
                    </div>
                    <div class="space-y-2 text-sm text-[var(--color-mist)]">
                        <p>Letzter Test: {{ $provider['config']['last_test_at'] ? \Illuminate\Support\Carbon::parse($provider['config']['last_test_at'])->format(config('app.date_format', 'd.m.Y').' H:i') : 'Noch nie' }}</p>
                        <p>Letzte Meldung: {{ $provider['config']['last_test_message'] ?? 'Keine' }}</p>
                        @if ($provider['masked_secret'])
                            <p>Secret: {{ $provider['masked_secret'] }}</p>
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <a href="{{ route('admin.integrations.edit', $provider['slug']) }}" class="btn-secondary">Konfigurieren</a>
                        <form method="post" action="{{ route('admin.integrations.test', $provider['slug']) }}">@csrf<button class="btn-secondary">Testen</button></form>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endsection
