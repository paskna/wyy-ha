@extends('layouts.app', ['title' => 'Installation abgeschlossen'])

@section('content')
    <section class="mx-auto max-w-3xl space-y-6">
        <div>
            <p class="text-xs uppercase tracking-[0.35em] text-[var(--color-gold)]">Installation</p>
            <h1 class="mt-2 font-serif text-3xl">Installation erfolgreich abgeschlossen</h1>
            <p class="mt-3 text-sm text-[var(--color-mist)]">Die Wein-App ist jetzt einsatzbereit.</p>
        </div>

        <div class="flex flex-wrap gap-3">
            <a href="{{ route('dashboard') }}" class="nav-pill nav-pill-active">App oeffnen</a>
            <a href="{{ route('admin.dashboard') }}" class="nav-pill">Administration oeffnen</a>
        </div>
    </section>
@endsection
