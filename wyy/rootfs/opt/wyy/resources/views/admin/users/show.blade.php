@extends('layouts.app', ['title' => 'Benutzerdetail'])

@section('content')
    <div class="space-y-6">
        <div class="section-head">
            <div><p class="text-xs uppercase tracking-[0.35em] text-[var(--color-gold)]">Administration</p><h1 class="mt-2 font-serif text-3xl">{{ $user->name }}</h1></div>
            <a href="{{ route('admin.users.edit', $user) }}" class="btn-secondary">Bearbeiten</a>
        </div>
        <section class="card-grid">
            <div class="grid gap-4 text-sm md:grid-cols-2">
                <div><span class="text-[var(--color-mist)]">E-Mail</span><strong class="block">{{ $user->email }}</strong></div>
                <div><span class="text-[var(--color-mist)]">Rolle</span><strong class="block">{{ $user->role->label() }}</strong></div>
                <div><span class="text-[var(--color-mist)]">Status</span><strong class="block">{{ $user->status->label() }}</strong></div>
                <div><span class="text-[var(--color-mist)]">Erstellt</span><strong class="block">{{ $user->created_at->format(config('app.date_format', 'd.m.Y').' H:i') }}</strong></div>
                <div><span class="text-[var(--color-mist)]">Letzte Anmeldung</span><strong class="block">{{ $user->last_login_at?->format(config('app.date_format', 'd.m.Y').' H:i') ?? 'Noch nie' }}</strong></div>
                <div><span class="text-[var(--color-mist)]">Gespeicherte Weine</span><strong class="block">{{ $user->user_wines_count }}</strong></div>
                <div><span class="text-[var(--color-mist)]">Top-Weine</span><strong class="block">{{ $user->top_wines_count }}</strong></div>
                <div><span class="text-[var(--color-mist)]">Scans</span><strong class="block">{{ $user->scans_count }}</strong></div>
            </div>
        </section>
        <section class="card-grid">
            <div class="section-head"><h2>Aktionen</h2></div>
            <div class="flex flex-col gap-3 md:flex-row">
                <form method="post" action="{{ route('admin.users.status', $user) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="{{ $user->status->value === 'active' ? 'inactive' : 'active' }}">
                    <button class="btn-secondary">{{ $user->status->value === 'active' ? 'Deaktivieren' : 'Aktivieren' }}</button>
                </form>
                <a href="{{ route('admin.users.password.edit', $user) }}" class="btn-secondary">Passwort zuruecksetzen</a>
                <form method="post" action="{{ route('admin.users.sessions.destroy', $user) }}" onsubmit="return confirm('Alle Sitzungen dieses Benutzers wirklich beenden?');">
                    @csrf
                    <button class="btn-secondary">Alle Sitzungen beenden</button>
                </form>
                <form method="post" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Benutzer wirklich deaktivieren und archivieren?');">
                    @csrf
                    @method('DELETE')
                    <button class="btn-secondary">Loeschen</button>
                </form>
            </div>
        </section>
    </div>
@endsection
