@php($isSelf = $user->is(auth()->user()))

<x-panel.card class="max-w-2xl">
    <form method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}"
        x-data="{ busy: false }" @submit="busy = true" class="space-y-5">
        @csrf
        @if ($user->exists)
            @method('PUT')
        @endif

        <x-form.input name="name" label="Name" :value="$user->name" required autofocus />
        <x-form.input name="email" label="Email address" type="email" :value="$user->email" required />

        <div class="grid gap-5 sm:grid-cols-2">
            <x-form.select name="role" label="Role" :selected="$user->role?->value"
                :options="collect(\App\Enums\UserRole::cases())->mapWithKeys(fn ($r) => [$r->value => $r->label()])->all()" :disabled="$isSelf" />
            <x-form.select name="status" label="Status" :selected="$user->status?->value"
                :options="collect(\App\Enums\UserStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()" :disabled="$isSelf" />
        </div>
        @if ($isSelf)
            <input type="hidden" name="role" value="{{ $user->role->value }}">
            <input type="hidden" name="status" value="{{ $user->status->value }}">
            <p class="-mt-3 text-xs text-muted">You cannot change your own role or status.</p>
        @endif

        <div>
            <x-form.input name="password" label="{{ $user->exists ? 'New password' : 'Password' }}" type="password" autocomplete="new-password" :required="! $user->exists" />
            @if ($user->exists)
                <p class="mt-1.5 text-xs text-muted">Leave blank to keep the current password.</p>
            @endif
        </div>

        <div class="flex gap-3">
            <x-button>{{ $user->exists ? 'Save changes' : 'Create user' }}</x-button>
            <x-button variant="ghost" :href="route('admin.users.index')">Cancel</x-button>
        </div>
    </form>
</x-panel.card>
