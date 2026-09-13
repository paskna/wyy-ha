@extends('layouts.app', ['title' => 'Benutzer bearbeiten'])

@section('content')
    <div class="space-y-6">
        <div><p class="text-xs uppercase tracking-[0.35em] text-[var(--color-gold)]">Administration</p><h1 class="mt-2 font-serif text-3xl">Benutzer bearbeiten</h1></div>
        @include('admin.users.partials.form', ['action' => route('admin.users.update', $user), 'method' => 'PATCH', 'user' => $user, 'roles' => $roles, 'statuses' => $statuses])
    </div>
@endsection
