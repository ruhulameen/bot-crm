{{-- Single shared dialog driven by $store.confirm; see x-confirm-form. --}}
<div x-data x-show="$store.confirm.open" x-cloak x-transition.opacity role="dialog" aria-modal="true" aria-labelledby="confirm-title"
    @keydown.escape.window="$store.confirm.open && $store.confirm.answer(false)"
    x-effect="if ($store.confirm.open) $nextTick(() => $refs[$store.confirm.variant === 'danger' ? 'cancel' : 'confirm'].focus())"
    class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/50" @click="$store.confirm.answer(false)"></div>

    <div class="relative w-full max-w-md rounded-xl bg-card p-6 shadow-2xl ring-1 ring-line">
        <h2 id="confirm-title" class="text-lg font-semibold text-ink" x-text="$store.confirm.title"></h2>
        <p class="mt-2 text-sm text-muted" x-text="$store.confirm.message"></p>

        <div class="mt-6 flex justify-end gap-3">
            <button type="button" x-ref="cancel" @click="$store.confirm.answer(false)"
                class="rounded-lg bg-card px-3 py-2 text-sm font-semibold text-ink ring-1 ring-line hover:bg-page focus-visible:outline-2 focus-visible:outline-brand">Cancel</button>
            <button type="button" x-ref="confirm" @click="$store.confirm.answer(true)" x-text="$store.confirm.confirmLabel"
                :class="{
                    'bg-danger text-white': $store.confirm.variant === 'danger',
                    'bg-warning text-black': $store.confirm.variant === 'warning',
                    'bg-success text-white': $store.confirm.variant === 'success',
                    'bg-brand text-white': ! ['danger', 'warning', 'success'].includes($store.confirm.variant),
                }"
                class="rounded-lg px-3 py-2 text-sm font-semibold shadow-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"></button>
        </div>
    </div>
</div>
