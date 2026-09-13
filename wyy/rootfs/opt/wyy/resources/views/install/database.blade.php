@extends('layouts.app', ['title' => 'Datenbank'])

@section('content')
    <section class="mx-auto max-w-3xl space-y-6">
        <div>
            <p class="text-xs uppercase tracking-[0.35em] text-[var(--color-gold)]">Installation</p>
            <h1 class="mt-2 font-serif text-3xl">Datenbank</h1>
        </div>

        @include('install.partials.steps', ['steps' => $steps])

        <form method="post" action="{{ route('install.database.store') }}" class="grid gap-4 rounded-[28px] border border-white/10 bg-white/6 p-6 md:grid-cols-2">
            @csrf
            <div>
                <label for="host" class="mb-2 block text-sm font-medium">Host</label>
                <input id="host" name="host" value="{{ old('host', $values['host']) }}" class="w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-sm" required>
            </div>
            <div>
                <label for="port" class="mb-2 block text-sm font-medium">Port</label>
                <input id="port" name="port" type="number" value="{{ old('port', $values['port']) }}" class="w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-sm" required>
            </div>
            <div>
                <label for="database" class="mb-2 block text-sm font-medium">Datenbank</label>
                <input id="database" name="database" value="{{ old('database', $values['database']) }}" class="w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-sm" required>
            </div>
            <div>
                <label for="username" class="mb-2 block text-sm font-medium">Benutzer</label>
                <input id="username" name="username" value="{{ old('username', $values['username']) }}" class="w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-sm" required>
            </div>
            <div class="md:col-span-2">
                <label for="password" class="mb-2 block text-sm font-medium">Passwort</label>
                <input id="password" name="password" type="password" value="{{ old('password', $values['password']) }}" class="w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-sm">
            </div>
            <div class="md:col-span-2">
                <button type="submit" class="nav-pill nav-pill-active">Verbindung testen und weiter</button>
            </div>
        </form>
    </section>
@endsection
