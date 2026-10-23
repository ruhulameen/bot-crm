@props(['name', 'label', 'value', 'checked' => false])

<label class="flex items-center gap-2 text-sm text-ink">
    <input type="radio" name="{{ $name }}" value="{{ $value }}" @checked((string) old($name, $checked ? $value : null) === (string) $value)
        {{ $attributes->class('size-4 border-line bg-card text-brand focus:ring-brand') }}>
    {{ $label }}
</label>
