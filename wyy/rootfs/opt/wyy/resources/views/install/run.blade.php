@extends('layouts.app', ['title' => 'Installation ausfuehren'])

@section('content')
    <section class="mx-auto max-w-3xl space-y-6">
        <div>
            <p class="text-xs uppercase tracking-[0.35em] text-[var(--color-gold)]">Installation</p>
            <h1 class="mt-2 font-serif text-3xl">Installation ausfuehren</h1>
            <p class="mt-3 text-sm text-[var(--color-mist)]">Jetzt werden APP_KEY erzeugt, Konfiguration gespeichert, Migrationen ausgefuehrt und der Administrator erstellt.</p>
        </div>

        @include('install.partials.steps', ['steps' => $steps])

        <form method="post" action="{{ route('install.perform') }}" class="rounded-[28px] border border-white/10 bg-white/6 p-6">
            @csrf
            <button type="submit" class="nav-pill nav-pill-active">Installation abschliessen</button>
        </form>
    </section>
@endsection
