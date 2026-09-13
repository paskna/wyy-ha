@extends('layouts.app', ['title' => 'Grundeinstellungen'])

@section('content')
    <section class="mx-auto max-w-3xl space-y-6">
        <div>
            <p class="text-xs uppercase tracking-[0.35em] text-[var(--color-gold)]">Installation</p>
            <h1 class="mt-2 font-serif text-3xl">Grundeinstellungen</h1>
        </div>

        @include('install.partials.steps', ['steps' => $steps])

        @unless ($canWriteConfig)
            <div class="rounded-[24px] border border-amber-400/20 bg-amber-500/10 p-4 text-sm text-amber-100">
                Weder <code>.env</code> noch der alternative Runtime-Konfigurationspfad sind aktuell sicher beschreibbar. Bitte die Schreibrechte pruefen.
            </div>
        @endunless

        <form method="post" action="{{ route('install.settings.store') }}" class="space-y-4 rounded-[28px] border border-white/10 bg-white/6 p-6">
            @csrf
            <div>
                <label for="app_name" class="mb-2 block text-sm font-medium">App Name</label>
                <input id="app_name" name="app_name" value="{{ old('app_name', $values['app_name']) }}" class="w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-sm" required>
            </div>
            <div>
                <label for="timezone" class="mb-2 block text-sm font-medium">Zeitzone</label>
                <input id="timezone" name="timezone" value="{{ old('timezone', $values['timezone']) }}" class="w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-sm" required>
            </div>
            <div>
                <label for="language" class="mb-2 block text-sm font-medium">Sprache</label>
                <select id="language" name="language" class="w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-sm">
                    <option value="de" @selected(old('language', $values['language']) === 'de')>Deutsch</option>
                    <option value="en" @selected(old('language', $values['language']) === 'en')>English</option>
                </select>
            </div>
            <div>
                <label for="from_address" class="mb-2 block text-sm font-medium">Absender E-Mail (optional)</label>
                <input id="from_address" name="from_address" type="email" value="{{ old('from_address', $values['from_address']) }}" class="w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-sm">
            </div>
            <button type="submit" class="nav-pill nav-pill-active">Weiter zur Installation</button>
        </form>
    </section>
@endsection
