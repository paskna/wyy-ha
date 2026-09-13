@extends('layouts.app', ['title' => 'WYY einrichten'])

@section('content')
    <div class="mx-auto">
        <p class="mb-5 text-sm leading-6 text-[var(--color-mist)]">Die technische Einrichtung ist abgeschlossen. Erstelle jetzt das erste WYY-Administratorkonto.</p>
        <form method="post" action="{{ route('ha.setup.store') }}" class="space-y-4">
            @csrf
            <label class="field">
                <span>Name</span>
                <input name="name" value="{{ old('name') }}" required autocomplete="name">
            </label>
            <label class="field">
                <span>E-Mail</span>
                <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email">
            </label>
            <label class="field">
                <span>Passwort</span>
                <input type="password" name="password" required autocomplete="new-password">
            </label>
            <label class="field">
                <span>Passwort bestaetigen</span>
                <input type="password" name="password_confirmation" required autocomplete="new-password">
            </label>
            <label class="field">
                <span>App-Name <small>(optional)</small></span>
                <input name="app_name" value="{{ old('app_name', 'WYY') }}" maxlength="80">
            </label>
            <button class="btn-primary w-full">WYY einrichten</button>
        </form>
    </div>
@endsection
