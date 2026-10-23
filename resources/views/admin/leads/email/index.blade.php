@php
    $report = session('import_report');
@endphp

<x-layouts.admin title="Email leads" heading="Email leads" breadcrumb="Leads / Email">
    @if ($report && ($report['invalid'] > 0 || $report['duplicates'] > 0))
        <div class="mb-5 rounded-xl border border-warning/50 bg-warning/10 p-4 text-sm">
            <p class="font-semibold text-ink">Import finished with notes</p>
            <p class="mt-1 text-muted">{{ $report['total'] }} rows read · {{ $report['imported'] }} imported · {{ $report['duplicates'] }} skipped as duplicates · {{ $report['invalid'] }} invalid</p>
            @if ($report['errors'])
                <ul class="mt-2 list-inside list-disc text-ink">
                    @foreach ($report['errors'] as $error)
                        <li>Row {{ $error['row'] }}: {{ $error['message'] }}</li>
                    @endforeach
                </ul>
                @if ($report['invalid'] > count($report['errors']))
                    <p class="mt-1 text-xs text-muted">…and {{ $report['invalid'] - count($report['errors']) }} more invalid rows.</p>
                @endif
            @endif
        </div>
    @endif

    @if ($errors->hasAny(['format', 'date_from', 'date_to', 'types', 'types.*']))
        <div class="mb-5 rounded-md bg-danger/10 p-3 text-sm text-danger ring-1 ring-danger/30">
            <p class="font-semibold">The export options are not valid:</p>
            <ul class="mt-1 list-inside list-disc">
                @foreach (collect(['format', 'date_from', 'date_to', 'types', 'types.*'])->flatMap(fn ($key) => $errors->get($key))->unique() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <x-panel.card>
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap gap-2 text-sm">
                <a href="{{ route('admin.leads.email.index') }}"><x-badge :tone="empty($filters['type']) ? 'brand' : 'neutral'">All {{ number_format($typeCounts->sum()) }}</x-badge></a>
                @foreach (\App\Enums\LeadType::cases() as $type)
                    <a href="{{ route('admin.leads.email.index', ['type' => $type->value]) }}">
                        <x-badge :tone="($filters['type'] ?? null) === $type->value ? 'brand' : 'neutral'">{{ $type->label() }} {{ number_format($typeCounts[$type->value] ?? 0) }}</x-badge>
                    </a>
                @endforeach
            </div>

            <div class="flex flex-wrap gap-2">
                <x-button variant="ghost" :href="route('admin.leads.email.import.create')">Import</x-button>
                <div class="relative" x-data="{ open: {{ $errors->hasAny(['format', 'date_from', 'date_to', 'types', 'types.*']) ? 'true' : 'false' }} }"
                    @click.outside="open = false" @keydown.escape.window="open = false">
                    <x-button variant="ghost" type="button" @click="open = ! open" x-bind:disabled="false" aria-haspopup="true">Export ▾</x-button>

                    <form method="GET" action="{{ route('admin.leads.email.export') }}" x-show="open" x-cloak x-transition.origin.top.right
                        class="absolute right-0 z-20 mt-2 w-80 space-y-4 rounded-xl bg-card p-4 shadow-lg ring-1 ring-line">
                        <div>
                            <p class="text-sm font-semibold text-ink">Date</p>
                            <div class="mt-2 grid grid-cols-2 gap-2">
                                <x-form.input name="date_from" type="date" label="From" />
                                <x-form.input name="date_to" type="date" label="To" />
                            </div>
                            <p class="mt-1.5 text-xs text-muted">Leave both empty for all dates. Pick the same day twice for a single day.</p>
                        </div>

                        <div>
                            <p class="text-sm font-semibold text-ink">Types</p>
                            <div class="mt-2 grid grid-cols-2 gap-2">
                                @foreach (\App\Enums\LeadType::cases() as $type)
                                    <x-form.checkbox name="types[]" :value="$type->value" :label="$type->label()" />
                                @endforeach
                            </div>
                            <p class="mt-1.5 text-xs text-muted">Tick none to export every type.</p>
                        </div>

                        <div>
                            <p class="text-sm font-semibold text-ink">Format</p>
                            <div class="mt-2 flex gap-4">
                                <x-form.radio name="format" value="xlsx" label="Excel (.xlsx)" checked />
                                <x-form.radio name="format" value="csv" label="CSV" />
                            </div>
                        </div>

                        <x-button class="w-full" x-bind:disabled="false">Download</x-button>
                    </form>
                </div>
                <x-button :href="route('admin.leads.email.create')">Add lead</x-button>
            </div>
        </div>

        <x-filters>
            <x-form.input name="q" placeholder="Search number, range, username…" :value="$filters['q'] ?? null" class="w-64" @input.debounce.400ms="$el.form.requestSubmit()" />
            <x-form.select name="type" placeholder="All types" :selected="$filters['type'] ?? null" @change="$el.form.requestSubmit()"
                :options="collect(\App\Enums\LeadType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all()" />
            @if ($ranges->isNotEmpty())
                <x-form.select name="range" placeholder="All ranges" :options="$ranges->combine($ranges)->all()" :selected="$filters['range'] ?? null" @change="$el.form.requestSubmit()" />
            @endif
            @if ($usernames->isNotEmpty())
                <x-form.select name="username" placeholder="All usernames" :options="$usernames->combine($usernames)->all()" :selected="$filters['username'] ?? null" @change="$el.form.requestSubmit()" />
            @endif
            @if ($customers->isNotEmpty())
                <x-form.select name="user" placeholder="All customers" :options="$customers->all()" :selected="$filters['user'] ?? null" @change="$el.form.requestSubmit()" />
            @endif
            <x-form.input name="from" type="date" :value="$filters['from'] ?? null" title="From date" @change="$el.form.requestSubmit()" />
            <x-form.input name="to" type="date" :value="$filters['to'] ?? null" title="To date" @change="$el.form.requestSubmit()" />
        </x-filters>

        <form method="POST" action="{{ route('admin.leads.email.destroy-many') }}"
            x-data="{ selected: [], all: false, pageIds: @js($leads->pluck('id')->map(fn ($id) => (string) $id)->all()) }"
            @submit.prevent="const form = $el; $store.confirm.ask({ title: 'Delete leads', message: 'Delete ' + selected.length + ' selected lead(s)? This cannot be undone.', confirmLabel: 'Delete', variant: 'danger' }).then((ok) => { if (ok) form.submit(); })">
            @csrf
            @method('DELETE')

            <div class="mb-3 flex items-center gap-3" x-show="selected.length > 0" x-cloak>
                <x-button variant="danger" size="sm" x-bind:disabled="false">Delete selected (<span x-text="selected.length"></span>)</x-button>
            </div>

            <x-table>
                <thead>
                    <tr>
                        <th class="w-8"><input type="checkbox" aria-label="Select all on this page" class="size-4 rounded border-line bg-card text-brand focus:ring-brand" x-model="all" @change="selected = all ? [...pageIds] : []"></th>
                        <th>Number</th><th>Password</th><th>Type</th><th>Range</th><th>Username</th><th>Date</th><th>Added</th><th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($leads as $lead)
                        <tr>
                            <td><input type="checkbox" name="ids[]" value="{{ $lead->id }}" aria-label="Select lead {{ $lead->number }}" class="size-4 rounded border-line bg-card text-brand focus:ring-brand" x-model="selected"></td>
                            <td class="font-mono text-xs">{{ $lead->number }}</td>
                            <td>
                                @if (filled($lead->password))
                                    <span x-data="{ show: false }" class="inline-flex items-center gap-2">
                                        <span x-show="! show" class="text-muted">••••••</span>
                                        <span x-show="show" x-cloak class="font-mono text-xs">{{ $lead->password }}</span>
                                        <button type="button" class="text-xs text-brand hover:underline" @click="show = ! show" x-text="show ? 'Hide' : 'Show'"></button>
                                    </span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td><x-badge tone="brand">{{ $lead->type->label() }}</x-badge></td>
                            <td class="text-xs">{{ $lead->number_range !== '' ? $lead->number_range : '—' }}</td>
                            <td>
                                @if ($lead->username)
                                    <div class="font-mono text-xs">{{ $lead->username }}</div>
                                    <div class="text-xs text-muted">{{ $lead->user?->name }}</div>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                                <x-badge class="mt-1">{{ $lead->source }}</x-badge>
                            </td>
                            <td class="whitespace-nowrap">{{ $lead->created_at->format('d M Y') }}</td>
                            <td class="whitespace-nowrap text-muted" title="{{ $lead->created_at->format('d M Y, H:i') }}">{{ $lead->created_at->diffForHumans() }}</td>
                            <td class="text-right"><x-button variant="ghost" size="sm" :href="route('admin.leads.email.edit', $lead)">Edit</x-button></td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="py-8 text-center text-muted">No leads found. <a href="{{ route('admin.leads.email.import.create') }}" class="text-brand hover:underline">Import a file</a> or add one.</td></tr>
                    @endforelse
                </tbody>
            </x-table>
        </form>

        <div class="mt-4">{{ $leads->links() }}</div>
    </x-panel.card>
</x-layouts.admin>
