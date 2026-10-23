<x-panel.card class="max-w-2xl">
    <form method="POST" action="{{ $lead->exists ? route('admin.leads.email.update', $lead) : route('admin.leads.email.store') }}"
        x-data="{ busy: false }" @submit="busy = true" class="space-y-5">
        @csrf
        @if ($lead->exists)
            @method('PUT')
        @endif

        <div class="grid gap-5 sm:grid-cols-2">
            <x-form.input name="number" label="Number" :value="$lead->number" placeholder="+919980966156" autocomplete="off" required autofocus />
            <x-form.input name="password" label="Password" :value="$lead->password" autocomplete="off" />
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <x-form.select name="type" label="Type" :selected="$lead->type?->value"
                :options="collect(\App\Enums\LeadType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all()" required />
            <div>
                <x-form.input name="number_range" label="Range" :value="$lead->number_range" list="lead-ranges" placeholder="e.g. 9980966000-9980966999" autocomplete="off" />
                <datalist id="lead-ranges">
                    @foreach ($ranges as $range)
                        <option value="{{ $range }}"></option>
                    @endforeach
                </datalist>
            </div>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <x-form.select name="license_id" label="Supplied by (username)" placeholder="— None —" :options="$accounts->all()" :selected="$lead->license_id" />
                <p class="mt-1.5 text-xs text-muted">Tags the lead with the account's username and customer.</p>
            </div>
            <x-form.select name="user_id" label="Customer" placeholder="— From the account —" :options="$customers->all()" :selected="$lead->user_id" />
        </div>

        <div class="flex gap-3">
            <x-button>{{ $lead->exists ? 'Save changes' : 'Add lead' }}</x-button>
            <x-button variant="ghost" :href="route('admin.leads.email.index')">Cancel</x-button>
        </div>
    </form>
</x-panel.card>
