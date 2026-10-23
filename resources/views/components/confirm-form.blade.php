@props(['action', 'method' => 'POST', 'message' => 'Are you sure?', 'title' => 'Please confirm', 'confirm' => 'Confirm', 'variant' => 'primary'])

<form method="POST" action="{{ $action }}" x-data="{ busy: false }"
    @submit.prevent="const form = $el; $store.confirm.ask({ title: @js($title), message: @js($message), confirmLabel: @js($confirm), variant: @js($variant) }).then((ok) => { if (ok) { busy = true; form.submit(); } })"
    {{ $attributes->class(['inline' => ! $attributes->has('class')]) }}>
    @csrf
    @if (strtoupper($method) !== 'POST')
        @method($method)
    @endif
    {{ $slot }}
</form>
