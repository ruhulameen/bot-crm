<?php

namespace App\Providers;

use App\Services\Settings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Settings::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        try {
            $this->app->make(Settings::class)->apply();
        } catch (Throwable) {
            // Database not migrated yet (fresh install); .env defaults apply.
        }

        RateLimiter::for('license-api', fn (Request $request) => [
            Limit::perMinute(600)->by('ip|'.$request->ip()),
            Limit::perMinute(30)->by($request->input('product', '').'|'.$request->input('username', '').'|'.$request->ip()),
        ]);
    }
}
