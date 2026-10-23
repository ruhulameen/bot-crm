{{-- Picks the admin or user panel layout from the signed-in user's role, for pages shared by every role. --}}
@props(['title' => null, 'heading' => null, 'breadcrumb' => null])

<x-dynamic-component :component="auth()->user()->hasRole(\App\Enums\UserRole::Admin) ? 'layouts.admin' : 'layouts.user'"
    :title="$title" :heading="$heading" :breadcrumb="$breadcrumb">
    {{ $slot }}
</x-dynamic-component>
