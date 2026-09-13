@extends('layouts.app', ['title' => 'Admin Aktivitaeten'])

@section('content')
    <div class="space-y-6">
        <div><p class="text-xs uppercase tracking-[0.35em] text-[var(--color-gold)]">Administration</p><h1 class="mt-2 font-serif text-3xl">Aktivitaeten</h1></div>
        @include('admin.partials.nav')
        <div class="space-y-3">
            @foreach ($logs as $log)
                <div class="rounded-[24px] border border-white/8 bg-white/6 p-4">
                    <strong>{{ $log->action }}</strong>
                    <p class="mt-1 text-sm text-[var(--color-mist)]">{{ $log->created_at->format(config('app.date_format', 'd.m.Y').' H:i') }} · Admin: {{ $log->adminUser?->name ?? 'unbekannt' }} · Benutzer: {{ $log->targetUser?->name ?? 'entfernt' }}</p>
                    @if ($log->metadata)
                        <p class="mt-2 text-xs text-[var(--color-mist)]">{{ collect($log->metadata)->map(fn ($value, $key) => $key.': '.(is_scalar($value) ? $value : json_encode($value)))->implode(' · ') }}</p>
                    @endif
                </div>
            @endforeach
        </div>
        {{ $logs->links() }}
    </div>
@endsection
