<?php

use App\Http\Controllers\Admin\EmailLeadController;
use App\Http\Controllers\Admin\EmailLeadExampleController;
use App\Http\Controllers\Admin\EmailLeadExportController;
use App\Http\Controllers\Admin\EmailLeadImportController;
use App\Http\Controllers\Admin\LicenseController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\TelegramController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfilePasswordController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:20,1');

    Route::get('forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [ForgotPasswordController::class, 'store'])->middleware('throttle:6,1')->name('password.email');

    Route::get('reset-password/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
    Route::post('reset-password', [ResetPasswordController::class, 'store'])->name('password.store');
});

Route::middleware(['auth', 'auth.session', 'active'])->group(function () {
    Route::post('logout', LogoutController::class)->name('logout');

    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', ProfilePasswordController::class)->middleware('throttle:6,1')->name('profile.password.update');

    Route::name('admin.')->group(function () {
        Route::middleware('role:admin')->group(function () {
            Route::resource('users', UserController::class)->except('show');
            Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
            Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
            Route::post('settings/telegram/test', [TelegramController::class, 'test'])->name('settings.telegram.test');
            Route::post('settings/telegram/chats', [TelegramController::class, 'chats'])->name('settings.telegram.chats');
            Route::resource('products', ProductController::class)->except('show');

            Route::prefix('leads/email')->name('leads.email.')->group(function () {
                Route::get('/', [EmailLeadController::class, 'index'])->name('index');
                Route::get('create', [EmailLeadController::class, 'create'])->name('create');
                Route::post('/', [EmailLeadController::class, 'store'])->name('store');
                Route::delete('/', [EmailLeadController::class, 'destroyMany'])->name('destroy-many');

                Route::get('import', [EmailLeadImportController::class, 'create'])->name('import.create');
                Route::post('import', [EmailLeadImportController::class, 'store'])->name('import.store');
                Route::get('export', EmailLeadExportController::class)->name('export');
                Route::get('example', EmailLeadExampleController::class)->name('example');

                Route::get('{lead}/edit', [EmailLeadController::class, 'edit'])->whereNumber('lead')->name('edit');
                Route::put('{lead}', [EmailLeadController::class, 'update'])->whereNumber('lead')->name('update');
                Route::delete('{lead}', [EmailLeadController::class, 'destroy'])->whereNumber('lead')->name('destroy');
            });

            Route::resource('licenses', LicenseController::class);
            Route::post('licenses/{license}/extend', [LicenseController::class, 'extend'])->name('licenses.extend');
            Route::post('licenses/{license}/revoke', [LicenseController::class, 'revoke'])->name('licenses.revoke');
            Route::post('licenses/{license}/restore', [LicenseController::class, 'restore'])->name('licenses.restore');
            Route::post('licenses/{license}/reset-password', [LicenseController::class, 'resetPassword'])->name('licenses.reset-password');
        });
    });
});
