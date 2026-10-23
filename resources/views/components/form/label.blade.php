@props(['for'])

<label for="{{ $for }}" {{ $attributes->class('block text-sm/6 font-medium text-ink') }}>{{ $slot }}</label>
