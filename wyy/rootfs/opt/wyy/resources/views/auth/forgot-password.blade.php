@extends('layouts.app', ['title' => 'Passwort vergessen'])

@section('content')
    <div class="mx-auto max-w-md pt-12">
        <h1 class="font-serif text-3xl">Passwort vergessen</h1>
        <p class="mt-4 text-sm leading-6 text-[var(--color-mist)]">Wenn fuer die App ein Mailversand konfiguriert ist, wird ein Reset-Link vorbereitet. Alternativ kann ein Administrator dein Passwort zuruecksetzen.</p>
        <form method="post" action="{{ route('password.email') }}" class="mt-6 card-grid">
            @csrf
            <label class="field">
                <span>E-Mail</span>
                <input type="email" name="email" value="{{ old('email') }}" required>
                @error('email')<small>{{ $message }}</small>@enderror
            </label>
            <button class="btn-primary">Reset-Link anfordern</button>
        </form>
    </div>
@endsection
