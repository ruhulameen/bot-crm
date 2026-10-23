<x-panel.card class="max-w-2xl">
    <form method="POST" action="{{ $license->exists ? route('admin.licenses.update', $license) : route('admin.licenses.store') }}"
        x-data="{ busy: false }" @submit="busy = true" class="space-y-5">
        @csrf
        @if ($license->exists)
            @method('PUT')
        @endif

        @unless ($license->exists)
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.select name="product_id" label="Product" placeholder="Select a product" :options="$products->all()" required />
                <x-form.select name="days" label="Validity" :options="\App\Models\License::DURATIONS" selected="30" required />
            </div>
        @endunless

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <x-form.input name="username" label="Username" :value="$license->username" autocomplete="off" autocapitalize="off" placeholder="e.g. john123" required />
                <p class="mt-1.5 text-xs text-muted">Lowercase letters, numbers and . _ @ - only. Unique per product.</p>
            </div>
            @unless ($license->exists)
                <div>
                    <x-form.input name="password" label="Password" type="password" autocomplete="new-password" />
                    <p class="mt-1.5 text-xs text-muted">Leave blank to generate one. It is shown once after saving.</p>
                </div>
            @endunless
        </div>

        <x-form.select name="user_id" label="Customer" placeholder="— None —" :options="$users->all()" :selected="$license->user_id" />
        <div x-data="{ tags: @js(old('tags', $license->tags?->pluck('name')->implode(', ') ?? '')) }">
            <x-form.input name="tags" label="Tags" placeholder="vip, reseller, trial" x-model="tags" :value="$license->tags?->pluck('name')->implode(', ')" />
            <p class="mt-1.5 text-xs text-muted">Separate with commas (max 10).</p>
            @if ($tagNames->isNotEmpty())
                <div class="mt-2 flex flex-wrap gap-1.5">
                    @foreach ($tagNames as $tagName)
                        <button type="button" class="rounded-full bg-brand/10 px-2 py-0.5 text-xs text-brand hover:bg-brand/20"
                            @click="if (! tags.split(',').map(t => t.trim().toLowerCase()).includes(@js($tagName))) tags = tags.trim() ? tags.replace(/,\s*$/, '') + ', ' + @js($tagName) : @js($tagName)">+ {{ $tagName }}</button>
                    @endforeach
                </div>
            @endif
        </div>
        <x-form.textarea name="notes" label="Notes" :value="$license->notes" rows="3" />

        <div class="flex gap-3">
            <x-button>{{ $license->exists ? 'Save changes' : 'Issue license' }}</x-button>
            <x-button variant="ghost" :href="$license->exists ? route('admin.licenses.show', $license) : route('admin.licenses.index')">Cancel</x-button>
        </div>
    </form>
</x-panel.card>
