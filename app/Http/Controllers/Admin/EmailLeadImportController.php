<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\InvalidLeadFile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EmailLeadImportRequest;
use App\Models\EmailLead;
use App\Models\License;
use App\Models\User;
use App\Services\LeadImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EmailLeadImportController extends Controller
{
    public function create(): View
    {
        return view('admin.leads.email.import', [
            'accounts' => License::with('product')->orderBy('username')->get()->mapWithKeys(fn (License $l) => [$l->id => "{$l->username} — {$l->product->name}"]),
            'customers' => User::orderBy('name')->get()->mapWithKeys(fn (User $u) => [$u->id => "{$u->name} ({$u->email})"]),
        ]);
    }

    public function store(EmailLeadImportRequest $request, LeadImporter $importer): RedirectResponse
    {
        $license = $request->filled('license_id') ? License::find($request->validated('license_id')) : null;
        $userId = $request->filled('user_id') ? (int) $request->validated('user_id') : null;

        try {
            $report = $importer->importFile($request->file('file'), EmailLead::tagsFor($license, $userId));
        } catch (InvalidLeadFile $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        return to_route('admin.leads.email.index')
            ->with('status', "{$report['imported']} lead(s) imported.")
            ->with('import_report', $report);
    }
}
