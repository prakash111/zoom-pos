<?php

use App\Livewire\Auth\PlatformLogin;
use App\Livewire\SuperAdmin\ActivationCodes;
use App\Livewire\SuperAdmin\AuditLogs;
use App\Livewire\SuperAdmin\Backups;
use App\Livewire\SuperAdmin\Branding;
use App\Livewire\SuperAdmin\Dashboard;
use App\Livewire\SuperAdmin\Languages;
use App\Livewire\Superadmin\MenuBuilderComponent;
use App\Livewire\SuperAdmin\Pages;
use App\Livewire\SuperAdmin\PaymentGateways;
use App\Livewire\SuperAdmin\Plans;
use App\Livewire\SuperAdmin\Settings;
use App\Livewire\SuperAdmin\Smtp;
use App\Livewire\SuperAdmin\System;
use App\Livewire\SuperAdmin\Tax;
use App\Livewire\SuperAdmin\Tenants;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::prefix('superadmin')->name('superadmin.')->group(function () {
    Route::get('/login', PlatformLogin::class)
        ->middleware('guest:platform_web')
        ->name('login');

    Route::post('/logout', function () {
        Auth::guard('platform_web')->logout();

        return redirect()->route('superadmin.login');
    })->middleware('auth:platform_web')->name('logout');

    Route::middleware('auth:platform_web')->group(function () {
        Route::get('/', Dashboard::class)->name('dashboard');

        Route::get('/tenants', Tenants\Index::class)->name('tenants.index');
        Route::get('/tenants/create', Tenants\Create::class)->name('tenants.create');
        Route::get('/tenants/{company}', Tenants\Show::class)->name('tenants.show');

        Route::get('/plans', Plans\Index::class)->name('plans.index');
        Route::get('/activation-codes', ActivationCodes\Index::class)->name('activation-codes.index');
        Route::get('/payment-gateways', PaymentGateways\Index::class)->name('payment-gateways.index');
        Route::get('/settings', Settings\Index::class)->name('settings.index');
        Route::get('/settings/notifications', Settings\Index::class)->name('settings.notifications');
        Route::get('/settings/regional', Settings\Index::class)->name('settings.regional');
        Route::get('/branding', Branding\Index::class)->name('branding.index');
        Route::get('/menus', MenuBuilderComponent::class)->name('menus.index');
        Route::get('/pages', Pages\Index::class)->name('pages.index');
        Route::get('/pages/create', Pages\Create::class)->name('pages.create');
        Route::get('/pages/{page}', Pages\Edit::class)->name('pages.edit');
        Route::get('/smtp', Smtp\Index::class)->name('smtp.index');
        Route::get('/tax', Tax\Index::class)->name('tax.index');
        Route::get('/languages', Languages\Index::class)->name('languages.index');
        Route::get('/backups', Backups\Index::class)->name('backups.index');
        Route::get('/system', System\Index::class)->name('system.index');
        Route::get('/audit', AuditLogs\Index::class)->name('audit.index');
    });
});
