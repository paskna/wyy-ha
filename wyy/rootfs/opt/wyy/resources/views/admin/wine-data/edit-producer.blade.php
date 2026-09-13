@extends('layouts.app', ['title' => 'Produzent bearbeiten'])

@section('content')
    <form method="post" action="{{ route('admin.wine-data.producers.update', $producer) }}" class="space-y-6">
        @csrf
        @method('patch')
        <div><p class="text-xs uppercase tracking-[0.35em] text-[var(--color-gold)]">Wein-Daten</p><h1 class="mt-2 font-serif text-3xl">Produzent bearbeiten</h1></div>
        @include('admin.partials.nav')
        <div class="card-grid grid gap-4 md:grid-cols-2">
            <label class="field"><span>Name</span><input type="text" name="name" value="{{ old('name', $producer->name) }}"></label>
            <label class="field"><span>Land</span><input type="text" name="country" value="{{ old('country', $producer->country) }}"></label>
            <label class="field"><span>Region</span><input type="text" name="region" value="{{ old('region', $producer->region) }}"></label>
            <label class="field"><span>Website</span><input type="url" name="website" value="{{ old('website', $producer->website) }}"></label>
            <label class="field md:col-span-2"><span>Beschreibung</span><textarea name="description" rows="5">{{ old('description', $producer->description) }}</textarea></label>
        </div>
        <button class="btn-primary">Speichern</button>
    </form>
@endsection
