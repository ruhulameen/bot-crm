@php
    $messages = collect([['success', session('status')], ['error', session('error')]])
        ->filter(fn (array $flash) => filled($flash[1]))
        ->when($errors->any() && blank(session('error')), fn ($flashes) => $flashes->push(['error', 'Please fix the highlighted fields.']))
        ->map(fn (array $flash) => ['type' => $flash[0], 'message' => $flash[1]])
        ->values();
@endphp

{{-- Flash messages become toasts; trigger more from Alpine with $store.toasts.add('success', 'Saved'). --}}
<div x-data x-init="@js($messages).forEach((toast) => $store.toasts.add(toast.type, toast.message))" aria-live="polite"
    class="pointer-events-none fixed inset-x-4 top-4 z-50 flex flex-col items-end gap-2 sm:left-auto sm:w-96">
    <template x-for="toast in $store.toasts.items" :key="toast.id">
        <div role="status" class="pointer-events-auto flex w-full animate-toast-in items-start gap-3 rounded-lg border-l-4 bg-card p-3 text-sm shadow-lg ring-1 ring-line"
            :class="toast.type === 'error' ? 'border-danger' : 'border-success'">
            <span class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full text-xs font-bold text-white"
                :class="toast.type === 'error' ? 'bg-danger' : 'bg-success'" x-text="toast.type === 'error' ? '!' : '✓'"></span>
            <p class="flex-1 text-ink" x-text="toast.message"></p>
            <button type="button" class="text-muted hover:text-ink" aria-label="Dismiss" @click="$store.toasts.remove(toast.id)">✕</button>
        </div>
    </template>
</div>
