@props(['name', 'label' => null, 'type' => 'text', 'value' => null])

<div x-data="{ show: false }">
    @if ($label)
        <x-form.label :for="$name">{{ $label }}</x-form.label>
    @endif
    <div class="relative {{ $label ? 'mt-2' : '' }}">
        <input id="{{ $name }}" name="{{ $name }}"
            @if ($type === 'password') :type="show ? 'text' : 'password'" @else type="{{ $type }}" value="{{ old($name, $value) }}" @endif
            {{ $attributes->class([
                'block w-full rounded-md border-0 bg-card px-3 py-1.5 text-ink shadow-sm ring-1 ring-inset placeholder:text-muted focus:ring-2 focus:ring-inset focus:ring-brand focus:outline-none sm:text-sm/6',
                'ring-line' => ! $errors->has($name),
                'ring-danger' => $errors->has($name),
                'pr-16' => $type === 'password',
            ]) }}>
        @if ($type === 'password')
            <button type="button" @click="show = !show" x-text="show ? 'Hide' : 'Show'"
                class="absolute inset-y-0 right-0 px-3 text-xs font-medium text-muted hover:text-ink"></button>
        @endif
    </div>
    <x-form.error :name="$name" />
</div>
