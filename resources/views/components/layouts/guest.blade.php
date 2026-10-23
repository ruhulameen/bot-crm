@props(['title' => null, 'heading', 'subheading' => null])

<x-layouts.base :title="$title ?? $heading">
    <div class="flex min-h-full flex-col items-center justify-center bg-linear-135 from-brand via-brand-violet via-60% to-brand-pink px-4 py-12">
        <div class="w-full max-w-md overflow-hidden rounded-2xl bg-card shadow-2xl shadow-sidebar-from/40">
            <div class="border-b border-line bg-linear-to-b from-brand/10 to-transparent px-6 py-6 text-center">
                <x-logo class="mx-auto" />
                <h1 class="mt-4 text-2xl font-semibold tracking-tight text-brand">{{ $heading }}</h1>
                @if ($subheading)
                    <p class="mt-2 text-sm text-muted">{{ $subheading }}</p>
                @endif
            </div>
            <div class="px-6 py-8 sm:px-10">
                {{ $slot }}
            </div>
        </div>

        @isset($footer)
            <p class="mt-6 text-center text-sm text-white/90 [&_a]:font-semibold [&_a]:text-white [&_a]:underline">{{ $footer }}</p>
        @endisset
    </div>
</x-layouts.base>
