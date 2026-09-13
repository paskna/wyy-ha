@extends('layouts.app', ['title' => 'Wein bearbeiten'])

@section('content')
    <form method="post" action="{{ route('admin.wine-data.wines.update', $wine) }}" class="space-y-6">
        @csrf
        @method('patch')
        <div><p class="text-xs uppercase tracking-[0.35em] text-[var(--color-gold)]">Wein-Daten</p><h1 class="mt-2 font-serif text-3xl">Wein bearbeiten</h1></div>
        @include('admin.partials.nav')
        <div class="card-grid grid gap-4 md:grid-cols-2">
            <label class="field"><span>Name</span><input type="text" name="name" value="{{ old('name', $wine->name) }}"></label>
            <label class="field"><span>Typ</span><input type="text" name="wine_type" value="{{ old('wine_type', $wine->wine_type) }}"></label>
            <label class="field"><span>Land</span><input type="text" name="country" value="{{ old('country', $wine->country) }}"></label>
            <label class="field"><span>Region</span><input type="text" name="region" value="{{ old('region', $wine->region) }}"></label>
            <label class="field"><span>Subregion</span><input type="text" name="subregion" value="{{ old('subregion', $wine->subregion) }}"></label>
            <label class="field"><span>Appellation</span><input type="text" name="appellation" value="{{ old('appellation', $wine->appellation) }}"></label>
            <label class="field md:col-span-2"><span>Beschreibung</span><textarea name="description" rows="5">{{ old('description', $wine->description) }}</textarea></label>
        </div>
        <button class="btn-primary">Speichern</button>
    </form>
@endsection
