<?php

use App\Http\Controllers\Api\V1\AiImageApiController;
use App\Http\Controllers\Api\V1\AppBootstrapController;
use App\Http\Controllers\Api\V1\AuthApiController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\Api\V1\CashRegisterApiController;
use App\Http\Controllers\Api\V1\CatalogAdminApiController;
use App\Http\Controllers\Api\V1\CatalogApiController;
use App\Http\Controllers\Api\V1\ConsignmentApiController;
use App\Http\Controllers\Api\V1\DeviceApiController;
use App\Http\Controllers\Api\V1\EcommerceWebhookController;
use App\Http\Controllers\Api\V1\LanguageApiController;
use App\Http\Controllers\Api\V1\PayablesApiController;
use App\Http\Controllers\Api\V1\PermissionApiController;
use App\Http\Controllers\Api\V1\PharmacyApiController;
use App\Http\Controllers\Api\V1\RoleApiController;
use App\Http\Controllers\Api\V1\PosDesktopSyncController;
use App\Http\Controllers\Api\V1\PosSyncApiController;
use App\Http\Controllers\Api\V1\PushDeviceApiController;
use App\Http\Controllers\Api\V1\QuotationApiController;
use App\Http\Controllers\Api\V1\RepairApiController;
use App\Http\Controllers\Api\V1\ReportsApiController;
use App\Http\Controllers\Api\V1\RestaurantApiController;
use App\Http\Controllers\Api\V1\SaleApiController;
use App\Http\Controllers\Api\V1\SalesTargetApiController;
use App\Http\Controllers\Api\V1\SalonApiController;
use App\Http\Controllers\Api\V1\SduiViewController;
use App\Http\Controllers\Api\V1\ServiceOrderApiController;
use App\Http\Controllers\Api\V1\SettingsApiController;
use App\Http\Controllers\Api\V1\TaxApiController;
use App\Http\Controllers\Api\V1\TenantDemoDataController;
use App\Http\Controllers\Api\V1\UploadApiController;
use App\Http\Controllers\Api\V1\UserApiController;
use App\Http\Controllers\Tenant\Auth\PasswordResetController;
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
| Tenant Password Reset & Change (Mobile / Desktop)
|--------------------------------------------------------------------------
*/
Route::prefix('tenant/password')->group(function () {
    Route::post('/email', [PasswordResetController::class, 'sendResetLink']);
    Route::post('/reset', [PasswordResetController::class, 'reset']);
});

Route::post('/tenant/profile/change-password', [PasswordResetController::class, 'changePassword'])
    ->middleware([AuthenticateTenantApi::class]);

// Unauthenticated Dynamic Store Registration Metadata
Route::get('/app/registration-meta', [PosSyncApiController::class, 'registrationMeta']);
Route::get('/app/auth-config', [PosSyncApiController::class, 'authConfig']);
Route::get('/api/app/auth-config', [PosSyncApiController::class, 'authConfig']);

// Authentication, Registration, Email OTP & Social Auth
Route::post('/auth/register', [AuthApiController::class, 'register']);
Route::post('/register', [AuthApiController::class, 'register']);
Route::post('/auth/verify-email-otp', [AuthApiController::class, 'verifyEmailOtp']);
Route::post('/api/auth/verify-email-otp', [AuthApiController::class, 'verifyEmailOtp']);
Route::post('/app/verify-otp', [AuthApiController::class, 'verifyEmailOtp']);
Route::post('/api/app/verify-otp', [AuthApiController::class, 'verifyEmailOtp']);
Route::post('/auth/verify-otp', [AuthApiController::class, 'verifyEmailOtp']);
Route::post('/api/auth/verify-otp', [AuthApiController::class, 'verifyEmailOtp']);
Route::post('/auth/resend-otp', [AuthApiController::class, 'resendOtp']);
Route::post('/app/resend-otp', [AuthApiController::class, 'resendOtp']);
Route::post('/api/app/resend-otp', [AuthApiController::class, 'resendOtp']);
Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect']);
Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback']);
Route::post('/auth/{provider}/mobile-token', [SocialAuthController::class, 'mobileToken']);
Route::post('/api/auth/{provider}/mobile-token', [SocialAuthController::class, 'mobileToken']);

// Signed, login-free invoice PDF — opened by the native Post-Sale Action
// Sheet's "Preview & Print" row. The URL signature is the authorization, so
// it renders in the device browser without a web session (no /login bounce).
Route::get('/tenant/receipt/{sale}/pdf', [\App\Http\Controllers\Tenant\InvoiceController::class, 'signedPdf'])
    ->middleware('signed')
    ->name('receipt.signed.pdf');

// Inbound Two-Way E-Commerce Webhook Receiver (Shopify / WooCommerce / Generic)
Route::post('/v1/integrations/webhooks/{tenant_uuid}/orders', [EcommerceWebhookController::class, 'handleOrders']);
Route::post('/integrations/webhooks/{tenant_uuid}/orders', [EcommerceWebhookController::class, 'handleOrders']);

// Server-Driven UI Bootstrap, View Schemas, and Form Action Routes
Route::middleware([AuthenticateTenantApi::class])->group(function () {
    Route::get('/app/bootstrap', [AppBootstrapController::class, 'bootstrap']);
    Route::get('/app/translations', [LanguageApiController::class, 'appTranslations']);
    Route::post('/app/mode', [AppBootstrapController::class, 'switchMode'])->middleware('tenant.api.permission:settings,edit');

    // Server-Driven UI Dynamic Schema Views
    Route::get('/tenant/views/{view}', [SduiViewController::class, 'show']);
    Route::get('/app/views/{view}', [SduiViewController::class, 'show']);

    // Universal POS Checkout & Drawer Endpoints
    Route::get('/tenant/pos/checkout-sheet', [SaleApiController::class, 'checkoutSheet'])->middleware('tenant.api.permission:pos,view');
    Route::get('/tenant/pos/cart-sheet', [SaleApiController::class, 'checkoutSheet'])->middleware('tenant.api.permission:pos,view');
    Route::post('/tenant/pos/checkout', [SaleApiController::class, 'checkout'])->middleware('tenant.api.permission:pos,create');
    Route::post('/tenant/pos/hold-order', [SaleApiController::class, 'holdOrder'])->middleware('tenant.api.permission:pos,create');
    Route::get('/app/pos/checkout-sheet', [SaleApiController::class, 'checkoutSheet'])->middleware('tenant.api.permission:pos,view');
    Route::post('/app/pos/checkout', [SaleApiController::class, 'checkout'])->middleware('tenant.api.permission:pos,create');
    Route::post('/app/pos/hold-order', [SaleApiController::class, 'holdOrder'])->middleware('tenant.api.permission:pos,create');

    // Custom Roles & Granular Permissions — unversioned tenant URLs emitted by
    // the SDUI "Manage Roles" screen (mirrors the /v1/pos/roles endpoints).
    Route::get('/tenant/roles', [RoleApiController::class, 'index'])->middleware('tenant.api.permission:users,view');
    Route::post('/tenant/roles', [RoleApiController::class, 'store'])->middleware('tenant.api.permission:users,create');
    Route::match(['put', 'patch'], '/tenant/roles/{id}', [RoleApiController::class, 'update'])->middleware('tenant.api.permission:users,edit');
    Route::delete('/tenant/roles/{id}', [RoleApiController::class, 'destroy'])->middleware('tenant.api.permission:users,edit');

    // Native mobile cash-register contract. These unversioned tenant URLs
    // are emitted by SchemaResponse and intentionally coexist with the
    // versioned /v1/pos endpoints used by desktop/offline clients.
    Route::prefix('tenant/cash-register')->group(function () {
        Route::get('/status', [CashRegisterApiController::class, 'status'])->middleware('tenant.api.permission:cash_register,view');
        Route::post('/open', [CashRegisterApiController::class, 'openRegister'])->middleware('tenant.api.permission:cash_register,create');
        Route::post('/close', [CashRegisterApiController::class, 'closeRegister'])->middleware('tenant.api.permission:cash_register,edit');
        Route::get('/history', [CashRegisterApiController::class, 'history'])->middleware('tenant.api.permission:cash_register,view');
        Route::post('/{id}/transaction', [CashRegisterApiController::class, 'recordTransaction'])->middleware('tenant.api.permission:cash_register,edit');
    });

    // Custom Notification Channels (SMS & Unofficial WhatsApp Gateways)
    Route::prefix('tenant/settings/custom-notifications')->group(function () {
        Route::get('/', [SettingsApiController::class, 'notificationChannelsIndex']);
        Route::post('/', [SettingsApiController::class, 'notificationChannelsStore']);
        Route::put('/{id}', [SettingsApiController::class, 'notificationChannelsUpdate']);
        Route::delete('/{id}', [SettingsApiController::class, 'notificationChannelsDestroy']);
        Route::post('/test', [SettingsApiController::class, 'testNotificationChannel']);
    });
    Route::prefix('pos/settings/custom-notifications')->group(function () {
        Route::get('/', [SettingsApiController::class, 'notificationChannelsIndex']);
        Route::post('/', [SettingsApiController::class, 'notificationChannelsStore']);
        Route::put('/{id}', [SettingsApiController::class, 'notificationChannelsUpdate']);
        Route::delete('/{id}', [SettingsApiController::class, 'notificationChannelsDestroy']);
        Route::post('/test', [SettingsApiController::class, 'testNotificationChannel']);
    });

    // Secure tenant file uploads (non-executable image/PDF whitelist) — backs
    // the SDUI `file_picker` component (e.g. prescription attachments).
    Route::prefix('tenant/uploads')->group(function () {
        Route::post('/prescription-doc', [UploadApiController::class, 'uploadRxAttachment'])
            ->middleware('tenant.api.permission:sales,create');
    });

    // Pharmacy POS Module Routes
    Route::prefix('tenant/pharmacy')->group(function () {
        Route::get('/search', [PharmacyApiController::class, 'search'])->middleware('tenant.api.permission:products,view');
        Route::post('/checkout', [PharmacyApiController::class, 'checkout'])->middleware('tenant.api.permission:pos,create');
        Route::get('/batch-sheet', [PharmacyApiController::class, 'batchSheet'])->middleware('tenant.api.permission:products,view');
        Route::get('/checkout-sheet', [PharmacyApiController::class, 'checkoutSheet'])->middleware('tenant.api.permission:pos,view');
        Route::get('/batches', [PharmacyApiController::class, 'batchesIndex'])->middleware('tenant.api.permission:products,view');
        Route::get('/batches/{id}/barcode', [PharmacyApiController::class, 'batchBarcode'])->middleware('tenant.api.permission:products,view');
        Route::post('/batches', [PharmacyApiController::class, 'batchesStore'])->middleware('tenant.api.permission:products,create');
        Route::post('/batches/adjust', [PharmacyApiController::class, 'batchesAdjust'])->middleware('tenant.api.permission:products,edit');
        Route::post('/batches/{id}/adjust', [PharmacyApiController::class, 'batchesAdjust'])->middleware('tenant.api.permission:products,edit');
        Route::post('/batches/return', [PharmacyApiController::class, 'batchesReturn'])->middleware('tenant.api.permission:products,edit');
        Route::post('/batches/{id}/return', [PharmacyApiController::class, 'batchesReturn'])->middleware('tenant.api.permission:products,edit');
        Route::get('/prescriptions', [PharmacyApiController::class, 'prescriptionsIndex'])->middleware('tenant.api.permission:sales,view');
        Route::post('/prescriptions', [PharmacyApiController::class, 'prescriptionsStore'])->middleware('tenant.api.permission:sales,create');
        Route::get('/prescriptions/{id}/checkout-sheet', [PharmacyApiController::class, 'prescriptionCheckoutSheet'])->middleware('tenant.api.permission:pos,view');
        Route::post('/prescriptions/{id}/checkout', [PharmacyApiController::class, 'prescriptionCheckout'])->middleware('tenant.api.permission:pos,create');
        Route::post('/prescriptions/{id}/dispense', [PharmacyApiController::class, 'prescriptionsDispense'])->middleware('tenant.api.permission:pos,edit');
    });

    // Repair & Technician POS Module Routes
    Route::prefix('tenant/repair')->group(function () {
        Route::get('/stats', [RepairApiController::class, 'stats'])->middleware('tenant.api.permission:repair,view');
        Route::get('/categories', [RepairApiController::class, 'categoriesIndex'])->middleware('tenant.api.permission:repair,view');
        Route::post('/categories', [RepairApiController::class, 'categoriesStore'])->middleware('tenant.api.permission:repair,create');
        Route::match(['put', 'patch', 'post'], '/categories/{id}', [RepairApiController::class, 'categoriesUpdate'])->middleware('tenant.api.permission:repair,diagnose');
        Route::delete('/categories/{id}', [RepairApiController::class, 'categoriesDestroy'])->middleware('tenant.api.permission:repair,delete');
        Route::get('/tickets', [RepairApiController::class, 'ticketsIndex'])->middleware('tenant.api.permission:repair,view');
        Route::post('/tickets', [RepairApiController::class, 'ticketsStore'])->middleware('tenant.api.permission:repair,create');
        Route::get('/tickets/{id}', [RepairApiController::class, 'ticketsShow'])->middleware('tenant.api.permission:repair,view');
        Route::delete('/tickets/{id}', [RepairApiController::class, 'ticketsDestroy'])->middleware('tenant.api.permission:repair,delete');
        Route::post('/tickets/{id}/status', [RepairApiController::class, 'ticketsUpdateStatus'])->middleware('tenant.api.permission:repair,diagnose');
        Route::match(['get', 'post'], '/tickets/{id}/share', [RepairApiController::class, 'ticketsShareSheet'])->middleware('tenant.api.permission:repair,view');
        Route::post('/tickets/{id}/assign', [RepairApiController::class, 'ticketsAssign'])->middleware('tenant.api.permission:repair,assign');
        Route::post('/tickets/{id}/parts', [RepairApiController::class, 'ticketsAddPart'])->middleware('tenant.api.permission:repair,diagnose');
        Route::delete('/tickets/{ticketId}/parts/{partId}', [RepairApiController::class, 'ticketsRemovePart'])->middleware('tenant.api.permission:repair,diagnose');
        Route::post('/tickets/{id}/labor', [RepairApiController::class, 'ticketsSetLabor'])->middleware('tenant.api.permission:repair,diagnose');
        Route::post('/tickets/{id}/checklist', [RepairApiController::class, 'ticketsUpdateChecklist'])->middleware('tenant.api.permission:repair,diagnose');
        Route::post('/tickets/{id}/settle', [RepairApiController::class, 'ticketsSettle'])->middleware('tenant.api.permission:repair,checkout');
        Route::get('/tickets/{id}/checkout-sheet', [RepairApiController::class, 'ticketCheckoutSheet'])->middleware('tenant.api.permission:repair,view');
        Route::get('/tickets/{id}/intake-sheet', [RepairApiController::class, 'ticketIntakeSheet'])->middleware('tenant.api.permission:repair,view');
        Route::get('/checkout-sheet', [RepairApiController::class, 'checkoutSheet'])->middleware('tenant.api.permission:repair,view');
        Route::post('/pos-checkout', [RepairApiController::class, 'posCheckout'])->middleware('tenant.api.permission:repair,checkout');
        Route::post('/checkout', [RepairApiController::class, 'posCheckout'])->middleware('tenant.api.permission:repair,checkout');
    });

    // Salon & Service POS Module Routes
    Route::prefix('tenant/salon')->group(function () {
        Route::get('/specialist-sheet', [SalonApiController::class, 'specialistSheet'])->middleware('tenant.api.permission:service_orders,view');
        Route::get('/checkout-sheet', [SalonApiController::class, 'checkoutSheet'])->middleware('tenant.api.permission:pos,view');
        Route::post('/pos-checkout', [SalonApiController::class, 'posCheckout'])->middleware('tenant.api.permission:pos,create');
        Route::post('/checkout', [SalonApiController::class, 'posCheckout'])->middleware('tenant.api.permission:pos,create');
        Route::get('/appointments', [SalonApiController::class, 'appointmentsIndex'])->middleware('tenant.api.permission:service_orders,view');
        Route::post('/appointments', [SalonApiController::class, 'appointmentsStore'])->middleware('tenant.api.permission:service_orders,create');
        Route::get('/appointments/availability', [SalonApiController::class, 'appointmentsAvailability'])->middleware('tenant.api.permission:service_orders,view');
        Route::post('/appointments/{id}/status', [SalonApiController::class, 'appointmentsUpdateStatus'])->middleware('tenant.api.permission:service_orders,edit');
        Route::get('/specialists', [SalonApiController::class, 'specialistsIndex'])->middleware('tenant.api.permission:users,view');
        Route::post('/specialists/{id}/toggle', [SalonApiController::class, 'specialistsToggle'])->middleware('tenant.api.permission:users,edit');
        Route::get('/services', [SalonApiController::class, 'servicesIndex'])->middleware('tenant.api.permission:service_orders,view');
        Route::post('/services', [SalonApiController::class, 'servicesStore'])->middleware('tenant.api.permission:service_orders,create');
        Route::get('/services/{id}/edit-sheet', [SalonApiController::class, 'servicesEditSheet'])->middleware('tenant.api.permission:service_orders,view');
        Route::match(['post', 'put'], '/services/{id}', [SalonApiController::class, 'servicesUpdate'])->middleware('tenant.api.permission:service_orders,edit');
        Route::delete('/services/{id}', [SalonApiController::class, 'servicesDestroy'])->middleware('tenant.api.permission:service_orders,edit');
        Route::post('/services/{id}/delete', [SalonApiController::class, 'servicesDestroy'])->middleware('tenant.api.permission:service_orders,edit');
    });

    // One-Click Demo Data Purge
    Route::delete('/tenant/demo-data', [TenantDemoDataController::class, 'destroy'])->middleware('tenant.api.permission:settings,edit');
    Route::delete('/app/demo-data', [TenantDemoDataController::class, 'destroy'])->middleware('tenant.api.permission:settings,edit');

    // Navigation Labels & Custom Display Names
    Route::get('/tenant/settings/navigation-labels', [SettingsApiController::class, 'getNavigationLabels'])->middleware('tenant.api.permission:settings,view');
    Route::post('/tenant/settings/navigation-labels', [SettingsApiController::class, 'updateNavigationLabels'])->middleware('tenant.api.permission:settings,edit');
    Route::get('/app/settings/navigation-labels', [SettingsApiController::class, 'getNavigationLabels'])->middleware('tenant.api.permission:settings,view');
    Route::post('/app/settings/navigation-labels', [SettingsApiController::class, 'updateNavigationLabels'])->middleware('tenant.api.permission:settings,edit');

    // Dynamic SDUI Navigation Drawer Tree
    Route::get('/tenant/navigation/drawer', [SettingsApiController::class, 'getDrawerNavigation']);
    Route::get('/app/navigation/drawer', [SettingsApiController::class, 'getDrawerNavigation']);
    Route::get('/tenant/navigation', [SettingsApiController::class, 'getDrawerNavigation']);
    Route::get('/app/navigation', [SettingsApiController::class, 'getDrawerNavigation']);

    // Form Field Labels & Dynamic Custom Fields
    Route::get('/tenant/settings/form-labels', [SettingsApiController::class, 'getFormLabels'])->middleware('tenant.api.permission:settings,view');
    Route::post('/tenant/settings/form-labels', [SettingsApiController::class, 'updateFormLabels'])->middleware('tenant.api.permission:settings,edit');
    Route::get('/app/settings/form-labels', [SettingsApiController::class, 'getFormLabels'])->middleware('tenant.api.permission:settings,view');
    Route::post('/app/settings/form-labels', [SettingsApiController::class, 'updateFormLabels'])->middleware('tenant.api.permission:settings,edit');

    Route::delete('/tenant/products/{id}', [PosSyncApiController::class, 'inventoryDestroyProduct'])->middleware('tenant.api.permission:products,edit');
    Route::delete('/app/products/{id}', [PosSyncApiController::class, 'inventoryDestroyProduct'])->middleware('tenant.api.permission:products,edit');

    // Server-Driven UI Declarative Form Submissions
    Route::match(['post', 'put'], '/tenant/settings/{section}', [SduiViewController::class, 'submitSettings'])->middleware('tenant.api.permission:settings,edit');
    Route::match(['post', 'put'], '/app/settings/{section}', [SduiViewController::class, 'submitSettings'])->middleware('tenant.api.permission:settings,edit');

    // Post-Checkout Invoicing & Sales History Actions
    Route::post('/tenant/sales/{id}/send-invoice', [SaleApiController::class, 'sendInvoice']);
    Route::post('/app/sales/{id}/send-invoice', [SaleApiController::class, 'sendInvoice']);
    Route::post('/tenant/sales/{id}/print', [SaleApiController::class, 'printInvoice']);
    Route::post('/app/sales/{id}/print', [SaleApiController::class, 'printInvoice']);

    // Raw invoice PDF bytes for the native Post-Sale Action Sheet's
    // "Preview & Print" row — token-authenticated (bearer), returns
    // application/pdf, never HTML, so it renders straight into the device's
    // native PDF viewer with no web session / /login bounce.
    Route::get('/tenant/invoices/{sale}/pdf-stream', [\App\Http\Controllers\Tenant\InvoiceController::class, 'pdfStream'])
        ->middleware('tenant.api.permission:sales,view')
        ->name('invoice.pdf.stream');
    Route::get('/app/invoices/{sale}/pdf-stream', [\App\Http\Controllers\Tenant\InvoiceController::class, 'pdfStream'])
        ->middleware('tenant.api.permission:sales,view');

    // Terminal Devices & Active Session Management
    Route::get('/tenant/devices', [DeviceApiController::class, 'index']);
    Route::post('/tenant/devices/{token}/revoke', [DeviceApiController::class, 'revoke']);
    Route::get('/devices', [DeviceApiController::class, 'index']);
    Route::post('/devices/{token}/revoke', [DeviceApiController::class, 'revoke']);

    // Centralized Products & Inventory Categories (Mobile SDUI & API)
    Route::prefix('tenant/categories')->group(function () {
        Route::get('/', [CatalogAdminApiController::class, 'categoriesIndex'])->middleware('tenant.api.permission:categories,view');
        Route::post('/', [CatalogAdminApiController::class, 'categoriesStore'])->middleware('tenant.api.permission:categories,create');
        Route::match(['put', 'patch', 'post'], '/{id}', [CatalogAdminApiController::class, 'categoriesUpdate'])->middleware('tenant.api.permission:categories,edit');
        Route::delete('/{id}', [CatalogAdminApiController::class, 'categoriesDestroy'])->middleware('tenant.api.permission:categories,edit');
    });
    Route::prefix('app/categories')->group(function () {
        Route::get('/', [CatalogAdminApiController::class, 'categoriesIndex'])->middleware('tenant.api.permission:categories,view');
        Route::post('/', [CatalogAdminApiController::class, 'categoriesStore'])->middleware('tenant.api.permission:categories,create');
        Route::match(['put', 'patch', 'post'], '/{id}', [CatalogAdminApiController::class, 'categoriesUpdate'])->middleware('tenant.api.permission:categories,edit');
        Route::delete('/{id}', [CatalogAdminApiController::class, 'categoriesDestroy'])->middleware('tenant.api.permission:categories,edit');
    });

    // Centralized Customers & CRM (Mobile SDUI & API)
    Route::prefix('tenant/customers')->group(function () {
        Route::get('/', [PosSyncApiController::class, 'customersIndex'])->middleware('tenant.api.permission:customers,view');
        Route::get('/search', [PosSyncApiController::class, 'customersSearch'])->middleware('tenant.api.permission:customers,view');
        Route::post('/', [PosSyncApiController::class, 'customersStore'])->middleware('tenant.api.permission:customers,create');
        Route::get('/{id}/ledger', [PosSyncApiController::class, 'customerLedger'])->middleware('tenant.api.permission:customers,view');
        Route::post('/{id}/payment', [PosSyncApiController::class, 'customerRecordPayment'])->middleware('tenant.api.permission:finance,edit');
    });
    Route::prefix('app/customers')->group(function () {
        Route::get('/', [PosSyncApiController::class, 'customersIndex'])->middleware('tenant.api.permission:customers,view');
        Route::get('/search', [PosSyncApiController::class, 'customersSearch'])->middleware('tenant.api.permission:customers,view');
        Route::post('/', [PosSyncApiController::class, 'customersStore'])->middleware('tenant.api.permission:customers,create');
        Route::get('/{id}/ledger', [PosSyncApiController::class, 'customerLedger'])->middleware('tenant.api.permission:customers,view');
        Route::post('/{id}/payment', [PosSyncApiController::class, 'customerRecordPayment'])->middleware('tenant.api.permission:finance,edit');
    });
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
    Route::post('/auth/register', [AuthApiController::class, 'register']);
    Route::post('/register', [AuthApiController::class, 'register']);
    Route::post('/auth/verify-email-otp', [AuthApiController::class, 'verifyEmailOtp']);
    Route::post('/auth/verify-otp', [AuthApiController::class, 'verifyEmailOtp']);
    Route::post('/app/verify-otp', [AuthApiController::class, 'verifyEmailOtp']);
    Route::post('/auth/resend-otp', [AuthApiController::class, 'resendOtp']);
    Route::post('/app/resend-otp', [AuthApiController::class, 'resendOtp']);
    Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect']);
    Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback']);
    Route::post('/auth/{provider}/mobile-token', [SocialAuthController::class, 'mobileToken']);
    Route::get('/auth/registration-config', [PosSyncApiController::class, 'registrationConfig']);
    Route::get('/auth/registration-meta', [PosSyncApiController::class, 'registrationMeta']);
    Route::get('/app/registration-meta', [PosSyncApiController::class, 'registrationMeta']);
    Route::get('/auth/branding', [PosSyncApiController::class, 'branding']);
    Route::get('/auth/auth-config', [PosSyncApiController::class, 'authConfig']);
    Route::get('/auth-config', [PosSyncApiController::class, 'authConfig']);
    Route::get('/app/auth-config', [PosSyncApiController::class, 'authConfig']);
    Route::get('/auth/push-config', [PushDeviceApiController::class, 'config']);

    // Protected POS Endpoints (Require API Key or Bearer Token)
    Route::middleware([AuthenticateTenantApi::class])->group(function () {
        Route::get('/auth/session', [PosSyncApiController::class, 'session']);
        Route::post('/auth/desktop-session', [PosSyncApiController::class, 'desktopWebSession']);
        Route::get('/status', [PosSyncApiController::class, 'status']);
        Route::post('/push-devices', [PushDeviceApiController::class, 'store']);
        Route::delete('/push-devices', [PushDeviceApiController::class, 'destroy']);
        Route::get('/sync-catalog', [PosSyncApiController::class, 'syncPull'])->middleware('tenant.api.permission:products,view');
        Route::get('/sync-pull', [PosSyncApiController::class, 'syncPull'])->middleware('tenant.api.permission:products,view');
        Route::post('/sync-sales', [PosSyncApiController::class, 'syncPush'])->middleware('tenant.api.permission:pos,create');
        Route::post('/sync-push', [PosSyncApiController::class, 'syncPush'])->middleware('tenant.api.permission:pos,create');
        Route::post('/sync-batch', [PosSyncApiController::class, 'syncBatch'])->middleware('tenant.api.permission:pos,create');
        Route::get('/pos/checkout-sheet', [SaleApiController::class, 'checkoutSheet'])->middleware('tenant.api.permission:pos,view');
        Route::get('/pos/cart-sheet', [SaleApiController::class, 'checkoutSheet'])->middleware('tenant.api.permission:pos,view');
        Route::post('/pos/checkout', [SaleApiController::class, 'checkout'])->middleware('tenant.api.permission:pos,create');
        Route::post('/pos/hold-order', [SaleApiController::class, 'holdOrder'])->middleware('tenant.api.permission:pos,create');
        Route::get('/checkout-sheet', [SaleApiController::class, 'checkoutSheet'])->middleware('tenant.api.permission:pos,view');
        Route::post('/checkout', [SaleApiController::class, 'checkout'])->middleware('tenant.api.permission:pos,create');

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
        Route::delete('/inventory/product/{id}', [PosSyncApiController::class, 'inventoryDestroyProduct'])->middleware('tenant.api.permission:products,edit');
        Route::delete('/inventory/products/{id}', [PosSyncApiController::class, 'inventoryDestroyProduct'])->middleware('tenant.api.permission:products,edit');
        Route::delete('/products/{id}', [PosSyncApiController::class, 'inventoryDestroyProduct'])->middleware('tenant.api.permission:products,edit');

        // AI Product Image Generation (mobile parity for the web "Generate with AI" button)
        Route::get('/ai-image/availability', [AiImageApiController::class, 'availability'])->middleware('tenant.api.permission:products,view');
        Route::post('/ai-image/generate', [AiImageApiController::class, 'generate'])->middleware('tenant.api.permission:products,edit');

        // Customer Ledger & Khata
        Route::get('/customers', [PosSyncApiController::class, 'customersIndex'])->middleware('tenant.api.permission:customers,view');
        Route::get('/customers/search', [PosSyncApiController::class, 'customersSearch'])->middleware('tenant.api.permission:customers,view');
        Route::post('/customers', [PosSyncApiController::class, 'customersStore'])->middleware('tenant.api.permission:customers,create');
        Route::get('/customers/{id}/ledger', [PosSyncApiController::class, 'customerLedger'])->middleware('tenant.api.permission:customers,view');
        Route::post('/customers/{id}/payment', [PosSyncApiController::class, 'customerRecordPayment'])->middleware('tenant.api.permission:finance,edit');

        // Due Payments / Receivables dashboard panel
        Route::get('/receivables/due', [PosSyncApiController::class, 'dueReceivables'])->middleware('tenant.api.permission:customers,view');
        Route::post('/receivables/{sale}/remind', [PosSyncApiController::class, 'remindReceivable'])->middleware('tenant.api.permission:finance,edit');
        Route::put('/receivables/{sale}/reminder', [PosSyncApiController::class, 'scheduleReceivableReminder'])->middleware('tenant.api.permission:finance,edit');

        // Analytics & Reports
        Route::get('/analytics', [PosSyncApiController::class, 'analytics'])->middleware('tenant.api.permission:reports,view');

        // Outbound Delivery (WhatsApp / Email)
        Route::post('/send-delivery', [PosSyncApiController::class, 'sendDelivery'])->middleware('tenant.api.permission:pos,create');

        // Invoice PDF (Preview / Print / Share)
        Route::get('/sales/{id}', [PosSyncApiController::class, 'saleDetails'])->middleware('tenant.api.permission:sales,view');
        Route::get('/sales/{id}/pdf', [PosSyncApiController::class, 'salePdf'])->middleware('tenant.api.permission:sales,view');

        // Taxes & Tax Rules Management
        Route::get('/taxes', [PosSyncApiController::class, 'taxRulesIndex']);
        Route::post('/taxes', [PosSyncApiController::class, 'taxRulesStore'])->middleware('tenant.api.permission:settings,view');
        Route::put('/taxes/{id}', [PosSyncApiController::class, 'taxRulesUpdate'])->middleware('tenant.api.permission:settings,edit');
        Route::delete('/taxes/{id}', [PosSyncApiController::class, 'taxRulesDestroy'])->middleware('tenant.api.permission:settings,edit');
        Route::post('/taxes/{id}/set-default', [PosSyncApiController::class, 'taxRulesSetDefault'])->middleware('tenant.api.permission:settings,edit');
        Route::get('/settings/navigation-labels', [SettingsApiController::class, 'getNavigationLabels'])->middleware('tenant.api.permission:settings,view');
        Route::post('/settings/navigation-labels', [SettingsApiController::class, 'updateNavigationLabels'])->middleware('tenant.api.permission:settings,edit');
        Route::get('/navigation/drawer', [SettingsApiController::class, 'getDrawerNavigation']);
        Route::get('/navigation', [SettingsApiController::class, 'getDrawerNavigation']);
        Route::get('/settings/form-labels', [SettingsApiController::class, 'getFormLabels'])->middleware('tenant.api.permission:settings,view');
        Route::post('/settings/form-labels', [SettingsApiController::class, 'updateFormLabels'])->middleware('tenant.api.permission:settings,edit');

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
        Route::get('/quotations/defaults', [QuotationApiController::class, 'defaults'])->middleware('tenant.api.permission:quotes,view');
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

        // Settings: Profile / Receipts / Financial / Payment Methods
        Route::get('/settings', [SettingsApiController::class, 'index'])->middleware('tenant.api.permission:settings,view');
        Route::put('/settings/profile', [SettingsApiController::class, 'updateProfile'])->middleware('tenant.api.permission:settings,edit');
        Route::put('/settings/branding', [SettingsApiController::class, 'updateBranding'])->middleware('tenant.api.permission:settings,edit');
        Route::post('/settings/profile/logo', [SettingsApiController::class, 'uploadLogo'])->middleware('tenant.api.permission:settings,edit');
        Route::delete('/settings/profile/logo', [SettingsApiController::class, 'removeLogo'])->middleware('tenant.api.permission:settings,edit');
        Route::post('/settings/profile/favicon', [SettingsApiController::class, 'uploadFavicon'])->middleware('tenant.api.permission:settings,edit');
        Route::delete('/settings/profile/favicon', [SettingsApiController::class, 'removeFavicon'])->middleware('tenant.api.permission:settings,edit');
        Route::post('/settings/profile/drawer-cover', [SettingsApiController::class, 'uploadDrawerCover'])->middleware('tenant.api.permission:settings,edit');
        Route::delete('/settings/profile/drawer-cover', [SettingsApiController::class, 'removeDrawerCover'])->middleware('tenant.api.permission:settings,edit');
        Route::put('/settings/receipts', [SettingsApiController::class, 'updateReceipts'])->middleware('tenant.api.permission:settings,edit');
        Route::match(['post', 'put'], '/settings/repair-checklist', [SettingsApiController::class, 'updateRepairChecklist'])->middleware('tenant.api.permission:settings,edit');
        Route::put('/settings/financial', [SettingsApiController::class, 'updateFinancial'])->middleware('tenant.api.permission:settings,edit');
        Route::get('/settings/payment-methods', [SettingsApiController::class, 'paymentMethodsIndex'])->middleware('tenant.api.permission:settings,view');
        Route::post('/settings/payment-methods', [SettingsApiController::class, 'paymentMethodsStore'])->middleware('tenant.api.permission:settings,edit');
        Route::put('/settings/payment-methods/{id}', [SettingsApiController::class, 'paymentMethodsUpdate'])->middleware('tenant.api.permission:settings,edit');
        Route::delete('/settings/payment-methods/{id}', [SettingsApiController::class, 'paymentMethodsDestroy'])->middleware('tenant.api.permission:settings,edit');
        Route::post('/settings/payment-methods/{id}/toggle', [SettingsApiController::class, 'paymentMethodsToggle'])->middleware('tenant.api.permission:settings,edit');
        Route::get('/settings/payment-methods/{id}/transactions', [SettingsApiController::class, 'paymentMethodTransactions'])->middleware('tenant.api.permission:settings,view');
        Route::get('/settings/payment-methods/{id}/transactions/export', [SettingsApiController::class, 'paymentMethodTransactionsExport'])->middleware('tenant.api.permission:settings,view');
        Route::delete('/demo-data', [TenantDemoDataController::class, 'destroy'])->middleware('tenant.api.permission:settings,edit');

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
        Route::get('/service-orders/parts', [ServiceOrderApiController::class, 'partsIndex'])->middleware('tenant.api.permission:service_orders,view');
        Route::post('/service-orders', [ServiceOrderApiController::class, 'store'])->middleware('tenant.api.permission:service_orders,create');
        Route::get('/service-orders/{id}', [ServiceOrderApiController::class, 'show'])->middleware('tenant.api.permission:service_orders,view');
        Route::put('/service-orders/{id}', [ServiceOrderApiController::class, 'update'])->middleware('tenant.api.permission:service_orders,edit');
        Route::post('/service-orders/{id}/status', [ServiceOrderApiController::class, 'updateStatus'])->middleware('tenant.api.permission:service_orders,edit');
        Route::delete('/service-orders/{id}', [ServiceOrderApiController::class, 'destroy'])->middleware('tenant.api.permission:service_orders,edit');

        // Restaurant Mode: floors/tables, send-to-kitchen, KOT, settle bill
        Route::get('/restaurant/floors', [RestaurantApiController::class, 'floorsIndex'])->middleware('tenant.api.permission:pos,view');
        Route::post('/restaurant/floors', [RestaurantApiController::class, 'floorsStore'])->middleware('tenant.api.permission:pos,edit');
        Route::put('/restaurant/floors/{id}', [RestaurantApiController::class, 'floorsUpdate'])->middleware('tenant.api.permission:pos,edit');
        Route::delete('/restaurant/floors/{id}', [RestaurantApiController::class, 'floorsDestroy'])->middleware('tenant.api.permission:pos,edit');

        Route::post('/restaurant/tables', [RestaurantApiController::class, 'tablesStore'])->middleware('tenant.api.permission:pos,edit');
        Route::get('/restaurant/tables/{id}', [RestaurantApiController::class, 'tableShow'])->middleware('tenant.api.permission:pos,view');
        Route::put('/restaurant/tables/{id}', [RestaurantApiController::class, 'tablesUpdate'])->middleware('tenant.api.permission:pos,edit');
        Route::post('/restaurant/tables/{id}/status', [RestaurantApiController::class, 'tablesSetStatus'])->middleware('tenant.api.permission:pos,edit');
        Route::delete('/restaurant/tables/{id}', [RestaurantApiController::class, 'tablesDestroy'])->middleware('tenant.api.permission:pos,edit');

        Route::post('/restaurant/orders/send-to-kitchen', [RestaurantApiController::class, 'sendToKitchen'])->middleware('tenant.api.permission:pos,create');
        Route::post('/restaurant/orders/{saleId}/settle', [RestaurantApiController::class, 'settle'])->middleware('tenant.api.permission:pos,create');

        Route::get('/restaurant/kot', [RestaurantApiController::class, 'kotIndex'])->middleware('tenant.api.permission:pos,view');
        Route::post('/restaurant/kot/{id}/status', [RestaurantApiController::class, 'kotUpdateStatus'])->middleware('tenant.api.permission:pos,edit');
        Route::post('/restaurant/kot/{id}/dismiss-alarm', [RestaurantApiController::class, 'kotDismissAlarm'])->middleware('tenant.api.permission:pos,edit');

        // Pharmacy POS Module Aliases
        Route::prefix('pharmacy')->group(function () {
            Route::get('/search', [PharmacyApiController::class, 'search'])->middleware('tenant.api.permission:products,view');
            Route::post('/checkout', [PharmacyApiController::class, 'checkout'])->middleware('tenant.api.permission:pos,create');
            Route::get('/batch-sheet', [PharmacyApiController::class, 'batchSheet'])->middleware('tenant.api.permission:products,view');
            Route::get('/checkout-sheet', [PharmacyApiController::class, 'checkoutSheet'])->middleware('tenant.api.permission:pos,view');
            Route::get('/batches', [PharmacyApiController::class, 'batchesIndex'])->middleware('tenant.api.permission:products,view');
            Route::get('/batches/{id}/barcode', [PharmacyApiController::class, 'batchBarcode'])->middleware('tenant.api.permission:products,view');
            Route::post('/batches', [PharmacyApiController::class, 'batchesStore'])->middleware('tenant.api.permission:products,create');
            Route::post('/batches/adjust', [PharmacyApiController::class, 'batchesAdjust'])->middleware('tenant.api.permission:products,edit');
            Route::post('/batches/{id}/adjust', [PharmacyApiController::class, 'batchesAdjust'])->middleware('tenant.api.permission:products,edit');
            Route::post('/batches/return', [PharmacyApiController::class, 'batchesReturn'])->middleware('tenant.api.permission:products,edit');
            Route::post('/batches/{id}/return', [PharmacyApiController::class, 'batchesReturn'])->middleware('tenant.api.permission:products,edit');
            Route::get('/prescriptions', [PharmacyApiController::class, 'prescriptionsIndex'])->middleware('tenant.api.permission:sales,view');
            Route::post('/prescriptions', [PharmacyApiController::class, 'prescriptionsStore'])->middleware('tenant.api.permission:sales,create');
            Route::get('/prescriptions/{id}/checkout-sheet', [PharmacyApiController::class, 'prescriptionCheckoutSheet'])->middleware('tenant.api.permission:pos,view');
            Route::post('/prescriptions/{id}/checkout', [PharmacyApiController::class, 'prescriptionCheckout'])->middleware('tenant.api.permission:pos,create');
            Route::post('/prescriptions/{id}/dispense', [PharmacyApiController::class, 'prescriptionsDispense'])->middleware('tenant.api.permission:pos,edit');
        });

        // Repair & Technician POS Module Aliases
        Route::prefix('repair')->group(function () {
            Route::get('/stats', [RepairApiController::class, 'stats'])->middleware('tenant.api.permission:repair,view');
            Route::get('/categories', [RepairApiController::class, 'categoriesIndex'])->middleware('tenant.api.permission:repair,view');
            Route::post('/categories', [RepairApiController::class, 'categoriesStore'])->middleware('tenant.api.permission:repair,create');
            Route::match(['put', 'patch', 'post'], '/categories/{id}', [RepairApiController::class, 'categoriesUpdate'])->middleware('tenant.api.permission:repair,diagnose');
            Route::delete('/categories/{id}', [RepairApiController::class, 'categoriesDestroy'])->middleware('tenant.api.permission:repair,delete');
            Route::get('/tickets', [RepairApiController::class, 'ticketsIndex'])->middleware('tenant.api.permission:repair,view');
            Route::post('/tickets', [RepairApiController::class, 'ticketsStore'])->middleware('tenant.api.permission:repair,create');
            Route::get('/tickets/{id}', [RepairApiController::class, 'ticketsShow'])->middleware('tenant.api.permission:repair,view');
            Route::delete('/tickets/{id}', [RepairApiController::class, 'ticketsDestroy'])->middleware('tenant.api.permission:repair,delete');
            Route::post('/tickets/{id}/status', [RepairApiController::class, 'ticketsUpdateStatus'])->middleware('tenant.api.permission:repair,diagnose');
            Route::match(['get', 'post'], '/tickets/{id}/share', [RepairApiController::class, 'ticketsShareSheet'])->middleware('tenant.api.permission:repair,view');
            Route::post('/tickets/{id}/assign', [RepairApiController::class, 'ticketsAssign'])->middleware('tenant.api.permission:repair,assign');
            Route::post('/tickets/{id}/parts', [RepairApiController::class, 'ticketsAddPart'])->middleware('tenant.api.permission:repair,diagnose');
            Route::delete('/tickets/{ticketId}/parts/{partId}', [RepairApiController::class, 'ticketsRemovePart'])->middleware('tenant.api.permission:repair,diagnose');
            Route::post('/tickets/{id}/labor', [RepairApiController::class, 'ticketsSetLabor'])->middleware('tenant.api.permission:repair,diagnose');
            Route::post('/tickets/{id}/checklist', [RepairApiController::class, 'ticketsUpdateChecklist'])->middleware('tenant.api.permission:repair,diagnose');
            Route::post('/tickets/{id}/settle', [RepairApiController::class, 'ticketsSettle'])->middleware('tenant.api.permission:repair,checkout');
            Route::get('/tickets/{id}/checkout-sheet', [RepairApiController::class, 'ticketCheckoutSheet'])->middleware('tenant.api.permission:repair,view');
            Route::get('/tickets/{id}/intake-sheet', [RepairApiController::class, 'ticketIntakeSheet'])->middleware('tenant.api.permission:repair,view');
            Route::get('/checkout-sheet', [RepairApiController::class, 'checkoutSheet'])->middleware('tenant.api.permission:repair,view');
            Route::post('/pos-checkout', [RepairApiController::class, 'posCheckout'])->middleware('tenant.api.permission:repair,checkout');
            Route::post('/checkout', [RepairApiController::class, 'posCheckout'])->middleware('tenant.api.permission:repair,checkout');
        });

        // Salon & Service POS Module Aliases
        Route::prefix('salon')->group(function () {
            Route::get('/specialist-sheet', [SalonApiController::class, 'specialistSheet'])->middleware('tenant.api.permission:service_orders,view');
            Route::get('/checkout-sheet', [SalonApiController::class, 'checkoutSheet'])->middleware('tenant.api.permission:pos,view');
            Route::post('/pos-checkout', [SalonApiController::class, 'posCheckout'])->middleware('tenant.api.permission:pos,create');
            Route::post('/checkout', [SalonApiController::class, 'posCheckout'])->middleware('tenant.api.permission:pos,create');
            Route::get('/appointments', [SalonApiController::class, 'appointmentsIndex'])->middleware('tenant.api.permission:service_orders,view');
            Route::post('/appointments', [SalonApiController::class, 'appointmentsStore'])->middleware('tenant.api.permission:service_orders,create');
            Route::get('/appointments/availability', [SalonApiController::class, 'appointmentsAvailability'])->middleware('tenant.api.permission:service_orders,view');
            Route::post('/appointments/{id}/status', [SalonApiController::class, 'appointmentsUpdateStatus'])->middleware('tenant.api.permission:service_orders,edit');
            Route::get('/specialists', [SalonApiController::class, 'specialistsIndex'])->middleware('tenant.api.permission:users,view');
            Route::post('/specialists/{id}/toggle', [SalonApiController::class, 'specialistsToggle'])->middleware('tenant.api.permission:users,edit');
            Route::get('/services', [SalonApiController::class, 'servicesIndex'])->middleware('tenant.api.permission:service_orders,view');
            Route::post('/services', [SalonApiController::class, 'servicesStore'])->middleware('tenant.api.permission:service_orders,create');
            Route::get('/services/{id}/edit-sheet', [SalonApiController::class, 'servicesEditSheet'])->middleware('tenant.api.permission:service_orders,view');
            Route::match(['post', 'put'], '/services/{id}', [SalonApiController::class, 'servicesUpdate'])->middleware('tenant.api.permission:service_orders,edit');
            Route::delete('/services/{id}', [SalonApiController::class, 'servicesDestroy'])->middleware('tenant.api.permission:service_orders,edit');
            Route::post('/services/{id}/delete', [SalonApiController::class, 'servicesDestroy'])->middleware('tenant.api.permission:service_orders,edit');
        });

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

        // Custom Roles & Granular Permissions
        Route::get('/roles', [RoleApiController::class, 'index'])->middleware('tenant.api.permission:users,view');
        Route::post('/roles', [RoleApiController::class, 'store'])->middleware('tenant.api.permission:users,create');
        Route::match(['put', 'patch'], '/roles/{id}', [RoleApiController::class, 'update'])->middleware('tenant.api.permission:users,edit');
        Route::delete('/roles/{id}', [RoleApiController::class, 'destroy'])->middleware('tenant.api.permission:users,edit');

        // Online Catalog
        Route::get('/catalog', [CatalogApiController::class, 'index'])->middleware('tenant.api.permission:catalog,view');
        Route::post('/catalog', [CatalogApiController::class, 'store'])->middleware('tenant.api.permission:catalog,create');
        Route::delete('/catalog/{id}', [CatalogApiController::class, 'destroy'])->middleware('tenant.api.permission:catalog,edit');

        // Devices & POS Terminals
        Route::get('/devices', [DeviceApiController::class, 'index']);
        Route::post('/devices/{token}/revoke', [DeviceApiController::class, 'revoke']);
        Route::get('/device-sessions', [DeviceApiController::class, 'index']);
        Route::post('/device-sessions/{token}/revoke', [DeviceApiController::class, 'revoke']);

        // Sales Invoice Actions
        Route::post('/sales/{id}/send-invoice', [SaleApiController::class, 'sendInvoice']);
        Route::post('/sales/{id}/print', [SaleApiController::class, 'printInvoice']);

        // Languages (store default language only — see LanguageApiController)
        Route::get('/languages', [LanguageApiController::class, 'index'])->middleware('tenant.api.permission:settings,view');
        Route::put('/languages/default', [LanguageApiController::class, 'setDefault'])->middleware('tenant.api.permission:settings,edit');
        Route::get('/languages/translations/{locale}', [LanguageApiController::class, 'translations']);

        // App bootstrap (translations + nav customization + config + SDUI modules in one call)
        Route::get('/app/bootstrap', [AppBootstrapController::class, 'bootstrap']);
        Route::post('/app/mode', [AppBootstrapController::class, 'switchMode'])->middleware('tenant.api.permission:settings,edit');
        // Settings: Nav & Custom Notifications
        Route::post('/settings/nav-config', [AppBootstrapController::class, 'updateNav'])->middleware('tenant.api.permission:settings,edit');
        Route::prefix('settings/custom-notifications')->group(function () {
            Route::get('/', [SettingsApiController::class, 'notificationChannelsIndex']);
            Route::post('/', [SettingsApiController::class, 'notificationChannelsStore']);
            Route::put('/{id}', [SettingsApiController::class, 'notificationChannelsUpdate']);
            Route::delete('/{id}', [SettingsApiController::class, 'notificationChannelsDestroy']);
            Route::post('/test', [SettingsApiController::class, 'testNotificationChannel']);
        });
        Route::get('/views/{view}', [SduiViewController::class, 'show']);
    });
});
