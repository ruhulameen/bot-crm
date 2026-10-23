@props(['name', 'label' => null, 'options' => [], 'selected' => null, 'placeholder' => null])

<div>
    @if ($label)
        <x-form.label :for="$name">{{ $label }}</x-form.label>
    @endif
    <select id="{{ $name }}" name="{{ $name }}"
        {{ $attributes->class([
            'block w-full rounded-md border-0 bg-card px-3 py-1.5 text-ink shadow-sm ring-1 ring-inset focus:ring-2 focus:ring-inset focus:ring-brand focus:outline-none sm:text-sm/6',
            'mt-2' => $label,
            'ring-line' => ! $errors->has($name),
            'ring-danger' => $errors->has($name),
        ]) }}>
        @if ($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $value => $text)
            <option value="{{ $value }}" @selected((string) old($name, $selected) === (string) $value)>{{ $text }}</option>
        @endforeach
    </select>
    <x-form.error :name="$name" />
</div>
