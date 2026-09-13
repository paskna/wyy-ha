@extends('layouts.app', ['title' => 'Administration'])

@section('content')
    <div class="space-y-6">
        <div class="section-head">
            <div><p class="text-xs uppercase tracking-[0.35em] text-[var(--color-gold)]">Administration</p><h1 class="mt-2 font-serif text-3xl">Admin Dashboard</h1></div>
        </div>
        @include('admin.partials.nav')
        <section class="grid gap-4 md:grid-cols-4">
            <div class="metric-card"><span>Benutzer</span><strong>{{ $stats['users'] }}</strong></div>
            <div class="metric-card"><span>Aktive Benutzer</span><strong>{{ $stats['active_users'] }}</strong></div>
            <div class="metric-card"><span>Deaktivierte Benutzer</span><strong>{{ $stats['inactive_users'] }}</strong></div>
            <div class="metric-card"><span>Administratoren</span><strong>{{ $stats['admins'] }}</strong></div>
            <div class="metric-card"><span>Weinstammdaten</span><strong>{{ $stats['wines'] }}</strong></div>
            <div class="metric-card"><span>Jahrgaenge</span><strong>{{ $stats['vintages'] }}</strong></div>
            <div class="metric-card"><span>Persoenliche User-Weine</span><strong>{{ $stats['user_wines'] }}</strong></div>
            <div class="metric-card"><span>Scans</span><strong>{{ $stats['scans'] }}</strong></div>
            <div class="metric-card"><span>Scans heute</span><strong>{{ $stats['scans_today'] }}</strong></div>
            <div class="metric-card"><span>Fehlgeschlagene Scans</span><strong>{{ $stats['failed_scans'] }}</strong></div>
            <div class="metric-card"><span>Scanquote</span><strong>{{ $stats['scan_success_rate'] }} %</strong></div>
            <div class="metric-card"><span>Bilder-Speicher</span><strong>{{ $stats['image_storage_mb'] }} MB</strong></div>
        </section>
        <section class="card-grid">
            <div class="section-head"><h2>Schnellzugriff</h2></div>
            <div class="flex flex-col gap-3 md:flex-row md:flex-wrap">
                <a href="{{ route('admin.users.index') }}" class="btn-secondary">Benutzer verwalten</a>
                <a href="{{ route('admin.users.create') }}" class="btn-secondary">Neuen Benutzer erstellen</a>
                <a href="{{ route('admin.integrations.index') }}" class="btn-secondary">API & Integrationen</a>
                <a href="{{ route('admin.wine-data.index') }}" class="btn-secondary">Wein-Daten</a>
                <a href="{{ route('admin.system.show') }}" class="btn-secondary">Systemdiagnose</a>
                <a href="{{ route('admin.audits.index') }}" class="btn-secondary">Aktivitaeten</a>
            </div>
        </section>
        <section class="grid gap-4 lg:grid-cols-2">
            <div class="card-grid space-y-4">
                <div class="section-head"><h2>API Status</h2></div>
                @foreach ($providers as $provider)
                    <a href="{{ route('admin.integrations.edit', $provider['slug']) }}" class="flex items-center justify-between rounded-[22px] border border-white/8 bg-black/15 px-4 py-4">
                        <div>
                            <strong>{{ $provider['name'] }}</strong>
                            <p class="mt-1 text-sm text-[var(--color-mist)]">{{ $provider['description'] }}</p>
                        </div>
                        <span class="rounded-full px-3 py-1 text-xs {{ in_array($provider['status'], ['active'], true) ? 'bg-emerald-500/20 text-emerald-100' : (in_array($provider['status'], ['error'], true) ? 'bg-rose-500/20 text-rose-100' : 'bg-white/10 text-[var(--color-cream)]') }}">{{ $provider['status_label'] }}</span>
                    </a>
                @endforeach
            </div>
            <div class="card-grid space-y-4">
                <div class="section-head"><h2>Letzte API Fehler</h2></div>
                @forelse ($recentApiErrors as $log)
                    <div class="rounded-[22px] border border-white/8 bg-black/15 px-4 py-4">
                        <strong>{{ $log->provider }}</strong>
                        <p class="mt-1 text-sm text-[var(--color-mist)]">{{ $log->message }} · {{ $log->created_at->format(config('app.date_format', 'd.m.Y').' H:i') }}</p>
                    </div>
                @empty
                    <div class="empty-card">Aktuell keine API Fehler protokolliert.</div>
                @endforelse
            </div>
        </section>
        <section class="card-grid">
            <div class="section-head"><h2>Letzte Benutzeraktivitaeten</h2></div>
            <div class="space-y-3">
                @foreach ($recentUsers as $user)
                    <div class="rounded-[22px] border border-white/8 bg-black/15 px-4 py-3">
                        <strong>{{ $user->name }}</strong>
                        <p class="mt-1 text-sm text-[var(--color-mist)]">{{ $user->email }} · {{ $user->role->label() }} · {{ $user->last_login_at?->format(config('app.date_format', 'd.m.Y').' H:i') ?? 'Noch kein Login' }}</p>
                    </div>
                @endforeach
            </div>
        </section>
        <section class="card-grid">
            <div class="section-head"><h2>Administrative Aktivitaeten</h2></div>
            <div class="space-y-3">
                @forelse ($recentActivities as $activity)
                    <div class="rounded-[22px] border border-white/8 bg-black/15 px-4 py-4">
                        <strong>{{ $activity->action }}</strong>
                        <p class="mt-1 text-sm text-[var(--color-mist)]">{{ $activity->created_at->format(config('app.date_format', 'd.m.Y').' H:i') }} · Admin: {{ $activity->adminUser?->name ?? 'unbekannt' }} · Ziel: {{ $activity->targetUser?->name ?? 'entfernt' }}</p>
                    </div>
                @empty
                    <div class="empty-card">Noch keine administrativen Aenderungen protokolliert.</div>
                @endforelse
            </div>
        </section>
    </div>
@endsection
