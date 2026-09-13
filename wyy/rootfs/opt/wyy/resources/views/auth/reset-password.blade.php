@extends('layouts.app', ['title' => 'Passwort zuruecksetzen'])

@section('content')
    <div class="mx-auto max-w-md pt-12">
        <h1 class="font-serif text-3xl">Neues Passwort vergeben</h1>
        <form method="post" action="{{ route('password.update') }}" class="mt-6 card-grid">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <label class="field">
                <span>E-Mail</span>
                <input type="email" name="email" value="{{ old('email', $email) }}" required>
            </label>
            <label class="field">
                <span>Neues Passwort</span>
                <input type="password" name="password" required>
            </label>
            <label class="field">
                <span>Neues Passwort bestaetigen</span>
                <input type="password" name="password_confirmation" required>
            </label>
            <button class="btn-primary">Passwort speichern</button>
        </form>
    </div>
@endsection
