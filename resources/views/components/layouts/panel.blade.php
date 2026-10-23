{{--
    Shared panel shell (sidebar + topbar + page header) for every role.
    Wrap it per audience (see layouts/admin and layouts/user) and pass the menu through the `nav` slot.
--}}
@props(['title' => null, 'heading' => null, 'breadcrumb' => null, 'nav'])

<x-layouts.base :title="$title ?? $heading">
    <div x-data="{ sidebar: false }" @keydown.escape.window="sidebar = false" class="flex min-h-full">
        <div x-show="sidebar" x-cloak x-transition.opacity @click="sidebar = false" class="fixed inset-0 z-20 bg-black/50 md:hidden"></div>

        <aside :class="sidebar ? 'translate-x-0' : '-translate-x-full md:translate-x-0'"
            class="fixed inset-y-0 left-0 z-30 flex w-56 shrink-0 flex-col bg-linear-to-b from-sidebar-from to-sidebar-to text-indigo-200 transition-transform md:sticky md:top-0 md:h-screen">
            <a href="{{ route('dashboard') }}" class="bg-linear-90 from-brand-soft to-brand-fuchsia bg-clip-text px-4 py-5 text-[22px] font-semibold tracking-wide text-transparent">
                {{ config('app.name') }}
            </a>

            <nav class="flex-1 overflow-y-auto pb-4">
                {{ $nav }}
            </nav>

            <a href="{{ route('profile.edit') }}" class="block bg-white/10 p-3 text-sm text-indigo-100 transition hover:bg-white/15" title="My profile">
                <p class="truncate font-medium">{{ auth()->user()->name }}</p>
                <p class="text-xs text-indigo-300">{{ auth()->user()->role->label() }}</p>
            </a>
        </aside>

        <div class="min-w-0 flex-1">
            <header class="flex h-[52px] items-center justify-between border-b border-line bg-card px-4 shadow-sm">
                <button type="button" @click="sidebar = true" class="-ml-1 rounded-lg px-2 py-1 text-lg text-ink hover:bg-page md:invisible" aria-label="Open menu">☰</button>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-lg border border-line px-3 py-1 text-sm text-ink hover:bg-page">Logout</button>
                </form>
            </header>

            <main class="p-5">
                @if ($heading)
                    <h1 class="text-3xl font-semibold text-brand">{{ $heading }}</h1>
                @endif
                @if ($breadcrumb)
                    <p class="mt-1 mb-5 text-muted">{{ $breadcrumb }}</p>
                @endif

                {{ $slot }}
            </main>
        </div>
    </div>
</x-layouts.base>
