<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->only(['q', 'role', 'status']);

        return view('admin.users.index', [
            'users' => User::filter($filters)->withCount('licenses')->latest()->paginate(15)->withQueryString(),
            'filters' => $filters,
            'roles' => UserRole::cases(),
            'statuses' => UserStatus::cases(),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.create', ['user' => new User(['role' => UserRole::User, 'status' => UserStatus::Active])]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        User::create($request->validated());

        return to_route('admin.users.index')->with('status', 'User created.');
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', ['user' => $user]);
    }

    /**
     * Admins cannot change their own role or status, so they can never lock themselves out.
     */
    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        if ($user->is($request->user())) {
            unset($data['role'], $data['status']);
        }

        $user->update($data);

        return to_route('admin.users.index')->with('status', 'User updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 403, 'You cannot delete your own account.');

        $user->delete();

        return to_route('admin.users.index')->with('status', 'User deleted.');
    }
}
