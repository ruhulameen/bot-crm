@props(['name', 'label', 'value' => '1', 'checked' => false])

<div>
    <label class="flex items-center gap-2 text-sm text-ink">
        <input type="checkbox" name="{{ $name }}" value="{{ $value }}" @checked(old($name, $checked))
            {{ $attributes->class('size-4 rounded border-line bg-card text-brand focus:ring-brand') }}>
        {{ $label }}
    </label>
    <x-form.error :name="$name" />
</div>
