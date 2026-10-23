<?php

use App\Http\Controllers\Api\V1\EmailLeadController;
use App\Http\Controllers\Api\V1\LicenseController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:license-api')->prefix('v1')->group(function () {
    Route::prefix('licenses')->name('api.v1.licenses.')->group(function () {
        Route::post('login', [LicenseController::class, 'login'])->name('login');
        Route::post('heartbeat', [LicenseController::class, 'heartbeat'])->name('heartbeat');
    });

    Route::post('leads/email', [EmailLeadController::class, 'store'])->name('api.v1.leads.email.store');
});
