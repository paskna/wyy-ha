<form method="post" action="{{ $action }}" class="card-grid">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif
    <div class="grid gap-4 md:grid-cols-2">
        <label class="field"><span>Name</span><input type="text" name="name" value="{{ old('name', $user?->name) }}" required></label>
        <label class="field"><span>E-Mail</span><input type="email" name="email" value="{{ old('email', $user?->email) }}" required></label>
        <label class="field"><span>Rolle</span><select name="role">@foreach ($roles as $role)<option value="{{ $role->value }}" @selected(old('role', $user?->role?->value ?? 'user') === $role->value)>{{ $role->label() }}</option>@endforeach</select></label>
        <label class="field"><span>Status</span><select name="status">@foreach ($statuses as $status)<option value="{{ $status->value }}" @selected(old('status', $user?->status?->value ?? 'active') === $status->value)>{{ $status->label() }}</option>@endforeach</select></label>
        @if (! $user)
            <label class="field"><span>Passwort</span><input type="password" name="password" required></label>
            <label class="field"><span>Passwort bestaetigen</span><input type="password" name="password_confirmation" required></label>
            <label class="field md:col-span-2">
                <span>Erster Login</span>
                <label class="inline-flex items-center gap-2 text-sm text-[var(--color-mist)]">
                    <input type="checkbox" name="must_change_password" value="1" @checked(old('must_change_password', false))>
                    <span>Benutzer muss Passwort beim ersten Login aendern</span>
                </label>
            </label>
        @endif
    </div>
    <button class="btn-primary">{{ $user ? 'Benutzer speichern' : 'Benutzer erstellen' }}</button>
</form>
