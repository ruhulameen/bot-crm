@props(['href', 'active' => false])

<a href="{{ $href }}" @if ($active) aria-current="page" @endif
    {{ $attributes->class([
        'mx-2.5 my-0.5 block rounded-lg px-3 py-2.5 transition',
        'bg-linear-90 from-indigo-500 to-violet-500 text-white shadow-lg shadow-indigo-500/40' => $active,
        'hover:bg-white/10 hover:text-white' => ! $active,
    ]) }}>{{ $slot }}</a>
