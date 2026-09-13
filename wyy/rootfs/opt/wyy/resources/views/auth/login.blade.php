@extends('layouts.app', ['title' => 'Login'])

@section('content')
    @php($loginBranding = $branding['login'] ?? [])
    <div class="mx-auto">
        <form method="post" action="{{ route('login.store') }}" class="space-y-4">
            @csrf
            <label class="field">
                <span>E-Mail</span>
                <input type="email" name="email" value="{{ old('email') }}" required>
                @error('email')<small>{{ $message }}</small>@enderror
            </label>
            <label class="field">
                <span>Passwort</span>
                <input type="password" name="password" required>
            </label>
            @if ($persistentLoginEnabled ?? true)
                <label class="flex items-center gap-3 text-sm text-[var(--color-mist)]">
                    <input type="checkbox" name="remember" value="1" class="size-4 rounded border-white/15 bg-transparent" @checked(old('remember', $rememberByDefault ?? true))>
                    <span>Angemeldet bleiben</span>
                </label>
                <p class="text-xs text-[var(--color-mist)]">Fuer die installierte App bleibt deine Anmeldung standardmaessig bis zu {{ $rememberDays ?? 180 }} Tage bestehen, sofern du dich nicht aktiv abmeldest.</p>
            @endif
            <a href="{{ route('password.request') }}" class="text-sm text-[var(--color-gold)]">{{ $loginBranding['forgot_password_text'] ?? 'Passwort vergessen' }}</a>
            <button class="btn-primary w-full">{{ $loginBranding['button_text'] ?? 'Anmelden' }}</button>
            @if (!empty($loginBranding['footer_text']))
                <p class="pt-2 text-center text-xs text-[var(--color-mist)]">{{ $loginBranding['footer_text'] }}</p>
            @endif
        </form>
    </div>
@endsection
