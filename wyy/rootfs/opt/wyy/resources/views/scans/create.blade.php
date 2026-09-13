@extends('layouts.app', ['title' => 'Scannen'])

@section('content')
    <div class="space-y-6">
        <div class="rounded-[36px] border border-white/8 bg-white/6 p-6">
            <p class="text-xs uppercase tracking-[0.35em] text-[var(--color-gold)]">Scannen</p>
            <h1 class="mt-3 font-serif text-3xl">Etikett fotografieren</h1>
            <p class="mt-3 text-sm leading-6 text-[var(--color-mist)]">Fotografiere das vordere Etikett moeglichst gerade und vollstaendig. Auf Smartphones wird die Rueckkamera bevorzugt.</p>
            <p class="mt-2 text-sm leading-6 text-[var(--color-mist)]">Unterstuetzt werden JPEG, PNG und WEBP. Auf iPhones muessen HEIC- oder HEIF-Bilder gegebenenfalls zuerst umgewandelt werden.</p>
        </div>
        <form method="post" action="{{ route('scans.store') }}" enctype="multipart/form-data" class="card-grid">
            @csrf
            <label class="field">
                <span>Foto aufnehmen oder aus Mediathek waehlen</span>
                <input type="file" name="image" accept="image/*" capture="environment" required>
                @error('image')<small>{{ $message }}</small>@enderror
            </label>
            <button class="btn-primary">Scan starten</button>
        </form>
    </div>
@endsection
