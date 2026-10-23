<x-layouts.admin title="Products" heading="Products" breadcrumb="Management / Products">
    <x-panel.card>
        <div class="mb-4 flex justify-end">
            <x-button :href="route('admin.products.create')">Add product</x-button>
        </div>

        <x-filters>
            <x-form.input name="q" placeholder="Search products…" :value="$filters['q'] ?? null" class="w-64" @input.debounce.400ms="$el.form.requestSubmit()" />
            <x-form.select name="active" placeholder="All" :options="['1' => 'Active', '0' => 'Inactive']" :selected="$filters['active'] ?? null" @change="$el.form.requestSubmit()" />
        </x-filters>

        <x-table>
            <thead>
                <tr><th>Product</th><th>Slug</th><th>Status</th><th>Licenses</th><th class="text-right">Actions</th></tr>
            </thead>
            <tbody>
                @forelse ($products as $product)
                    <tr>
                        <td class="font-medium">{{ $product->name }}</td>
                        <td class="font-mono text-xs text-muted">{{ $product->slug }}</td>
                        <td><x-badge :tone="$product->is_active ? 'success' : 'neutral'">{{ $product->is_active ? 'Active' : 'Inactive' }}</x-badge></td>
                        <td>
                            <a href="{{ route('admin.licenses.index', ['product' => $product->id]) }}" class="text-brand hover:underline">{{ $product->licenses_count }}</a>
                        </td>
                        <td class="space-x-1 text-right">
                            <x-button variant="ghost" size="sm" :href="route('admin.products.edit', $product)">Edit</x-button>
                            <x-confirm-form :action="route('admin.products.destroy', $product)" method="DELETE" :title="'Delete product'" :message="'Delete '.$product->name.'? This cannot be undone.'" confirm="Delete" variant="danger">
                                <x-button variant="danger" size="sm">Delete</x-button>
                            </x-confirm-form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-muted">No products found.</td></tr>
                @endforelse
            </tbody>
        </x-table>

        <div class="mt-4">{{ $products->links() }}</div>
    </x-panel.card>
</x-layouts.admin>
