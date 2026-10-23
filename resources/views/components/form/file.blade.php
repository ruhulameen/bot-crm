@props(['name', 'label' => null, 'accept' => null])

<div>
    @if ($label)
        <x-form.label :for="$name">{{ $label }}</x-form.label>
    @endif
    <input type="file" id="{{ $name }}" name="{{ $name }}" @if ($accept) accept="{{ $accept }}" @endif
        {{ $attributes->class([
            'block w-full rounded-md bg-card text-sm text-ink ring-1 ring-inset file:mr-3 file:cursor-pointer file:border-0 file:bg-brand/10 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-brand hover:file:bg-brand/20',
            'mt-2' => $label,
            'ring-line' => ! $errors->has($name),
            'ring-danger' => $errors->has($name),
        ]) }}>
    <x-form.error :name="$name" />
</div>
