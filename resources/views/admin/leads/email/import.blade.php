<x-layouts.admin title="Import email leads" heading="Import email leads" breadcrumb="Leads / Email / Import">
    <div class="grid items-start gap-5 lg:grid-cols-3">
        <x-panel.card title="Upload a file" class="lg:col-span-2">
            <form method="POST" action="{{ route('admin.leads.email.import.store') }}" enctype="multipart/form-data"
                x-data="{ busy: false }" @submit="busy = true" class="space-y-5">
                @csrf

                <x-form.file name="file" label="Excel (.xlsx) or CSV file" accept=".xlsx,.csv,.txt" required />

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <x-form.select name="license_id" label="Username (account)" placeholder="— None —" :options="$accounts->all()" />
                        <p class="mt-1.5 text-xs text-muted">Every imported lead is tagged with this username and its customer.</p>
                    </div>
                    <x-form.select name="user_id" label="Customer" placeholder="— From the account —" :options="$customers->all()" />
                </div>

                <div class="flex gap-3">
                    <x-button>Import</x-button>
                    <x-button variant="ghost" :href="route('admin.leads.email.index')">Cancel</x-button>
                </div>
            </form>
        </x-panel.card>

        <x-panel.card title="File format">
            <div class="space-y-3 text-sm">
                <p class="text-muted">Type, range and password all come from the sheet. The first row must be the column headers; extra columns are ignored.</p>
                <dl class="space-y-2">
                    <div><dt class="font-mono text-xs font-semibold">number</dt><dd class="text-muted">Required, up to 32 characters.</dd></div>
                    <div><dt class="font-mono text-xs font-semibold">password</dt><dd class="text-muted">Optional.</dd></div>
                    <div><dt class="font-mono text-xs font-semibold">type</dt><dd class="text-muted">Required: bell, insert, otp, selection or tap_code ("Tap Code", "sel" also work).</dd></div>
                    <div><dt class="font-mono text-xs font-semibold">range</dt><dd class="text-muted">Optional label of the number range, e.g. 9980966000-9980966999.</dd></div>
                </dl>
                <p class="text-muted">A row that repeats an existing number with the same type and range is skipped.</p>
                <div class="flex flex-wrap gap-2 pt-1">
                    <x-button variant="ghost" size="sm" :href="route('admin.leads.email.example', ['format' => 'xlsx'])">Example .xlsx</x-button>
                    <x-button variant="ghost" size="sm" :href="route('admin.leads.email.example', ['format' => 'csv'])">Example .csv</x-button>
                </div>
            </div>
        </x-panel.card>
    </div>
</x-layouts.admin>
