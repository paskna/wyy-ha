@extends('layouts.app', ['title' => 'Systemdiagnose'])

@section('content')
    <div class="space-y-6">
        <div><p class="text-xs uppercase tracking-[0.35em] text-[var(--color-gold)]">Administration</p><h1 class="mt-2 font-serif text-3xl">Systemdiagnose</h1></div>
        @include('admin.partials.nav')
        <div class="space-y-3">
            @foreach ($checks as $check)
                <div class="rounded-[24px] border border-white/8 bg-white/6 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <strong>{{ $check['label'] }}</strong>
                        <span class="rounded-full px-3 py-1 text-xs {{ $check['status'] === 'ok' ? 'bg-emerald-500/20 text-emerald-100' : ($check['status'] === 'error' ? 'bg-rose-500/20 text-rose-100' : 'bg-amber-500/20 text-amber-100') }}">{{ strtoupper($check['status']) }}</span>
                    </div>
                    <p class="mt-2 text-sm text-[var(--color-mist)]">{{ $check['message'] }}</p>
                </div>
            @endforeach
        </div>
        <section class="card-grid space-y-3">
            <div class="section-head"><h2>Letzte API Fehler</h2></div>
            @forelse ($recentErrors as $error)
                <div class="rounded-[22px] border border-white/8 bg-black/15 px-4 py-4 text-sm text-[var(--color-mist)]">{{ $error->provider }} · {{ $error->message }} · {{ $error->created_at->format(config('app.date_format', 'd.m.Y').' H:i') }}</div>
            @empty
                <div class="empty-card">Keine aktuellen API Fehler vorhanden.</div>
            @endforelse
        </section>
    </div>
@endsection
