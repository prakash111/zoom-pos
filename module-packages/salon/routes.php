<?php

use App\Http\Middleware\AuthenticateTenantApi;
use Illuminate\Support\Facades\Route;
use Modules\salon\Http\Controllers\SalonModuleController;

/*
 * Loaded by App\Providers\ModuleServiceProvider::boot() only while this
 * module's sdui_modules row is active.
 */
Route::middleware([AuthenticateTenantApi::class])
    ->prefix('api/tenant/salon-module')
    ->group(function () {
        Route::get('views/dashboard', [SalonModuleController::class, 'dashboard']);

        Route::get('views/services', [SalonModuleController::class, 'servicesView']);
        Route::post('services', [SalonModuleController::class, 'servicesStore']);

        Route::get('views/stylists', [SalonModuleController::class, 'stylistsView']);
        Route::post('stylists', [SalonModuleController::class, 'stylistsStore']);

        Route::get('views/appointments', [SalonModuleController::class, 'appointmentsView']);
        Route::post('appointments', [SalonModuleController::class, 'appointmentsStore']);
        Route::get('views/appointment-detail', [SalonModuleController::class, 'appointmentDetail']);
        Route::post('appointments/{id}/status', [SalonModuleController::class, 'appointmentStatus']);
    });
