<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfilePasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class ProfilePasswordController extends Controller
{
    /**
     * Changes the password and signs every other browser/device out; this session stays signed in.
     */
    public function __invoke(ProfilePasswordRequest $request): RedirectResponse
    {
        $password = $request->validated('password');

        $request->user()->update(['password' => $password]);
        Auth::logoutOtherDevices($password);

        return to_route('profile.edit')->with('status', 'Password changed. Other devices were signed out.');
    }
}
