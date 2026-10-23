<x-panel.card class="max-w-2xl">
    <form method="POST" action="{{ $product->exists ? route('admin.products.update', $product) : route('admin.products.store') }}"
        x-data="{ busy: false }" @submit="busy = true" class="space-y-5">
        @csrf
        @if ($product->exists)
            @method('PUT')
        @endif

        <x-form.input name="name" label="Name" :value="$product->name" required autofocus />
        <div>
            <x-form.input name="slug" label="Slug" :value="$product->slug" placeholder="generated from the name" />
            <p class="mt-1.5 text-xs text-muted">Bots send this value as <code>product</code> when activating. Changing it breaks installed bots.</p>
        </div>
        <x-form.textarea name="description" label="Description" :value="$product->description" />
        <x-form.checkbox name="is_active" label="Active (licenses of inactive products cannot be activated)" :checked="$product->is_active" />

        <div class="flex gap-3">
            <x-button>{{ $product->exists ? 'Save changes' : 'Create product' }}</x-button>
            <x-button variant="ghost" :href="route('admin.products.index')">Cancel</x-button>
        </div>
    </form>
</x-panel.card>
