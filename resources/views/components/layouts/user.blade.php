@props(['title' => null, 'heading' => null, 'breadcrumb' => null])

<x-layouts.panel :title="$title" :heading="$heading" :breadcrumb="$breadcrumb">
    <x-slot:nav>
        <x-panel.nav-heading>Menu</x-panel.nav-heading>
        <x-panel.nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">Dashboard</x-panel.nav-link>

        <x-panel.nav-heading>Account</x-panel.nav-heading>
        <x-panel.nav-link :href="route('profile.edit')" :active="request()->routeIs('profile.*')">Profile</x-panel.nav-link>
    </x-slot:nav>

    {{ $slot }}
</x-layouts.panel>
