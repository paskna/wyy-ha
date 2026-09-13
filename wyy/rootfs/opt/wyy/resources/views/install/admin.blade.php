@extends('layouts.app', ['title' => 'Administrator'])

@section('content')
    <section class="mx-auto max-w-3xl space-y-6">
        <div>
            <p class="text-xs uppercase tracking-[0.35em] text-[var(--color-gold)]">Installation</p>
            <h1 class="mt-2 font-serif text-3xl">Administrator erstellen</h1>
        </div>

        @include('install.partials.steps', ['steps' => $steps])

        <form method="post" action="{{ route('install.admin.store') }}" class="space-y-4 rounded-[28px] border border-white/10 bg-white/6 p-6">
            @csrf
            <div>
                <label for="name" class="mb-2 block text-sm font-medium">Name</label>
                <input id="name" name="name" value="{{ old('name', $values['name']) }}" class="w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-sm" required>
            </div>
            <div>
                <label for="email" class="mb-2 block text-sm font-medium">E-Mail</label>
                <input id="email" name="email" type="email" value="{{ old('email', $values['email']) }}" class="w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-sm" required>
            </div>
            <div>
                <label for="password" class="mb-2 block text-sm font-medium">Passwort</label>
                <input id="password" name="password" type="password" class="w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-sm" required>
            </div>
            <div>
                <label for="password_confirmation" class="mb-2 block text-sm font-medium">Passwort bestaetigen</label>
                <input id="password_confirmation" name="password_confirmation" type="password" class="w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-sm" required>
            </div>
            <button type="submit" class="nav-pill nav-pill-active">Weiter</button>
        </form>
    </section>
@endsection
