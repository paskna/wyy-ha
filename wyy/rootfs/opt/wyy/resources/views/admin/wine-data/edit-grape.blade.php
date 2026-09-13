@extends('layouts.app', ['title' => 'Rebsorte bearbeiten'])

@section('content')
    <form method="post" action="{{ route('admin.wine-data.grapes.update', $grape) }}" class="space-y-6">
        @csrf
        @method('patch')
        <div><p class="text-xs uppercase tracking-[0.35em] text-[var(--color-gold)]">Wein-Daten</p><h1 class="mt-2 font-serif text-3xl">Rebsorte bearbeiten</h1></div>
        @include('admin.partials.nav')
        <div class="card-grid">
            <label class="field"><span>Name</span><input type="text" name="name" value="{{ old('name', $grape->name) }}"></label>
        </div>
        <button class="btn-primary">Speichern</button>
    </form>
@endsection
