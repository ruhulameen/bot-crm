@props(['name'])

@error($name)
    <p {{ $attributes->class('mt-1.5 text-sm text-danger') }}>{{ $message }}</p>
@enderror
