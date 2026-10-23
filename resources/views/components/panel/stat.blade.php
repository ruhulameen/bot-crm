@props(['label', 'tone' => 'brand'])

@php
    $tones = [
        'brand' => 'from-brand to-brand-violet shadow-brand/20',
        'success' => 'from-emerald-500 to-teal-600 shadow-emerald-500/20',
        'danger' => 'from-rose-500 to-orange-500 shadow-rose-500/20',
    ];
@endphp

<div {{ $attributes->class(['rounded-2xl bg-linear-135 p-5 text-white shadow-xl', $tones[$tone]]) }}>
    <dt class="text-sm text-white/80">{{ $label }}</dt>
    <dd class="mt-1 text-lg font-semibold">{{ $slot }}</dd>
</div>
