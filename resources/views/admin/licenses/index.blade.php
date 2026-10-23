<x-layouts.admin title="Licenses" heading="Licenses" breadcrumb="Management / Licenses">
    <x-panel.card>
        <div class="mb-4 flex justify-end">
            <x-button :href="route('admin.licenses.create')">Issue license</x-button>
        </div>

        <x-filters>
            <x-form.input name="q" placeholder="Search username, customer, tag…" :value="$filters['q'] ?? null" class="w-64" @input.debounce.400ms="$el.form.requestSubmit()" />
            <x-form.select name="product" placeholder="All products" :options="$products->all()" :selected="$filters['product'] ?? null" @change="$el.form.requestSubmit()" />
            @if ($tags->isNotEmpty())
                <x-form.select name="tag" placeholder="All tags" :options="$tags->all()" :selected="$filters['tag'] ?? null" @change="$el.form.requestSubmit()" />
            @endif
            <x-form.select name="status" placeholder="Any status" :selected="$filters['status'] ?? null" @change="$el.form.requestSubmit()"
                :options="['expiring' => 'Expiring soon', ...collect($statuses)->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()]" />
            <x-form.select name="presence" placeholder="Any activity" :selected="$filters['presence'] ?? null" @change="$el.form.requestSubmit()"
                :options="['online' => 'Online', 'offline' => 'Offline', 'never' => 'Never seen']" />
        </x-filters>

        <x-table>
            <thead>
                <tr><th>Username</th><th>Product</th><th>Customer</th><th>Expires</th><th>Status</th><th>Last seen</th><th class="text-right">Actions</th></tr>
            </thead>
            <tbody>
                @forelse ($licenses as $license)
                    <tr>
                        <td>
                            <a href="{{ route('admin.licenses.show', $license) }}" class="font-mono text-xs text-brand hover:underline">{{ $license->username }}</a>
                            @if ($license->tags->isNotEmpty())
                                <div class="mt-1 flex gap-1">
                                    @foreach ($license->tags as $tag)
                                        <a href="{{ route('admin.licenses.index', ['tag' => $tag->id]) }}"><x-badge tone="brand">{{ $tag->name }}</x-badge></a>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td>{{ $license->product->name }}</td>
                        <td>
                            @if ($license->user)
                                <div>{{ $license->user->name }}</div>
                                <div class="text-xs text-muted">{{ $license->user->email }}</div>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            <div>{{ $license->expires_at->format('d M Y') }}</div>
                            <div class="text-xs text-muted">{{ $license->expires_at->diffForHumans() }}</div>
                        </td>
                        <td class="space-x-1">
                            <x-badge :tone="$license->status->tone()">{{ $license->status->label() }}</x-badge>
                            @if ($license->isExpiringSoon())
                                <x-badge tone="warning">Expiring soon</x-badge>
                            @endif
                        </td>
                        <td>
                            @if (! $license->hasBeenSeen())
                                <span class="text-muted">Never</span>
                            @else
                                <div class="inline-flex items-center gap-2">
                                    <span class="size-2 rounded-full {{ $license->isOnline() ? 'bg-success' : 'bg-danger' }}"></span>
                                    {{ $license->isOnline() ? 'Online' : 'Offline' }}
                                </div>
                                <div class="text-xs text-muted">{{ $license->last_seen_at->diffForHumans() }}</div>
                            @endif
                        </td>
                        <td class="text-right"><x-button variant="ghost" size="sm" :href="route('admin.licenses.show', $license)">Manage</x-button></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-8 text-center text-muted">No licenses found.</td></tr>
                @endforelse
            </tbody>
        </x-table>

        <div class="mt-4">{{ $licenses->links() }}</div>
    </x-panel.card>
</x-layouts.admin>
