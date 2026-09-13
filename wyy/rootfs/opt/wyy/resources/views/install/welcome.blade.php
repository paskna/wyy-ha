@extends('layouts.app', ['title' => 'Wein-App installieren'])

@section('content')
    <section class="mx-auto max-w-3xl space-y-6">
        <div>
            <p class="text-xs uppercase tracking-[0.35em] text-[var(--color-gold)]">Installation</p>
            <h1 class="mt-2 font-serif text-3xl">Wein-App installieren</h1>
            <p class="mt-3 text-sm text-[var(--color-mist)]">Dieser Assistent richtet die Anwendung auf diesem Server ein und funktioniert auch in Unterverzeichnissen.</p>
        </div>

        @include('install.partials.steps', ['steps' => $steps])

        @if ($installed)
            <div class="rounded-[24px] border border-emerald-400/20 bg-emerald-500/10 p-5 text-sm text-emerald-100">
                Die Anwendung ist bereits installiert.
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('dashboard') }}" class="nav-pill nav-pill-active">Zur Anwendung</a>
                <a href="{{ route('login') }}" class="nav-pill">Login</a>
            </div>
        @else
            <div class="rounded-[28px] border border-white/10 bg-white/6 p-6">
                <ul class="space-y-2 text-sm text-[var(--color-mist)]">
                    <li>Version: Laravel {{ app()->version() }}</li>
                    <li>PHP Mindestversion: 8.3</li>
                    <li>Keine SSH-, Composer- oder Node-Ausfuehrung auf dem Hosting notwendig</li>
                </ul>
            </div>
            <a href="{{ route('install.system') }}" class="nav-pill nav-pill-active inline-flex">Installation starten</a>
        @endif
    </section>
@endsection
