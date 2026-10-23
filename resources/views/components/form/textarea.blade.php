@props(['name', 'label' => null, 'value' => null, 'rows' => 4])

<div>
    @if ($label)
        <x-form.label :for="$name">{{ $label }}</x-form.label>
    @endif
    <textarea id="{{ $name }}" name="{{ $name }}" rows="{{ $rows }}"
        {{ $attributes->class([
            'block w-full rounded-md border-0 bg-card px-3 py-1.5 text-ink shadow-sm ring-1 ring-inset placeholder:text-muted focus:ring-2 focus:ring-inset focus:ring-brand focus:outline-none sm:text-sm/6',
            'mt-2' => $label,
            'ring-line' => ! $errors->has($name),
            'ring-danger' => $errors->has($name),
        ]) }}>{{ old($name, $value) }}</textarea>
    <x-form.error :name="$name" />
</div>
