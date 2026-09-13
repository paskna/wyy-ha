@extends('layouts.app', ['title' => 'Jahrgang bearbeiten'])

@section('content')
    <form method="post" action="{{ route('admin.wine-data.vintages.update', $vintage) }}" class="space-y-6">
        @csrf
        @method('patch')
        <div><p class="text-xs uppercase tracking-[0.35em] text-[var(--color-gold)]">Wein-Daten</p><h1 class="mt-2 font-serif text-3xl">Jahrgang bearbeiten</h1></div>
        @include('admin.partials.nav')
        <div class="card-grid grid gap-4 md:grid-cols-2">
            <label class="field"><span>Jahrgang</span><input type="text" name="vintage" value="{{ old('vintage', $vintage->vintage) }}"></label>
            <label class="field"><span>Farbe</span><input type="text" name="colour" value="{{ old('colour', $vintage->colour) }}"></label>
            <label class="field"><span>Body</span><input type="number" step="0.01" name="body" value="{{ old('body', $vintage->body) }}"></label>
            <label class="field"><span>Tannin</span><input type="number" step="0.01" name="tannin" value="{{ old('tannin', $vintage->tannin) }}"></label>
            <label class="field"><span>Saeure</span><input type="number" step="0.01" name="acidity" value="{{ old('acidity', $vintage->acidity) }}"></label>
            <label class="field"><span>Suesse</span><input type="number" step="0.01" name="sweetness" value="{{ old('sweetness', $vintage->sweetness) }}"></label>
            <label class="field"><span>Holz</span><input type="number" step="0.01" name="oak" value="{{ old('oak', $vintage->oak) }}"></label>
            <label class="field md:col-span-2"><span>Beschreibung</span><textarea name="description" rows="4">{{ old('description', $vintage->description) }}</textarea></label>
            <label class="field md:col-span-2"><span>Pairing</span><textarea name="pairing_suggestions" rows="4">{{ old('pairing_suggestions', $vintage->pairing_suggestions) }}</textarea></label>
        </div>
        <button class="btn-primary">Speichern</button>
    </form>
@endsection
