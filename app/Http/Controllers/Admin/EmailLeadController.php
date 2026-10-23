<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LeadType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EmailLeadRequest;
use App\Models\EmailLead;
use App\Models\License;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class EmailLeadController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->only(['q', 'type', 'range', 'username', 'user', 'from', 'to']);

        return view('admin.leads.email.index', [
            'leads' => EmailLead::filter($filters)->with('user')->orderByDesc('id')->paginate(25)->withQueryString(),
            'filters' => $filters,
            'typeCounts' => EmailLead::query()->selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type'),
            'ranges' => EmailLead::where('number_range', '!=', '')->distinct()->orderBy('number_range')->pluck('number_range'),
            'usernames' => EmailLead::whereNotNull('username')->distinct()->orderBy('username')->pluck('username'),
            'customers' => User::whereIn('id', EmailLead::whereNotNull('user_id')->select('user_id'))->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function create(): View
    {
        return view('admin.leads.email.create', ['lead' => new EmailLead(['type' => LeadType::Bell]), ...$this->formOptions()]);
    }

    public function store(EmailLeadRequest $request): RedirectResponse
    {
        EmailLead::create([...$this->attributes($request), 'source' => 'manual']);

        return to_route('admin.leads.email.index')->with('status', 'Lead added.');
    }

    public function edit(EmailLead $lead): View
    {
        return view('admin.leads.email.edit', ['lead' => $lead, ...$this->formOptions()]);
    }

    public function update(EmailLeadRequest $request, EmailLead $lead): RedirectResponse
    {
        $lead->update($this->attributes($request));

        return to_route('admin.leads.email.index')->with('status', 'Lead updated.');
    }

    public function destroy(EmailLead $lead): RedirectResponse
    {
        $lead->delete();

        return to_route('admin.leads.email.index')->with('status', 'Lead deleted.');
    }

    public function destroyMany(Request $request): RedirectResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array', 'max:500'], 'ids.*' => ['integer']])['ids'];

        $deleted = EmailLead::whereKey($ids)->delete();

        return back()->with('status', $deleted === 1 ? '1 lead deleted.' : "{$deleted} leads deleted.");
    }

    /**
     * Form fields plus the tag columns: the chosen account supplies the username (and the customer unless one is picked).
     *
     * @return array<string, mixed>
     */
    private function attributes(EmailLeadRequest $request): array
    {
        $data = $request->validated();
        $license = filled($data['license_id'] ?? null) ? License::find($data['license_id']) : null;

        return [
            'number' => $data['number'],
            'password' => $data['password'] ?? null,
            'type' => $data['type'],
            'number_range' => $data['number_range'] ?? '',
            ...EmailLead::tagsFor($license, filled($data['user_id'] ?? null) ? (int) $data['user_id'] : null),
        ];
    }

    /**
     * @return array{accounts: Collection<int, string>, customers: Collection<int, string>, ranges: Collection<int, string>}
     */
    private function formOptions(): array
    {
        return [
            'accounts' => License::with('product')->orderBy('username')->get()->mapWithKeys(fn (License $l) => [$l->id => "{$l->username} — {$l->product->name}"]),
            'customers' => User::orderBy('name')->get()->mapWithKeys(fn (User $u) => [$u->id => "{$u->name} ({$u->email})"]),
            'ranges' => EmailLead::where('number_range', '!=', '')->distinct()->orderBy('number_range')->pluck('number_range'),
        ];
    }
}
