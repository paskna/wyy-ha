@extends('layouts.app', ['title' => 'Passwort zuruecksetzen'])

@section('content')
    <div class="space-y-6">
        <div><p class="text-xs uppercase tracking-[0.35em] text-[var(--color-gold)]">Administration</p><h1 class="mt-2 font-serif text-3xl">Temporäres Passwort fuer {{ $user->name }}</h1></div>
        <form method="post" action="{{ route('admin.users.password.update', $user) }}" class="card-grid">
            @csrf
            @method('PATCH')
            <label class="field"><span>Neues Passwort</span><input type="password" name="password" required></label>
            <label class="field"><span>Neues Passwort bestaetigen</span><input type="password" name="password_confirmation" required></label>
            <button class="btn-primary">Passwort setzen</button>
        </form>
    </div>
@endsection
