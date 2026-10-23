@props(['title' => null, 'heading' => null, 'breadcrumb' => null])

<x-layouts.panel :title="$title" :heading="$heading" :breadcrumb="$breadcrumb">
    <x-slot:nav>
        <x-panel.nav-heading>Core</x-panel.nav-heading>
        <x-panel.nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">Dashboard</x-panel.nav-link>

        <x-panel.nav-heading>Management</x-panel.nav-heading>
        <x-panel.nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')">Users</x-panel.nav-link>
        <x-panel.nav-link :href="route('admin.products.index')" :active="request()->routeIs('admin.products.*')">Products</x-panel.nav-link>
        <x-panel.nav-link :href="route('admin.licenses.index')" :active="request()->routeIs('admin.licenses.*')">Licenses</x-panel.nav-link>

        <x-panel.nav-heading>Leads</x-panel.nav-heading>
        <x-panel.nav-link :href="route('admin.leads.email.index')" :active="request()->routeIs('admin.leads.email.*')">Email</x-panel.nav-link>

        <x-panel.nav-heading>System</x-panel.nav-heading>
        <x-panel.nav-link :href="route('admin.settings.edit')" :active="request()->routeIs('admin.settings.*')">Settings</x-panel.nav-link>

        <x-panel.nav-heading>Account</x-panel.nav-heading>
        <x-panel.nav-link :href="route('profile.edit')" :active="request()->routeIs('profile.*')">Profile</x-panel.nav-link>
    </x-slot:nav>

    {{ $slot }}
</x-layouts.panel>
