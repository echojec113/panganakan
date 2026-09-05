@props(['variant' => 'neutral'])

@php
    $classes = [
        'success' => 'status-badge-success',
        'warning' => 'status-badge-warning',
        'danger'  => 'status-badge-danger',
        'info'    => 'status-badge-info',
        'neutral' => 'status-badge-neutral',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'status-badge ' . ($classes[$variant] ?? 'status-badge-neutral')]) }}>
    {{ $slot }}
</span>