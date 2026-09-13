@extends('layouts.app', ['title' => 'Start'])

@section('content')
    <section class="space-y-6">
        <div class="rounded-[36px] border border-white/8 bg-[linear-gradient(160deg,_rgba(124,43,36,0.38),_rgba(255,255,255,0.04))] p-6">
            <p class="text-xs uppercase tracking-[0.35em] text-[var(--color-gold)]">Start</p>
            <h1 class="mt-3 font-serif text-4xl leading-tight">Wein scannen, wiedererkennen und mit deinem Geschmack abgleichen.</h1>
            <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                <a href="{{ route('scans.create') }}" class="btn-primary justify-center">Wein scannen</a>
                <a href="{{ route('collection.index') }}" class="btn-secondary justify-center">Sammlung oeffnen</a>
            </div>
            <form method="get" action="{{ route('collection.index') }}" class="mt-5">
                <label class="field">
                    <span>Welchen Wein suchst du?</span>
                    <input type="search" name="q" placeholder="Produzent, Region, Jahrgang oder Notiz">
                </label>
            </form>
        </div>
        <section class="space-y-4">
            <div class="section-head"><h2>Zuletzt gescannt</h2></div>
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @forelse ($recent as $item)
                    <x-wine-card :item="$item" />
                @empty
                    <div class="empty-card">Noch keine Scans vorhanden.</div>
                @endforelse
            </div>
        </section>
        <section class="space-y-4">
            <div class="section-head"><h2>Meine Top-Weine</h2></div>
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @forelse ($top as $item)
                    <x-wine-card :item="$item" />
                @empty
                    <div class="empty-card">Markiere deinen ersten Top-Wein, damit dieser Bereich lebendig wird.</div>
                @endforelse
            </div>
        </section>
        <section class="space-y-4">
            <div class="section-head"><h2>Dein Genussprofil</h2><a href="{{ route('taste-profile.show') }}" class="btn-secondary">Profil ansehen</a></div>
            <div class="card-grid"><div class="flex flex-wrap items-center justify-between gap-3"><div><p class="text-lg font-medium">{{ $profileSummary['status'] }}</p><p class="text-sm text-[var(--color-mist)]">{{ $profileSummary['rated_count'] }} Weine bewertet · Confidence {{ $profileSummary['confidence_label'] }}</p></div><span class="rounded-full bg-[var(--color-burgundy-soft)] px-3 py-1 text-sm">{{ $profileSummary['top_count'] }} Top-Weine</span></div></div>
        </section>
        <section class="space-y-4">
            <div class="section-head"><h2>Empfehlungen fuer mich</h2></div>
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @forelse ($recommendations as $item)
                    <x-wine-card :item="$item" />
                @empty
                    <div class="empty-card">Bewerte noch ein paar Weine, damit persoenliche Empfehlungen sinnvoll werden.</div>
                @endforelse
            </div>
        </section>
    </section>
@endsection
