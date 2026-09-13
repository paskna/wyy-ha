@extends('layouts.app', ['title' => 'API Aktivitaeten'])

@section('content')
    <div class="space-y-6">
        <div><p class="text-xs uppercase tracking-[0.35em] text-[var(--color-gold)]">Administration</p><h1 class="mt-2 font-serif text-3xl">API Aktivitaeten</h1></div>
        @include('admin.partials.nav')
        <div class="space-y-3">
            @foreach ($logs as $log)
                <div class="rounded-[24px] border border-white/8 bg-white/6 p-4">
                    <strong>{{ $log->provider }} · {{ $log->action }}</strong>
                    <p class="mt-1 text-sm text-[var(--color-mist)]">{{ $log->created_at->format(config('app.date_format', 'd.m.Y').' H:i') }} · {{ $log->status }} · {{ $log->response_time_ms ? $log->response_time_ms.' ms' : 'n/a' }} · HTTP {{ $log->http_status ?? 'n/a' }}</p>
                    <p class="mt-2 text-sm text-[var(--color-mist)]">{{ $log->message ?? 'Keine Meldung' }}</p>
                </div>
            @endforeach
        </div>
        {{ $logs->links() }}
    </div>
@endsection
