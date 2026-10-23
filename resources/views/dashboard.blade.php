@php
    $isStaff = auth()->user()->hasRole(\App\Enums\UserRole::Admin);
@endphp

<x-layouts.auto title="Dashboard" :heading="config('app.name')" breadcrumb="Dashboard">
    @if ($isStaff)
        <dl class="mb-5 grid gap-5 sm:grid-cols-[repeat(auto-fit,minmax(220px,1fr))]">
            <x-panel.stat label="Active licenses" tone="brand">{{ $stats['licenses'] }}</x-panel.stat>
            <x-panel.stat label="Bots online" tone="success">{{ $stats['online'] }} / {{ $stats['licenses'] }}</x-panel.stat>
            <x-panel.stat label="Expiring soon" tone="danger">{{ $expiring->count() }}</x-panel.stat>
            <x-panel.stat label="Products · Users" tone="brand">{{ $stats['products'] }} · {{ $stats['users'] }}</x-panel.stat>
        </dl>

        <div class="grid items-start gap-5 xl:grid-cols-2">
            <x-panel.card title="Expiring soon (next {{ config('licenses.expiring_soon_days') }} days)">
                <x-table>
                    <thead><tr><th>Account</th><th>Customer</th><th>Expires</th></tr></thead>
                    <tbody>
                        @forelse ($expiring as $license)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.licenses.show', $license) }}" class="font-mono text-xs text-brand hover:underline">{{ $license->username }}</a>
                                    <div class="text-xs text-muted">{{ $license->product->name }}</div>
                                </td>
                                <td>{{ $license->user?->name ?? '—' }}</td>
                                <td><x-badge tone="warning">{{ $license->expires_at->diffForHumans() }}</x-badge></td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="py-6 text-center text-muted">Nothing expires soon.</td></tr>
                        @endforelse
                    </tbody>
                </x-table>
            </x-panel.card>

            <x-panel.card title="Offline bots">
                <x-table>
                    <thead><tr><th>Account</th><th>Customer</th><th>Last seen</th></tr></thead>
                    <tbody>
                        @forelse ($offline as $license)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.licenses.show', $license) }}" class="font-mono text-xs text-brand hover:underline">{{ $license->username }}</a>
                                    <div class="text-xs text-muted">{{ $license->product->name }}</div>
                                </td>
                                <td>{{ $license->user?->name ?? '—' }}</td>
                                <td><x-badge tone="danger">{{ $license->last_seen_at->diffForHumans() }}</x-badge></td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="py-6 text-center text-muted">All bots are online.</td></tr>
                        @endforelse
                    </tbody>
                </x-table>
            </x-panel.card>
        </div>
    @else
        <dl class="mb-5 grid gap-5 sm:grid-cols-[repeat(auto-fit,minmax(220px,1fr))]">
            <x-panel.stat label="Role" tone="brand">{{ auth()->user()->role->label() }}</x-panel.stat>
            <x-panel.stat label="Status" tone="success">{{ auth()->user()->status->label() }}</x-panel.stat>
            <x-panel.stat label="Last login" tone="danger">{{ auth()->user()->last_login_at?->diffForHumans() ?? 'First visit' }}</x-panel.stat>
        </dl>

        <x-panel.card title="Welcome">
            Welcome back, {{ auth()->user()->name }}.
        </x-panel.card>
    @endif
</x-layouts.auto>
