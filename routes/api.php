<?php

use App\Http\Controllers\Api\V1\CashRegisterApiController;
use App\Http\Controllers\Api\V1\CatalogAdminApiController;
use App\Http\Controllers\Api\V1\CatalogApiController;
use App\Http\Controllers\Api\V1\ConsignmentApiController;
use App\Http\Controllers\Api\V1\DeviceApiController;
use App\Http\Controllers\Api\V1\LanguageApiController;
use App\Http\Controllers\Api\V1\PayablesApiController;
use App\Http\Controllers\Api\V1\PermissionApiController;
use App\Http\Controllers\Api\V1\PosDesktopSyncController;
use App\Http\Controllers\Api\V1\PosSyncApiController;
use App\Http\Controllers\Api\V1\QuotationApiController;
use App\Http\Controllers\Api\V1\ReportsApiController;
use App\Http\Controllers\Api\V1\SalesTargetApiController;
use App\Http\Controllers\Api\V1\ServiceOrderApiController;
use App\Http\Controllers\Api\V1\SettingsApiController;
use App\Http\Controllers\Api\V1\TaxApiController;
use App\Http\Controllers\Api\V1\UserApiController;
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
        Route::post('/inventory/product/{id}/image', [PosSyncApiController::class, 'inventoryUploadProductImage'])->middleware('tenant.api.permission:products,edit');
        Route::post('/inventory/import', [PosSyncApiController::class, 'inventoryBulkImport'])->middleware('tenant.api.permission:products,create');
        Route::post('/inventory/adjust', [PosSyncApiController::class, 'inventoryAdjustStock'])->middleware('tenant.api.permission:products,edit');

        // Customer Ledger & Khata
        Route::get('/customers', [PosSyncApiController::class, 'customersIndex'])->middleware('tenant.api.permission:customers,view');
        Route::post('/customers', [PosSyncApiController::class, 'customersStore'])->middleware('tenant.api.permission:customers,create');
        Route::get('/customers/{id}/ledger', [PosSyncApiController::class, 'customerLedger'])->middleware('tenant.api.permission:customers,view');
        Route::post('/customers/{id}/payment', [PosSyncApiController::class, 'customerRecordPayment'])->middleware('tenant.api.permission:finance,edit');

        // Due Payments / Receivables dashboard panel
        Route::get('/receivables/due', [PosSyncApiController::class, 'dueReceivables'])->middleware('tenant.api.permission:customers,view');
        Route::post('/receivables/{sale}/remind', [PosSyncApiController::class, 'remindReceivable'])->middleware('tenant.api.permission:finance,edit');

        // Analytics & Reports
        Route::get('/analytics', [PosSyncApiController::class, 'analytics'])->middleware('tenant.api.permission:reports,view');

        // Outbound Delivery (WhatsApp / Email)
        Route::post('/send-delivery', [PosSyncApiController::class, 'sendDelivery'])->middleware('tenant.api.permission:pos,create');

        // Invoice PDF (Preview / Print / Share)
        Route::get('/sales/{id}/pdf', [PosSyncApiController::class, 'salePdf'])->middleware('tenant.api.permission:sales,view');

        // Taxes & Tax Rules Management
        Route::get('/taxes', [PosSyncApiController::class, 'taxRulesIndex']);
        Route::post('/taxes', [PosSyncApiController::class, 'taxRulesStore'])->middleware('tenant.api.permission:settings,view');
        Route::put('/taxes/{id}', [PosSyncApiController::class, 'taxRulesUpdate'])->middleware('tenant.api.permission:settings,edit');
        Route::delete('/taxes/{id}', [PosSyncApiController::class, 'taxRulesDestroy'])->middleware('tenant.api.permission:settings,edit');
        Route::post('/taxes/{id}/set-default', [PosSyncApiController::class, 'taxRulesSetDefault'])->middleware('tenant.api.permission:settings,edit');

        // Subscription & Billing
        Route::get('/subscription', [PosSyncApiController::class, 'subscription']);
        Route::post('/subscription/redeem', [PosSyncApiController::class, 'subscriptionRedeem']);
        Route::post('/subscription/plans/{plan}/activate-free', [PosSyncApiController::class, 'subscriptionActivateFree']);
        Route::post('/subscription/plans/{plan}/razorpay/order', [PosSyncApiController::class, 'subscriptionRazorpayOrder']);
        Route::post('/subscription/plans/{plan}/razorpay/verify', [PosSyncApiController::class, 'subscriptionRazorpayVerify']);
        Route::post('/subscription/plans/{plan}/mercadopago/preference', [PosSyncApiController::class, 'subscriptionMercadoPagoPreference']);
        Route::post('/subscription/plans/{plan}/mercadopago/verify', [PosSyncApiController::class, 'subscriptionMercadoPagoVerify']);

        // Quotations (Sale rows with operation_type=quotation)
        Route::get('/quotations', [QuotationApiController::class, 'index'])->middleware('tenant.api.permission:quotes,view');
        Route::post('/quotations', [QuotationApiController::class, 'store'])->middleware('tenant.api.permission:quotes,create');
        Route::get('/quotations/{id}', [QuotationApiController::class, 'show'])->middleware('tenant.api.permission:quotes,view');
        Route::put('/quotations/{id}', [QuotationApiController::class, 'update'])->middleware('tenant.api.permission:quotes,edit');
        Route::delete('/quotations/{id}', [QuotationApiController::class, 'destroy'])->middleware('tenant.api.permission:quotes,edit');
        Route::post('/quotations/{id}/convert', [QuotationApiController::class, 'convert'])->middleware('tenant.api.permission:quotes,edit');
        Route::get('/quotations/{id}/pdf', [QuotationApiController::class, 'pdf'])->middleware('tenant.api.permission:quotes,view');

        // Cash Register
        Route::get('/cash-register/current', [CashRegisterApiController::class, 'current'])->middleware('tenant.api.permission:cash_register,view');
        Route::post('/cash-register/open', [CashRegisterApiController::class, 'open'])->middleware('tenant.api.permission:cash_register,create');
        Route::get('/cash-register/history', [CashRegisterApiController::class, 'history'])->middleware('tenant.api.permission:cash_register,view');
        Route::get('/cash-register/{id}', [CashRegisterApiController::class, 'show'])->middleware('tenant.api.permission:cash_register,view');
        Route::post('/cash-register/{id}/transaction', [CashRegisterApiController::class, 'recordTransaction'])->middleware('tenant.api.permission:cash_register,edit');
        Route::post('/cash-register/{id}/close', [CashRegisterApiController::class, 'close'])->middleware('tenant.api.permission:cash_register,edit');

        // Accounts Payable (Vendor Bills)
        Route::get('/payables', [PayablesApiController::class, 'index'])->middleware('tenant.api.permission:finance,view');
        Route::post('/payables', [PayablesApiController::class, 'store'])->middleware('tenant.api.permission:finance,create');
        Route::put('/payables/{id}', [PayablesApiController::class, 'update'])->middleware('tenant.api.permission:finance,edit');
        Route::delete('/payables/{id}', [PayablesApiController::class, 'destroy'])->middleware('tenant.api.permission:finance,edit');
        Route::post('/payables/{id}/pay', [PayablesApiController::class, 'pay'])->middleware('tenant.api.permission:finance,edit');

        // Reports (Sales Summary / DRE / Payment Methods / Till Closings / Commissions / Aging + CSV export)
        Route::get('/reports/summary', [ReportsApiController::class, 'summary'])->middleware('tenant.api.permission:reports,view');
        Route::get('/reports/profit-loss', [ReportsApiController::class, 'profitLoss'])->middleware('tenant.api.permission:reports,view');
        Route::get('/reports/payment-methods', [ReportsApiController::class, 'paymentMethods'])->middleware('tenant.api.permission:reports,view');
        Route::get('/reports/till-closings', [ReportsApiController::class, 'tillClosings'])->middleware('tenant.api.permission:reports,view');
        Route::get('/reports/commissions', [ReportsApiController::class, 'commissions'])->middleware('tenant.api.permission:reports,view');
        Route::get('/reports/aging', [ReportsApiController::class, 'aging'])->middleware('tenant.api.permission:reports,view');
        Route::get('/reports/export', [ReportsApiController::class, 'export'])->middleware('tenant.api.permission:reports,view');

        // Catalog Admin: Categories, Brands, Units, Suppliers
        Route::get('/categories', [CatalogAdminApiController::class, 'categoriesIndex'])->middleware('tenant.api.permission:categories,view');
        Route::post('/categories', [CatalogAdminApiController::class, 'categoriesStore'])->middleware('tenant.api.permission:categories,create');
        Route::put('/categories/{id}', [CatalogAdminApiController::class, 'categoriesUpdate'])->middleware('tenant.api.permission:categories,edit');
        Route::delete('/categories/{id}', [CatalogAdminApiController::class, 'categoriesDestroy'])->middleware('tenant.api.permission:categories,edit');

        Route::get('/brands', [CatalogAdminApiController::class, 'brandsIndex'])->middleware('tenant.api.permission:categories,view');
        Route::post('/brands', [CatalogAdminApiController::class, 'brandsStore'])->middleware('tenant.api.permission:categories,create');
        Route::put('/brands/{id}', [CatalogAdminApiController::class, 'brandsUpdate'])->middleware('tenant.api.permission:categories,edit');
        Route::delete('/brands/{id}', [CatalogAdminApiController::class, 'brandsDestroy'])->middleware('tenant.api.permission:categories,edit');

        Route::get('/units', [CatalogAdminApiController::class, 'unitsIndex'])->middleware('tenant.api.permission:units,view');
        Route::post('/units', [CatalogAdminApiController::class, 'unitsStore'])->middleware('tenant.api.permission:units,create');
        Route::put('/units/{id}', [CatalogAdminApiController::class, 'unitsUpdate'])->middleware('tenant.api.permission:units,edit');
        Route::delete('/units/{id}', [CatalogAdminApiController::class, 'unitsDestroy'])->middleware('tenant.api.permission:units,edit');

        Route::get('/suppliers', [CatalogAdminApiController::class, 'suppliersIndex'])->middleware('tenant.api.permission:suppliers,view');
        Route::post('/suppliers', [CatalogAdminApiController::class, 'suppliersStore'])->middleware('tenant.api.permission:suppliers,create');
        Route::put('/suppliers/{id}', [CatalogAdminApiController::class, 'suppliersUpdate'])->middleware('tenant.api.permission:suppliers,edit');
        Route::delete('/suppliers/{id}', [CatalogAdminApiController::class, 'suppliersDestroy'])->middleware('tenant.api.permission:suppliers,edit');

        // Settings: Profile / Receipts / Financial / Notifications / Payment Methods
        Route::get('/settings', [SettingsApiController::class, 'index'])->middleware('tenant.api.permission:settings,view');
        Route::put('/settings/profile', [SettingsApiController::class, 'updateProfile'])->middleware('tenant.api.permission:settings,edit');
        Route::post('/settings/profile/logo', [SettingsApiController::class, 'uploadLogo'])->middleware('tenant.api.permission:settings,edit');
        Route::delete('/settings/profile/logo', [SettingsApiController::class, 'removeLogo'])->middleware('tenant.api.permission:settings,edit');
        Route::post('/settings/profile/favicon', [SettingsApiController::class, 'uploadFavicon'])->middleware('tenant.api.permission:settings,edit');
        Route::delete('/settings/profile/favicon', [SettingsApiController::class, 'removeFavicon'])->middleware('tenant.api.permission:settings,edit');
        Route::put('/settings/receipts', [SettingsApiController::class, 'updateReceipts'])->middleware('tenant.api.permission:settings,edit');
        Route::put('/settings/financial', [SettingsApiController::class, 'updateFinancial'])->middleware('tenant.api.permission:settings,edit');
        Route::put('/settings/notifications', [SettingsApiController::class, 'updateNotifications'])->middleware('tenant.api.permission:settings,edit');
        Route::post('/settings/notifications/test-email', [SettingsApiController::class, 'testEmail'])->middleware('tenant.api.permission:settings,edit');

        Route::get('/settings/payment-methods', [SettingsApiController::class, 'paymentMethodsIndex'])->middleware('tenant.api.permission:settings,view');
        Route::post('/settings/payment-methods', [SettingsApiController::class, 'paymentMethodsStore'])->middleware('tenant.api.permission:settings,edit');
        Route::put('/settings/payment-methods/{id}', [SettingsApiController::class, 'paymentMethodsUpdate'])->middleware('tenant.api.permission:settings,edit');
        Route::delete('/settings/payment-methods/{id}', [SettingsApiController::class, 'paymentMethodsDestroy'])->middleware('tenant.api.permission:settings,edit');
        Route::post('/settings/payment-methods/{id}/toggle', [SettingsApiController::class, 'paymentMethodsToggle'])->middleware('tenant.api.permission:settings,edit');
        Route::get('/settings/payment-methods/{id}/transactions', [SettingsApiController::class, 'paymentMethodTransactions'])->middleware('tenant.api.permission:settings,view');
        Route::get('/settings/payment-methods/{id}/transactions/export', [SettingsApiController::class, 'paymentMethodTransactionsExport'])->middleware('tenant.api.permission:settings,view');

        Route::get('/settings/notification-channels', [SettingsApiController::class, 'notificationChannelsIndex'])->middleware('tenant.api.permission:settings,view');
        Route::post('/settings/notification-channels', [SettingsApiController::class, 'notificationChannelsStore'])->middleware('tenant.api.permission:settings,edit');
        Route::put('/settings/notification-channels/{id}', [SettingsApiController::class, 'notificationChannelsUpdate'])->middleware('tenant.api.permission:settings,edit');
        Route::delete('/settings/notification-channels/{id}', [SettingsApiController::class, 'notificationChannelsDestroy'])->middleware('tenant.api.permission:settings,edit');

        // Consignments (draft -> dispatched -> reconciled -> finalized)
        Route::get('/consignments', [ConsignmentApiController::class, 'index'])->middleware('tenant.api.permission:consignments,view');
        Route::post('/consignments', [ConsignmentApiController::class, 'store'])->middleware('tenant.api.permission:consignments,create');
        Route::get('/consignments/{id}', [ConsignmentApiController::class, 'show'])->middleware('tenant.api.permission:consignments,view');
        Route::delete('/consignments/{id}', [ConsignmentApiController::class, 'destroy'])->middleware('tenant.api.permission:consignments,edit');
        Route::post('/consignments/{id}/dispatch', [ConsignmentApiController::class, 'dispatch'])->middleware('tenant.api.permission:consignments,edit');
        Route::post('/consignments/{id}/reconcile', [ConsignmentApiController::class, 'reconcile'])->middleware('tenant.api.permission:consignments,edit');
        Route::post('/consignments/{id}/finalize', [ConsignmentApiController::class, 'finalize'])->middleware('tenant.api.permission:consignments,edit');

        // Service Orders (Repairs / Warranty)
        Route::get('/service-orders', [ServiceOrderApiController::class, 'index'])->middleware('tenant.api.permission:service_orders,view');
        Route::post('/service-orders', [ServiceOrderApiController::class, 'store'])->middleware('tenant.api.permission:service_orders,create');
        Route::get('/service-orders/{id}', [ServiceOrderApiController::class, 'show'])->middleware('tenant.api.permission:service_orders,view');
        Route::put('/service-orders/{id}', [ServiceOrderApiController::class, 'update'])->middleware('tenant.api.permission:service_orders,edit');
        Route::post('/service-orders/{id}/status', [ServiceOrderApiController::class, 'updateStatus'])->middleware('tenant.api.permission:service_orders,edit');
        Route::delete('/service-orders/{id}', [ServiceOrderApiController::class, 'destroy'])->middleware('tenant.api.permission:service_orders,edit');

        // Sales Targets & Goals
        Route::get('/sales-targets', [SalesTargetApiController::class, 'index'])->middleware('tenant.api.permission:targets,view');
        Route::post('/sales-targets', [SalesTargetApiController::class, 'store'])->middleware('tenant.api.permission:targets,edit');

        // Users & Permissions (impersonation intentionally not exposed)
        Route::get('/users', [UserApiController::class, 'index'])->middleware('tenant.api.permission:users,view');
        Route::post('/users/invite', [UserApiController::class, 'invite'])->middleware('tenant.api.permission:users,create');
        Route::post('/users/{id}/resend-invite', [UserApiController::class, 'resendInvite'])->middleware('tenant.api.permission:users,edit');
        Route::put('/users/{id}/role', [UserApiController::class, 'updateRole'])->middleware('tenant.api.permission:users,edit');
        Route::post('/users/{id}/toggle-status', [UserApiController::class, 'toggleStatus'])->middleware('tenant.api.permission:users,edit');
        Route::put('/users/{id}/commission', [UserApiController::class, 'updateCommission'])->middleware('tenant.api.permission:users,edit');
        Route::delete('/users/{id}', [UserApiController::class, 'destroy'])->middleware('tenant.api.permission:users,edit');
        Route::get('/users/{id}/permissions', [PermissionApiController::class, 'show'])->middleware('tenant.api.permission:users,view');
        Route::put('/users/{id}/permissions', [PermissionApiController::class, 'update'])->middleware('tenant.api.permission:users,edit');

        // Online Catalog
        Route::get('/catalog', [CatalogApiController::class, 'index'])->middleware('tenant.api.permission:catalog,view');
        Route::post('/catalog', [CatalogApiController::class, 'store'])->middleware('tenant.api.permission:catalog,create');
        Route::delete('/catalog/{id}', [CatalogApiController::class, 'destroy'])->middleware('tenant.api.permission:catalog,edit');

        // Devices (active web-login sessions — not TenantApiKey terminals)
        Route::get('/devices', [DeviceApiController::class, 'index']);
        Route::post('/devices/{token}/revoke', [DeviceApiController::class, 'revoke']);

        // Languages (store default language only — see LanguageApiController)
        Route::get('/languages', [LanguageApiController::class, 'index'])->middleware('tenant.api.permission:settings,view');
        Route::put('/languages/default', [LanguageApiController::class, 'setDefault'])->middleware('tenant.api.permission:settings,edit');
    });
});
