<?php

use App\Http\Controllers\Api\V1\PosDesktopSyncController;
use App\Http\Controllers\Api\V1\PosSyncApiController;
use App\Http\Controllers\Api\V1\TaxApiController;
use App\Http\Middleware\AuthenticateTenantApi;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| RESTful E-Invoicing & Fiscal Tax Engine API Routes
|--------------------------------------------------------------------------
| Versioned API endpoints (/api/v1/tax/*) secured with Tenant API Keys
| and bearer tokens for third-party ERPs, Shopify, WooCommerce, etc.
*/

Route::prefix('v1/tax')->middleware([AuthenticateTenantApi::class])->group(function () {
    Route::post('/calculate', [TaxApiController::class, 'calculate']);
    Route::post('/invoices', [TaxApiController::class, 'issueInvoice']);
    Route::get('/rates', [TaxApiController::class, 'getRates']);
    Route::get('/einvoice/{sale_id}/payload', [TaxApiController::class, 'getEInvoicePayload']);
});

/*
|--------------------------------------------------------------------------
| Dual-Mode POS Offline / Online Synchronization Engine
|--------------------------------------------------------------------------
| Endpoints for standalone desktop / PWA terminals to authenticate, sync
| catalog deltas, batch push offline transactions, manage inventory & customers,
| review business analytics, and manage tenant subscriptions.
*/
Route::prefix('v1/pos')->group(function () {
    // Public Tenant Auth & Registration Endpoints for Standalone / Dual-Mode Desktop Client
    Route::post('/auth/login', [PosSyncApiController::class, 'login']);
    Route::post('/auth/register', [PosSyncApiController::class, 'register']);

    // Protected POS Endpoints (Require API Key or Bearer Token)
    Route::middleware([AuthenticateTenantApi::class])->group(function () {
        Route::get('/auth/session', [PosSyncApiController::class, 'session']);
        Route::post('/auth/desktop-session', [PosSyncApiController::class, 'desktopWebSession']);
        Route::get('/status', [PosSyncApiController::class, 'status']);
        Route::get('/sync-catalog', [PosSyncApiController::class, 'syncPull'])->middleware('tenant.api.permission:products,view');
        Route::get('/sync-pull', [PosSyncApiController::class, 'syncPull'])->middleware('tenant.api.permission:products,view');
        Route::post('/sync-sales', [PosSyncApiController::class, 'syncPush'])->middleware('tenant.api.permission:pos,create');
        Route::post('/sync-push', [PosSyncApiController::class, 'syncPush'])->middleware('tenant.api.permission:pos,create');
        Route::post('/sync-batch', [PosSyncApiController::class, 'syncBatch'])->middleware('tenant.api.permission:pos,create');

        // Desktop Sync Engine: cash register, sales targets, consignments,
        // service orders and payables — modules the wire format above never covered.
        Route::get('/desktop-sync/pull', [PosDesktopSyncController::class, 'pull']);
        Route::post('/desktop-sync/push', [PosDesktopSyncController::class, 'push'])->middleware('tenant.api.permission:pos,create');

        // Inventory Management
        Route::get('/inventory', [PosSyncApiController::class, 'inventoryIndex'])->middleware('tenant.api.permission:products,view');
        Route::post('/inventory/product', [PosSyncApiController::class, 'inventoryStoreProduct'])->middleware('tenant.api.permission:products,create');
        Route::post('/inventory/adjust', [PosSyncApiController::class, 'inventoryAdjustStock'])->middleware('tenant.api.permission:products,edit');

        // Customer Ledger & Khata
        Route::get('/customers', [PosSyncApiController::class, 'customersIndex'])->middleware('tenant.api.permission:customers,view');
        Route::post('/customers', [PosSyncApiController::class, 'customersStore'])->middleware('tenant.api.permission:customers,create');
        Route::get('/customers/{id}/ledger', [PosSyncApiController::class, 'customerLedger'])->middleware('tenant.api.permission:customers,view');
        Route::post('/customers/{id}/payment', [PosSyncApiController::class, 'customerRecordPayment'])->middleware('tenant.api.permission:finance,edit');

        // Analytics & Reports
        Route::get('/analytics', [PosSyncApiController::class, 'analytics'])->middleware('tenant.api.permission:reports,view');

        // Outbound Delivery (WhatsApp / Email)
        Route::post('/send-delivery', [PosSyncApiController::class, 'sendDelivery'])->middleware('tenant.api.permission:pos,create');

        // Taxes & Tax Rules Management
        Route::get('/taxes', [PosSyncApiController::class, 'taxRulesIndex']);
        Route::post('/taxes', [PosSyncApiController::class, 'taxRulesStore'])->middleware('tenant.api.permission:settings,view');

        // Subscription & Billing
        Route::get('/subscription', [PosSyncApiController::class, 'subscription']);
        Route::post('/subscription/redeem', [PosSyncApiController::class, 'subscriptionRedeem']);
    });
});
