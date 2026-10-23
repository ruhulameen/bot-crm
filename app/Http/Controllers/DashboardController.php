<?php

namespace App\Http\Controllers;

use App\Enums\LicenseStatus;
use App\Enums\UserRole;
use App\Models\License;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        if (! $request->user()->hasRole(UserRole::Admin)) {
            return view('dashboard');
        }

        return view('dashboard', [
            'stats' => [
                'users' => User::count(),
                'products' => Product::count(),
                'licenses' => License::whereStatus(LicenseStatus::Active)->count(),
                'online' => License::online()->count(),
            ],
            'expiring' => License::expiringSoon()->with(['product', 'user'])->orderBy('expires_at')->limit(10)->get(),
            'offline' => License::offline()->whereStatus(LicenseStatus::Active)->with(['product', 'user'])->orderByDesc('last_seen_at')->limit(10)->get(),
        ]);
    }
}
