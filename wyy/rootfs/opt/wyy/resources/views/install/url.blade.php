@extends('layouts.app', ['title' => 'Installations-URL'])

@section('content')
    <section class="mx-auto max-w-3xl space-y-6">
        <div>
            <p class="text-xs uppercase tracking-[0.35em] text-[var(--color-gold)]">Installation</p>
            <h1 class="mt-2 font-serif text-3xl">URL-Pruefung</h1>
        </div>

        @include('install.partials.steps', ['steps' => $steps])

        <div class="rounded-[24px] border border-white/10 bg-white/6 p-5 text-sm text-[var(--color-mist)]">
            <p>Erkannte URL: <strong class="text-[var(--color-cream)]">{{ $detected['app_url'] }}</strong></p>
            <p class="mt-2">Installationspfad: <strong class="text-[var(--color-cream)]">{{ $detected['base_path'] ?: '/' }}</strong></p>
            <p class="mt-2">HTTPS: <strong class="text-[var(--color-cream)]">{{ $detected['https'] ? 'Ja' : 'Nein' }}</strong></p>
            <p class="mt-2">Host: <strong class="text-[var(--color-cream)]">{{ $detected['host'] }}</strong></p>
        </div>

        <form method="post" action="{{ route('install.url.store') }}" class="space-y-4 rounded-[28px] border border-white/10 bg-white/6 p-6">
            @csrf
            <div>
                <label for="app_url" class="mb-2 block text-sm font-medium">Basis-URL</label>
                <input id="app_url" name="app_url" type="url" value="{{ old('app_url', $values['app_url']) }}" class="w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-sm text-[var(--color-cream)]" required>
            </div>
            <button type="submit" class="nav-pill nav-pill-active">URL uebernehmen</button>
        </form>
    </section>
@endsection
