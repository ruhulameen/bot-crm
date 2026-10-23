@props(['tone' => 'neutral'])

<span {{ $attributes->class([
    'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium',
    match ($tone) {
        'success' => 'bg-success/15 text-emerald-700 dark:text-emerald-300',
        'warning' => 'bg-warning/25 text-amber-700 dark:text-amber-300',
        'danger' => 'bg-danger/15 text-red-700 dark:text-red-300',
        'brand' => 'bg-brand/15 text-brand dark:text-brand-soft',
        default => 'bg-muted/15 text-muted',
    },
]) }}>{{ $slot }}</span>
