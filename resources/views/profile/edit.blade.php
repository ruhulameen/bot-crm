<x-layouts.auto title="Profile" heading="My profile" breadcrumb="Account / Profile">
    <div class="grid items-start gap-5 lg:grid-cols-3">
        <div class="space-y-5 lg:col-span-2">
            <x-panel.card title="Profile information" class="mb-0">
                <form method="POST" action="{{ route('profile.update') }}" x-data="{ busy: false }" @submit="busy = true" class="space-y-5">
                    @csrf
                    @method('PUT')

                    <x-form.input name="name" label="Name" :value="$user->name" autocomplete="name" required />
                    <x-form.input name="email" label="Email address" type="email" :value="$user->email" autocomplete="email" required />

                    <x-button>Save changes</x-button>
                </form>
            </x-panel.card>

            <x-panel.card title="Change password" class="mb-0">
                <form method="POST" action="{{ route('profile.password.update') }}" x-data="{ busy: false }" @submit="busy = true" class="space-y-5">
                    @csrf
                    @method('PUT')

                    <x-form.input name="current_password" label="Current password" type="password" autocomplete="current-password" required />
                    <x-form.input name="password" label="New password" type="password" autocomplete="new-password" required />
                    <x-form.input name="password_confirmation" label="Confirm new password" type="password" autocomplete="new-password" required />
                    <p class="-mt-2 text-xs text-muted">Changing your password signs you out of every other browser and device.</p>

                    <x-button>Update password</x-button>
                </form>
            </x-panel.card>
        </div>

        <x-panel.card title="Account" class="mb-0">
            <dl class="space-y-4 text-sm">
                <div>
                    <dt class="text-muted">Role</dt>
                    <dd class="mt-1"><x-badge :tone="$user->role === \App\Enums\UserRole::Admin ? 'brand' : 'neutral'">{{ $user->role->label() }}</x-badge></dd>
                </div>
                <div>
                    <dt class="text-muted">Status</dt>
                    <dd class="mt-1"><x-badge tone="success">{{ $user->status->label() }}</x-badge></dd>
                </div>
                <div>
                    <dt class="text-muted">Last login</dt>
                    <dd class="mt-1 font-medium">{{ $user->last_login_at?->diffForHumans() ?? 'Never' }}</dd>
                </div>
                <div>
                    <dt class="text-muted">Member since</dt>
                    <dd class="mt-1 font-medium">{{ $user->created_at->format('d M Y') }}</dd>
                </div>
            </dl>
            <p class="mt-4 text-xs text-muted">Your role and status are managed by an administrator.</p>
        </x-panel.card>
    </div>
</x-layouts.auto>
