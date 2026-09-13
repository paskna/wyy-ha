@extends('layouts.app', ['title' => 'Profil'])

@section('content')
    <div class="space-y-6">
        <div>
            <p class="text-xs uppercase tracking-[0.35em] text-[var(--color-gold)]">Profil</p>
            <h1 class="mt-3 font-serif text-3xl">Zugang und Kontoeinstellungen</h1>
        </div>
        <form method="post" action="{{ route('profile.update') }}" class="card-grid">
            @csrf
            @method('PATCH')
            <label class="field"><span>Name</span><input type="text" name="name" value="{{ old('name', $user->name) }}" required></label>
            <label class="field"><span>E-Mail</span><input type="email" name="email" value="{{ old('email', $user->email) }}" required></label>
            <label class="field"><span>Rolle</span><input type="text" value="{{ $user->role->label() }}" readonly></label>
            <button class="btn-primary">Profil speichern</button>
        </form>
        <form method="post" action="{{ route('profile.password') }}" class="card-grid">
            @csrf
            @method('PATCH')
            <label class="field"><span>Aktuelles Passwort</span><input type="password" name="current_password" required></label>
            <label class="field"><span>Neues Passwort</span><input type="password" name="password" required></label>
            <label class="field"><span>Neues Passwort bestaetigen</span><input type="password" name="password_confirmation" required></label>
            <button class="btn-secondary">Passwort aendern</button>
        </form>
        <form method="post" action="{{ route('logout') }}">
            @csrf
            <button class="btn-secondary">Abmelden</button>
        </form>
        <section class="card-grid space-y-4">
            <div class="section-head"><h2>Geraete &amp; Sitzungen</h2></div>
            @if ($sessionManagementAvailable)
                <div class="space-y-3">
                    @forelse ($sessions as $session)
                        <div class="rounded-[24px] border border-white/8 bg-black/15 px-4 py-4 text-sm">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <strong class="block text-[var(--color-cream)]">{{ $session['device'] }} · {{ $session['browser'] }}</strong>
                                    <span class="text-[var(--color-mist)]">
                                        Letzte Aktivitaet: {{ $session['last_activity']->format(config('app.date_format', 'd.m.Y').' H:i') }}
                                        @if ($session['ip_address'])
                                            · IP {{ $session['ip_address'] }}
                                        @endif
                                    </span>
                                </div>
                                @if ($session['is_current'])
                                    <span class="rounded-full border border-emerald-400/20 bg-emerald-500/10 px-3 py-1 text-xs text-emerald-100">Dieses Geraet</span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-[var(--color-mist)]">Zurzeit sind keine gespeicherten Sitzungen verfuegbar.</p>
                    @endforelse
                </div>
                <div class="flex flex-wrap gap-3">
                    <form method="post" action="{{ route('profile.sessions.others.destroy') }}">
                        @csrf
                        <button class="btn-secondary">Andere Sitzungen abmelden</button>
                    </form>
                    <form method="post" action="{{ route('profile.sessions.destroy') }}" onsubmit="return confirm('Alle Geraete wirklich abmelden?');">
                        @csrf
                        <button class="btn-secondary">Alle Geraete abmelden</button>
                    </form>
                </div>
            @else
                <p class="text-sm text-[var(--color-mist)]">Die Sitzungsverwaltung ist mit der aktuellen Session-Konfiguration nicht verfuegbar.</p>
            @endif
        </section>
    </div>
@endsection
