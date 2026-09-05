@props(['type' => 'success', 'message' => null])

@php
    $classes = [
        'success' => 'alert-success',
        'error'   => 'alert-error',
        'warning' => 'alert-warning',
        'info'    => 'alert-info',
    ];
@endphp

@if ($message)
    <div {{ $attributes->merge(['class' => 'alert ' . ($classes[$type] ?? 'alert-info')]) }}>
        {{ $message }}
    </div>
@elseif ($slot->isNotEmpty())
    <div {{ $attributes->merge(['class' => 'alert ' . ($classes[$type] ?? 'alert-info')]) }}>
        {{ $slot }}
    </div>
@endif