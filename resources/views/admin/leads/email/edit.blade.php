<x-layouts.admin title="Edit lead" heading="Edit lead" breadcrumb="Leads / Email / {{ $lead->number }}">
    @include('admin.leads.email._form')

    <x-panel.card class="max-w-2xl">
        <x-confirm-form :action="route('admin.leads.email.destroy', $lead)" method="DELETE" title="Delete lead" :message="'Delete '.$lead->number.'? This cannot be undone.'" confirm="Delete" variant="danger">
            <x-button variant="danger">Delete lead</x-button>
        </x-confirm-form>
    </x-panel.card>
</x-layouts.admin>
