<?php

use App\Http\Middleware\AuthenticateTenantApi;
use Illuminate\Support\Facades\Route;
use Modules\leadmanagement\Http\Controllers\LeadModuleController;

/*
 * Loaded by App\Providers\ModuleServiceProvider::boot() only while this
 * module's sdui_modules row is active. Paths are absolute from the domain
 * root (this file is required directly, it does not inherit the /api prefix
 * that routes/api.php gets).
 */
Route::middleware([AuthenticateTenantApi::class])
    ->prefix('api/tenant/lead-module')
    ->group(function () {
        Route::get('views/dashboard', [LeadModuleController::class, 'dashboard']);
        Route::get('views/leads', [LeadModuleController::class, 'leadsView']);
        Route::get('views/create-lead', [LeadModuleController::class, 'createLeadView']);
        Route::get('views/lead-detail', [LeadModuleController::class, 'leadDetail']);
        Route::get('views/leads/{id}', [LeadModuleController::class, 'leadDetail']);

        Route::get('leads/list', [LeadModuleController::class, 'leadsListEndpoint']);
        Route::get('leads/followups', [LeadModuleController::class, 'followupsEndpoint']);
        Route::get('leads', [LeadModuleController::class, 'leadsIndex']);
        Route::post('leads', [LeadModuleController::class, 'leadsStore']);
        Route::get('leads/{id}', [LeadModuleController::class, 'leadsShow']);
        Route::match(['put', 'patch'], 'leads/{id}', [LeadModuleController::class, 'leadsUpdate']);
        Route::post('leads/{id}/status', [LeadModuleController::class, 'leadStatus']);
        Route::post('leads/{id}/convert', [LeadModuleController::class, 'leadConvert']);
        Route::post('leads/{id}/convert-to-invoice', [LeadModuleController::class, 'leadConvertToInvoice']);
        Route::post('leads/{id}/reminders', [LeadModuleController::class, 'leadAddReminder']);

        Route::get('staff/sales-reps', [LeadModuleController::class, 'salesReps']);
        Route::get('customers/search', [LeadModuleController::class, 'customerSearch']);

        Route::get('views/activities', [LeadModuleController::class, 'activitiesView']);
        Route::post('activities', [LeadModuleController::class, 'activitiesStore']);
        Route::post('activities/{id}/complete', [LeadModuleController::class, 'activityComplete']);

        Route::get('views/sources', [LeadModuleController::class, 'sourcesView']);
        Route::post('sources', [LeadModuleController::class, 'sourcesStore']);
    });
