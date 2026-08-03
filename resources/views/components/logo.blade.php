@props(['class' => 'size-6', 'color' => 'text-brand-500'])

{{-- Puntos azules en escalera ascendente: level up, y a la vez nodos de la malla CPM. --}}
<svg viewBox="0 0 30 30" fill="currentColor" aria-hidden="true" {{ $attributes->merge(['class' => $class.' '.$color]) }}>
    <circle cx="4" cy="25" r="2.6" />
    <circle cx="12" cy="25" r="2.6" />
    <circle cx="12" cy="17.5" r="2.6" opacity=".82" />
    <circle cx="20" cy="25" r="2.6" />
    <circle cx="20" cy="17.5" r="2.6" opacity=".82" />
    <circle cx="20" cy="10" r="2.6" opacity=".64" />
    <circle cx="27.4" cy="25" r="2.6" />
    <circle cx="27.4" cy="17.5" r="2.6" opacity=".82" />
    <circle cx="27.4" cy="10" r="2.6" opacity=".64" />
    <circle cx="27.4" cy="3" r="2.6" opacity=".46" />
</svg>
