@props(['title' => null])

<section {{ $attributes->class('mb-5 overflow-hidden rounded-xl border border-line bg-card shadow-sm') }}>
    @if ($title)
        <header class="border-b border-line bg-linear-90 from-brand/10 to-brand-violet/5 px-4 py-3 font-semibold text-ink">{{ $title }}</header>
    @endif
    <div class="p-4">{{ $slot }}</div>
</section>
