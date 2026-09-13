@extends('layouts.app', ['title' => 'Mehr'])

@section('content')
    <div class="space-y-6">
        <div class="section-head">
            <div><p class="text-xs uppercase tracking-[0.35em] text-[var(--color-gold)]">Mehr</p><h1 class="mt-2 font-serif text-3xl">Verlauf, Diagnose und Profil</h1></div>
            <div class="flex flex-wrap gap-2"><a href="{{ route('taste-profile.show') }}" class="btn-secondary">Genussprofil</a><a href="{{ route('profile.edit') }}" class="btn-secondary">Profil</a></div>
        </div>
        <section class="grid gap-4 md:grid-cols-3">
            <div class="metric-card"><span>Weine in Sammlung</span><strong>{{ $stats['collection'] }}</strong></div>
            <div class="metric-card"><span>Scans insgesamt</span><strong>{{ $stats['scans'] }}</strong></div>
            <div class="metric-card"><span>Fehlgeschlagene Scans</span><strong>{{ $stats['failed_scans'] }}</strong></div>
        </section>
        <section class="card-grid">
            <div class="section-head"><h2>Scanverlauf</h2></div>
            <div class="space-y-3">
                @forelse ($history as $scan)
                    <a href="{{ route('scans.show', $scan) }}" class="flex items-center justify-between gap-3 rounded-[24px] border border-white/8 bg-black/15 px-4 py-4 text-sm">
                        <div>
                            <strong>{{ optional($scan->matchedWineVintage?->wine?->producer)->name ?? 'Scan ohne finalen Treffer' }}</strong>
                            <p class="mt-1 text-[var(--color-mist)]">{{ $scan->statusLabel() }} · {{ $scan->created_at->format(config('app.date_format', 'd.m.Y').' H:i') }}</p>
                        </div>
                        <span class="rounded-full bg-white/6 px-3 py-1">{{ $scan->recognition_confidence ? (int) round($scan->recognition_confidence * 100).' %' : 'n/a' }}</span>
                    </a>
                @empty
                    <div class="empty-card">Noch keine Scans vorhanden.</div>
                @endforelse
            </div>
            {{ $history->links() }}
        </section>
        @if (auth()->user()->isAdmin())
            <section class="card-grid">
                <div class="section-head"><h2>Administration</h2></div>
                <div class="flex flex-col gap-3 md:flex-row">
                    <a href="{{ route('admin.dashboard') }}" class="btn-secondary">Dashboard</a>
                    <a href="{{ route('admin.users.index') }}" class="btn-secondary">Benutzer</a>
                    <a href="{{ route('admin.integrations.index') }}" class="btn-secondary">Integrationen</a>
                    <a href="{{ route('admin.branding.edit', 'general') }}" class="btn-secondary">Erscheinungsbild</a>
                    <a href="{{ route('admin.settings.edit', 'general') }}" class="btn-secondary">Systemeinstellungen</a>
                    <a href="{{ route('admin.system.show') }}" class="btn-secondary">Systemdiagnose</a>
                    <a href="{{ route('admin.audits.index') }}" class="btn-secondary">Aktivitaeten</a>
                </div>
            </section>
        @endif
    </div>
@endsection
