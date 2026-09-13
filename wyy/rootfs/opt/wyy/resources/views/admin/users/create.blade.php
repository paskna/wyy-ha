@extends('layouts.app', ['title' => 'Benutzer erstellen'])

@section('content')
    <div class="space-y-6">
        <div><p class="text-xs uppercase tracking-[0.35em] text-[var(--color-gold)]">Administration</p><h1 class="mt-2 font-serif text-3xl">Benutzer erstellen</h1></div>
        @include('admin.users.partials.form', ['action' => route('admin.users.store'), 'method' => 'POST', 'user' => null, 'roles' => $roles, 'statuses' => $statuses])
    </div>
@endsection
