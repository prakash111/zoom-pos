<?php

use App\Http\Middleware\AuthenticateTenantApi;
use Illuminate\Support\Facades\Route;
use Modules\pharmacy\Http\Controllers\PharmacyModuleController;

/*
 * Loaded by App\Providers\ModuleServiceProvider::boot() only while this
 * module's sdui_modules row is active. Paths are absolute from the domain
 * root (this file is required directly, it does not inherit the /api prefix
 * that routes/api.php gets).
 */
Route::middleware([AuthenticateTenantApi::class])
    ->prefix('api/tenant/pharmacy-module')
    ->group(function () {
        Route::get('views/dashboard', [PharmacyModuleController::class, 'dashboard']);

        Route::get('views/batches', [PharmacyModuleController::class, 'batchesView']);
        Route::post('batches', [PharmacyModuleController::class, 'batchesStore']);

        Route::get('views/prescriptions', [PharmacyModuleController::class, 'prescriptionsView']);
        Route::post('prescriptions', [PharmacyModuleController::class, 'prescriptionsStore']);
        Route::get('views/prescription-detail', [PharmacyModuleController::class, 'prescriptionDetail']);
        Route::post('prescriptions/{id}/dispense', [PharmacyModuleController::class, 'prescriptionDispense']);
    });
