<?php

use App\Http\Middleware\AuthenticateTenantApi;
use Illuminate\Support\Facades\Route;
use Modules\repairtechnician\Http\Controllers\RepairModuleController;

/*
 * Loaded by App\Providers\ModuleServiceProvider::boot() only while this
 * module's sdui_modules row is active.
 */
Route::middleware([AuthenticateTenantApi::class])
    ->prefix('api/tenant/repair-module')
    ->group(function () {
        Route::get('views/dashboard', [RepairModuleController::class, 'dashboard']);

        Route::get('views/tickets', [RepairModuleController::class, 'ticketsView']);
        Route::post('tickets', [RepairModuleController::class, 'ticketsStore']);
        Route::get('views/ticket-detail', [RepairModuleController::class, 'ticketDetail']);
        Route::post('tickets/{id}/status', [RepairModuleController::class, 'ticketStatus']);

        Route::get('views/categories', [RepairModuleController::class, 'categoriesView']);
        Route::post('categories', [RepairModuleController::class, 'categoriesStore']);
    });
