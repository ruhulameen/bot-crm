<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LicenseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ExtendLicenseRequest;
use App\Http\Requests\Admin\LicenseRequest;
use App\Http\Requests\Admin\ResetLicensePasswordRequest;
use App\Models\License;
use App\Models\Product;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class LicenseController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->only(['q', 'product', 'tag', 'status', 'presence']);

        return view('admin.licenses.index', [
            'licenses' => License::filter($filters)
                ->with(['product', 'user', 'tags'])
                ->latest()
                ->paginate(15)
                ->withQueryString(),
            'filters' => $filters,
            'products' => Product::orderBy('name')->pluck('name', 'id'),
            'tags' => Tag::has('licenses')->orderBy('name')->pluck('name', 'id'),
            'statuses' => LicenseStatus::cases(),
        ]);
    }

    public function create(): View
    {
        return view('admin.licenses.create', [
            'license' => new License,
            ...$this->formOptions(),
        ]);
    }

    public function store(LicenseRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $password = filled($data['password'] ?? null) ? $data['password'] : License::generatePassword();

        $license = License::create([
            ...collect($data)->except(['days', 'tags', 'password'])->all(),
            'password' => $password,
            'expires_at' => now()->addDays((int) $data['days']),
        ]);
        $license->syncTagNames($data['tags'] ?? null);

        return to_route('admin.licenses.show', $license)
            ->with('status', 'License created.')
            ->with('credentials', ['username' => $license->username, 'password' => $password]);
    }

    public function show(License $license): View
    {
        $license->load(['product', 'user', 'tags']);

        return view('admin.licenses.show', ['license' => $license]);
    }

    public function edit(License $license): View
    {
        return view('admin.licenses.edit', ['license' => $license->load('tags'), ...$this->formOptions()]);
    }

    public function update(LicenseRequest $request, License $license): RedirectResponse
    {
        $license->update(collect($request->validated())->except('tags')->all());
        $license->syncTagNames($request->validated('tags'));

        return to_route('admin.licenses.show', $license)->with('status', 'License updated.');
    }

    public function extend(ExtendLicenseRequest $request, License $license): RedirectResponse
    {
        $request->filled('expires_at')
            ? $license->extendUntil(Carbon::parse($request->validated('expires_at')))
            : $license->extendByDays((int) $request->validated('days'));

        return back()->with('status', "License valid until {$license->expires_at->format('d M Y')}.");
    }

    /**
     * The new password is shown once on the next page because only a hash is stored.
     */
    public function resetPassword(ResetLicensePasswordRequest $request, License $license): RedirectResponse
    {
        $password = $request->filled('password') ? $request->validated('password') : License::generatePassword();

        $license->update(['password' => $password]);

        return back()
            ->with('status', 'Password changed.')
            ->with('credentials', ['username' => $license->username, 'password' => $password]);
    }

    public function revoke(License $license): RedirectResponse
    {
        $license->revoke();

        return back()->with('status', 'License revoked.');
    }

    public function restore(License $license): RedirectResponse
    {
        $license->restore();

        return back()->with('status', 'License restored.');
    }

    public function destroy(License $license): RedirectResponse
    {
        $license->delete();

        return to_route('admin.licenses.index')->with('status', 'License deleted.');
    }

    /**
     * @return array{products: Collection<int, string>, tagNames: Collection<int, string>, users: Collection<int, string>}
     */
    private function formOptions(): array
    {
        return [
            'products' => Product::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'tagNames' => Tag::orderBy('name')->pluck('name'),
            'users' => User::orderBy('name')->get()->mapWithKeys(fn (User $u) => [$u->id => "{$u->name} ({$u->email})"]),
        ];
    }
}
