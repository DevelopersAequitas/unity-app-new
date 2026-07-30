@props([
    'text' => '',
    'as' => 'div',
    'lines' => 2,
    'class' => '',
])

@php
    $textContent = (string) ($text !== '' ? $text : $slot);
    $clampClass = (int) $lines === 1 ? 'admin-grid-text-single' : 'admin-grid-text-clamp';
@endphp

<{{ $as }} {{ $attributes->merge(['class' => $clampClass . ' ' . $class, 'data-full-text' => $textContent]) }}>
    {{ $slot->isEmpty() ? $textContent : $slot }}
</{{ $as }}>
