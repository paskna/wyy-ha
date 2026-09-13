@extends('layouts.app', ['title' => 'Benutzer'])

@section('content')
    <div class="space-y-6">
        <div class="section-head">
            <div><p class="text-xs uppercase tracking-[0.35em] text-[var(--color-gold)]">Administration</p><h1 class="mt-2 font-serif text-3xl">Benutzer</h1></div>
            <a href="{{ route('admin.users.create') }}" class="btn-primary">Neuen Benutzer erstellen</a>
        </div>
        @include('admin.partials.nav')
        <form method="get" class="grid gap-3 rounded-[28px] border border-white/8 bg-white/6 p-4 md:grid-cols-4">
            <label class="field"><span>Suche</span><input type="search" name="q" value="{{ request('q') }}" placeholder="Name oder E-Mail"></label>
            <label class="field"><span>Status</span><select name="status"><option value="">Alle</option>@foreach ($statuses as $status)<option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>@endforeach</select></label>
            <label class="field"><span>Rolle</span><select name="role"><option value="">Alle</option>@foreach ($roles as $role)<option value="{{ $role->value }}" @selected(request('role') === $role->value)>{{ $role->label() }}</option>@endforeach</select></label>
            <label class="field"><span>Sortierung</span><select name="sort"><option value="name">Name</option><option value="created_at" @selected(request('sort') === 'created_at')>Erstellt</option><option value="last_login_at" @selected(request('sort') === 'last_login_at')>Letzte Anmeldung</option></select></label>
            <button class="btn-secondary md:col-span-4 md:w-fit">Filtern</button>
        </form>
        <div class="space-y-4">
            @foreach ($users as $user)
                <div class="rounded-[28px] border border-white/8 bg-white/6 p-5">
                    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                        <div>
                            <h2 class="font-serif text-2xl">{{ $user->name }}</h2>
                            <p class="mt-1 text-sm text-[var(--color-mist)]">{{ $user->email }}</p>
                            <p class="mt-2 text-sm text-[var(--color-mist)]">{{ $user->role->label() }} · {{ $user->status->label() }}</p>
                            <p class="mt-2 text-sm text-[var(--color-mist)]">{{ $user->user_wines_count }} Weine · {{ $user->scans_count }} Scans · Letzter Login: {{ $user->last_login_at?->format(config('app.date_format', 'd.m.Y').' H:i') ?? 'Noch nie' }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('admin.users.show', $user) }}" class="btn-secondary">Ansehen</a>
                            <a href="{{ route('admin.users.edit', $user) }}" class="btn-secondary">Bearbeiten</a>
                            <a href="{{ route('admin.users.password.edit', $user) }}" class="btn-secondary">Passwort</a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        {{ $users->links() }}
    </div>
@endsection
