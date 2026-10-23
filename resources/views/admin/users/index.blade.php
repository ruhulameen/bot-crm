<x-layouts.admin title="Users" heading="Users" breadcrumb="Management / Users">
    <x-panel.card>
        <div class="mb-4 flex justify-end">
            <x-button :href="route('admin.users.create')">Add user</x-button>
        </div>

        <x-filters>
            <x-form.input name="q" placeholder="Search name or email…" :value="$filters['q'] ?? null" class="w-64" @input.debounce.400ms="$el.form.requestSubmit()" />
            <x-form.select name="role" placeholder="All roles" :options="collect($roles)->mapWithKeys(fn ($r) => [$r->value => $r->label()])->all()" :selected="$filters['role'] ?? null" @change="$el.form.requestSubmit()" />
            <x-form.select name="status" placeholder="All statuses" :options="collect($statuses)->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()" :selected="$filters['status'] ?? null" @change="$el.form.requestSubmit()" />
        </x-filters>

        <x-table>
            <thead>
                <tr><th>User</th><th>Role</th><th>Status</th><th>Licenses</th><th>Last login</th><th class="text-right">Actions</th></tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td>
                            <div class="font-medium">{{ $user->name }}</div>
                            <div class="text-xs text-muted">{{ $user->email }}</div>
                        </td>
                        <td><x-badge :tone="$user->role === \App\Enums\UserRole::Admin ? 'brand' : 'neutral'">{{ $user->role->label() }}</x-badge></td>
                        <td>
                            <x-badge :tone="match ($user->status) {
                                \App\Enums\UserStatus::Active => 'success',
                                \App\Enums\UserStatus::Inactive => 'neutral',
                                \App\Enums\UserStatus::Suspended => 'danger',
                            }">{{ $user->status->label() }}</x-badge>
                        </td>
                        <td>{{ $user->licenses_count }}</td>
                        <td class="text-muted">{{ $user->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                        <td class="space-x-1 text-right">
                            <x-button variant="ghost" size="sm" :href="route('admin.users.edit', $user)">Edit</x-button>
                            @unless ($user->is(auth()->user()))
                                <x-confirm-form :action="route('admin.users.destroy', $user)" method="DELETE" :title="'Delete user'" :message="'Delete '.$user->name.'? Their licenses will be kept without an owner.'" confirm="Delete" variant="danger">
                                    <x-button variant="danger" size="sm">Delete</x-button>
                                </x-confirm-form>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-muted">No users found.</td></tr>
                @endforelse
            </tbody>
        </x-table>

        <div class="mt-4">{{ $users->links() }}</div>
    </x-panel.card>
</x-layouts.admin>
