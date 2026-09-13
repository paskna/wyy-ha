@php($items = [
    ['label' => 'Dashboard', 'route' => route('admin.dashboard'), 'active' => request()->routeIs('admin.dashboard')],
    ['label' => 'Benutzer', 'route' => route('admin.users.index'), 'active' => request()->routeIs('admin.users.*')],
    ['label' => 'API & Integrationen', 'route' => route('admin.integrations.index'), 'active' => request()->routeIs('admin.integrations.*') || request()->routeIs('admin.api-activities.*')],
    ['label' => 'Wein-Daten', 'route' => route('admin.wine-data.index'), 'active' => request()->routeIs('admin.wine-data.*')],
    ['label' => 'Erscheinungsbild', 'route' => route('admin.branding.edit', 'general'), 'active' => request()->routeIs('admin.branding.*')],
    ['label' => 'Systemeinstellungen', 'route' => route('admin.settings.edit', 'general'), 'active' => request()->routeIs('admin.settings.*')],
    ['label' => 'Systemdiagnose', 'route' => route('admin.system.show'), 'active' => request()->routeIs('admin.system.*')],
    ['label' => 'Aktivitaeten', 'route' => route('admin.audits.index'), 'active' => request()->routeIs('admin.audits.*')],
])

<nav class="flex gap-2 overflow-x-auto pb-2">
    @foreach ($items as $item)
        <a href="{{ $item['route'] }}" @class([
            'rounded-full border px-4 py-2 text-sm whitespace-nowrap',
            'border-[var(--color-gold)] bg-[var(--color-burgundy-soft)] text-[var(--color-cream)]' => $item['active'],
            'border-white/10 bg-black/20 text-[var(--color-mist)]' => ! $item['active'],
        ])>{{ $item['label'] }}</a>
    @endforeach
</nav>
