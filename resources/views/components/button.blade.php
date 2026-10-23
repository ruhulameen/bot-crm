@props(['variant' => 'primary', 'href' => null, 'size' => 'md'])

@php
    $classes = [
        'inline-flex items-center justify-center rounded-lg font-semibold shadow-sm transition hover:-translate-y-px hover:brightness-110 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:opacity-60',
        match ($variant) {
            'success' => 'bg-success text-white',
            'danger' => 'bg-danger text-white',
            'warning' => 'bg-warning text-black',
            'ghost' => 'bg-card text-ink ring-1 ring-line shadow-none',
            default => 'bg-brand text-white',
        },
        match ($size) {
            'sm' => 'px-2.5 py-1 text-xs',
            default => 'px-3 py-2 text-sm/6',
        },
    ];
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->merge(['type' => 'submit', 'x-bind:disabled' => 'busy'])->class($classes) }}>{{ $slot }}</button>
@endif
