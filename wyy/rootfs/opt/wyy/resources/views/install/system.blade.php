@extends('layouts.app', ['title' => 'Systempruefung'])

@section('content')
    <section class="mx-auto max-w-4xl space-y-6">
        <div>
            <p class="text-xs uppercase tracking-[0.35em] text-[var(--color-gold)]">Installation</p>
            <h1 class="mt-2 font-serif text-3xl">Systempruefung</h1>
        </div>

        @include('install.partials.steps', ['steps' => $steps])

        <div class="space-y-3">
            @foreach ($report['checks'] as $check)
                <div class="rounded-[24px] border border-white/10 bg-white/6 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <strong>{{ $check['label'] }}</strong>
                        <span class="rounded-full px-3 py-1 text-xs {{ $check['status'] === 'ok' ? 'bg-emerald-500/20 text-emerald-100' : ($check['status'] === 'warning' ? 'bg-amber-500/20 text-amber-100' : 'bg-rose-500/20 text-rose-100') }}">{{ strtoupper($check['status']) }}</span>
                    </div>
                    <p class="mt-2 text-sm text-[var(--color-mist)]">{{ $check['message'] }}</p>
                </div>
            @endforeach
        </div>

        @if ($report['blocking'])
            <p class="text-sm text-rose-200">Die Installation kann erst fortgesetzt werden, wenn alle roten Fehler behoben sind.</p>
        @else
            <a href="{{ route('install.url') }}" class="nav-pill nav-pill-active inline-flex">Weiter zur URL-Pruefung</a>
        @endif
    </section>
@endsection
