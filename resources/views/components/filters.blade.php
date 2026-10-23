<form method="GET" action="{{ url()->current() }}" x-data {{ $attributes->class('mb-4 flex flex-wrap items-end gap-3') }}>
    {{ $slot }}
    @if (request()->query())
        <a href="{{ url()->current() }}" class="pb-1.5 text-sm text-muted hover:text-ink">Reset</a>
    @endif
</form>
