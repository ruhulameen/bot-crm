@php($isExpired = $license->status === \App\Enums\LicenseStatus::Expired)

<x-layouts.admin title="License" heading="License" breadcrumb="Management / Licenses / {{ $license->username }}">
    @if (session('credentials'))
        <div class="mb-5 rounded-xl border border-brand/40 bg-brand/10 p-4" x-data="{ copied: false }">
            <p class="text-sm font-semibold text-ink">Credentials — copy them now, the password is not shown again</p>
            <dl class="mt-3 grid gap-x-6 gap-y-2 font-mono text-sm sm:grid-cols-2">
                <div><dt class="font-sans text-xs text-muted">Username</dt><dd class="font-semibold">{{ session('credentials.username') }}</dd></div>
                <div><dt class="font-sans text-xs text-muted">Password</dt><dd class="font-semibold">{{ session('credentials.password') }}</dd></div>
            </dl>
            <button type="button" class="mt-3 text-sm font-semibold text-brand hover:underline"
                @click="$copy(@js('Username: '.session('credentials.username')."\nPassword: ".session('credentials.password'))).then((ok) => { if (ok) { copied = true; setTimeout(() => copied = false, 1500) } })"
                x-text="copied ? 'Copied ✓' : 'Copy both'"></button>
        </div>
    @endif

    <div class="grid items-start gap-5 lg:grid-cols-3">
        <div class="space-y-5 lg:col-span-2">
            <x-panel.card title="Details" class="mb-0">
                <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                    <div x-data="{ copied: false }">
                        <dt class="text-muted">Username</dt>
                        <dd class="mt-1 flex items-center gap-3">
                            <span class="font-mono text-lg font-semibold">{{ $license->username }}</span>
                            <button type="button" class="text-xs text-brand hover:underline"
                                @click="$copy(@js($license->username)).then((ok) => { if (ok) { copied = true; setTimeout(() => copied = false, 1500) } })"
                                x-text="copied ? 'Copied ✓' : 'Copy'"></button>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted">Product</dt>
                        <dd class="mt-1 font-medium">{{ $license->product->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Customer</dt>
                        <dd class="mt-1 font-medium">{{ $license->user ? $license->user->name.' · '.$license->user->email : '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Status</dt>
                        <dd class="mt-1 space-x-1">
                            <x-badge :tone="$license->status->tone()">{{ $license->status->label() }}</x-badge>
                            @if ($license->isExpiringSoon())
                                <x-badge tone="warning">Expiring soon</x-badge>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted">Expires</dt>
                        <dd class="mt-1 font-medium">{{ $license->expires_at->format('d M Y, H:i') }} <span class="font-normal text-muted">({{ $license->expires_at->diffForHumans() }})</span></dd>
                    </div>
                    <div>
                        <dt class="text-muted">Issued</dt>
                        <dd class="mt-1 font-medium">{{ $license->created_at->format('d M Y') }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-muted">Tags</dt>
                        <dd class="mt-1 flex flex-wrap gap-1">
                            @forelse ($license->tags as $tag)
                                <a href="{{ route('admin.licenses.index', ['tag' => $tag->id]) }}"><x-badge tone="brand">{{ $tag->name }}</x-badge></a>
                            @empty
                                <span class="text-muted">—</span>
                            @endforelse
                        </dd>
                    </div>
                    @if ($license->notes)
                        <div class="sm:col-span-2">
                            <dt class="text-muted">Notes</dt>
                            <dd class="mt-1 whitespace-pre-line">{{ $license->notes }}</dd>
                        </div>
                    @endif
                </dl>
            </x-panel.card>

            <x-panel.card title="Activity" class="mb-0">
                @if ($license->hasBeenSeen())
                    <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-muted">Presence</dt>
                            <dd class="mt-1"><x-badge :tone="$license->isOnline() ? 'success' : 'danger'">{{ $license->isOnline() ? 'Online' : 'Offline' }}</x-badge></dd>
                        </div>
                        <div>
                            <dt class="text-muted">Last seen</dt>
                            <dd class="mt-1 font-medium">{{ $license->last_seen_at->format('d M Y, H:i') }} <span class="font-normal text-muted">({{ $license->last_seen_at->diffForHumans() }})</span></dd>
                        </div>
                    </dl>
                @else
                    <p class="py-4 text-center text-muted">This account has not signed in yet.</p>
                @endif
            </x-panel.card>
        </div>

        <x-panel.card title="Actions">
            <div class="space-y-4">
                <form method="POST" action="{{ route('admin.licenses.extend', $license) }}" class="space-y-3"
                    x-data="{ mode: 'days', busy: false }"
                    @submit.prevent="const form = $el; $store.confirm.ask({ title: 'Update expiry', message: mode === 'days' ? 'Add the selected days to this license?' : 'Set the expiry to the chosen date?', confirmLabel: @js($isExpired ? 'Renew' : 'Extend'), variant: 'success' }).then((ok) => { if (ok) { busy = true; form.submit(); } })">
                    @csrf
                    <div class="flex gap-4">
                        <x-form.radio name="mode" value="days" label="{{ $isExpired ? 'Renew for' : 'Add days' }}" x-model="mode" />
                        <x-form.radio name="mode" value="date" label="Until date" x-model="mode" />
                    </div>

                    <div x-show="mode === 'days'">
                        <x-form.select name="days" :options="\App\Models\License::DURATIONS" selected="30" x-bind:disabled="mode !== 'days'" />
                    </div>
                    <div x-show="mode === 'date'" x-cloak>
                        <x-form.input name="expires_at" type="date" min="{{ now()->addDay()->toDateString() }}" x-bind:disabled="mode !== 'date'" />
                        <p class="mt-1.5 text-xs text-muted">Valid through the end of this day.</p>
                    </div>

                    <x-button variant="success" class="w-full">{{ $isExpired ? 'Renew' : 'Extend' }}</x-button>
                </form>

                <x-confirm-form :action="route('admin.licenses.reset-password', $license)" title="Reset password" message="Set a new password? The bot must use it from its next request." confirm="Reset password" class="block space-y-2 border-t border-line pt-4">
                    <x-form.input name="password" type="password" autocomplete="new-password" placeholder="New password (blank = generate)" />
                    <x-button variant="ghost" class="w-full">Reset password</x-button>
                </x-confirm-form>

                <div class="space-y-2 border-t border-line pt-4">
                    @if ($license->revoked_at)
                        <x-confirm-form :action="route('admin.licenses.restore', $license)" title="Restore license" message="Restore this license? Its bot will be able to run again." confirm="Restore" variant="success" class="block">
                            <x-button variant="success" class="w-full">Restore license</x-button>
                        </x-confirm-form>
                    @else
                        <x-confirm-form :action="route('admin.licenses.revoke', $license)" title="Revoke license" message="Revoke now? The running bot stops at its next heartbeat." confirm="Revoke" variant="warning" class="block">
                            <x-button variant="warning" class="w-full">Revoke license</x-button>
                        </x-confirm-form>
                    @endif
                    <x-button variant="ghost" class="w-full" :href="route('admin.licenses.edit', $license)">Edit details</x-button>
                    <x-confirm-form :action="route('admin.licenses.destroy', $license)" method="DELETE" title="Delete license" message="Permanently delete this license? This cannot be undone." confirm="Delete" variant="danger" class="block">
                        <x-button variant="danger" class="w-full">Delete license</x-button>
                    </x-confirm-form>
                </div>
            </div>
        </x-panel.card>
    </div>
</x-layouts.admin>
