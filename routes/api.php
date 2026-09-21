<?php

use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DispatchController;
use App\Http\Controllers\Api\DocumentActionController;
use App\Http\Controllers\Api\DocumentDispatchController;
use App\Http\Controllers\Api\DocumentPreviewController;
use App\Http\Controllers\Api\InvoiceController as ApiInvoiceController;
use App\Http\Controllers\Api\InvoicePreviewController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\LicenseActivationController;
use App\Http\Controllers\Api\NavigationController;
use App\Http\Controllers\Api\NavigationMenuController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\QuotationController;
use App\Http\Controllers\Api\ReceivablesController;
use App\Http\Controllers\Api\RolePermissionController;
use App\Http\Controllers\Api\TenantSettingsController;
use App\Http\Controllers\Api\UnifiedDispatchController;
use App\Http\Controllers\SuperAdmin\TenantController as SuperAdminTenantController;
use App\Http\Controllers\Api\Tenant\CouponApiController;
use App\Http\Controllers\Api\Tenant\FaqApiController;
use App\Http\Controllers\Api\Tenant\StorefrontSettingsController;
use App\Http\Controllers\Api\V1\AiImageApiController;
use App\Http\Controllers\Api\V1\ApiIntegrationsController;
use App\Http\Controllers\Api\V1\AppBootstrapController;
use App\Http\Controllers\Api\V1\AuthApiController;
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
use App\Http\Controllers\Api\V1\PosDesktopSyncController;
use App\Http\Controllers\Api\V1\PosSyncApiController;
use App\Http\Controllers\Api\V1\PushDeviceApiController;
use App\Http\Controllers\Api\V1\QuotationApiController;
use App\Http\Controllers\Api\V1\RepairApiController;
use App\Http\Controllers\Api\V1\ReportsApiController;
use App\Http\Controllers\Api\V1\RestaurantApiController;
use App\Http\Controllers\Api\V1\RoleApiController;
use App\Http\Controllers\Api\V1\SaleApiController;
use App\Http\Controllers\Api\V1\SalesTargetApiController;
use App\Http\Controllers\Api\V1\SalonApiController;
use App\Http\Controllers\Api\V1\SduiViewController;
use App\Http\Controllers\Api\V1\ServiceOrderApiController;
use App\Http\Controllers\Api\V1\SettingsApiController;
use App\Http\Controllers\Api\V1\TaxApiController;
use App\Http\Controllers\Api\V1\TenantAppPreferencesController;
use App\Http\Controllers\Api\V1\TenantDemoDataController;
use App\Http\Controllers\Api\V1\UploadApiController;
use App\Http\Controllers\Api\V1\UserApiController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\StoreProfileController;
use App\Http\Controllers\Tenant\Auth\PasswordResetController;
use App\Http\Controllers\Tenant\InvoiceController;
use App\Http\Controllers\Tenant\StoreInquiryController;
use App\Http\Controllers\Webhooks\SubscriptionWebhookController;
use App\Http\Middleware\AuthenticateTenantApi;
use App\Http\Middleware\PreventDemoModifications;
use App\Services\Auth\PermissionChecker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\leadmanagement\Http\Controllers\LeadModuleController;

/*
|--------------------------------------------------------------------------
| RESTful E-Invoicing & Fiscal Tax Engine API Routes
|--------------------------------------------------------------------------
| Versioned API endpoints (/api/v1/tax/*) secured with Tenant API Keys
| and bearer tokens for third-party ERPs, Shopify, WooCommerce, etc.
*/

// Signed "license issued" push from the vendor's License Manager after a
// hosted-checkout purchase. HMAC-verified in the controller (no auth middleware).
Route::post('/license/activate', [LicenseActivationController::class, 'activate']);
Route::get('/public/landing', [\App\Http\Controllers\Api\V1\LandingApiController::class, 'show']);
Route::get('/public/landing-config', [\App\Http\Controllers\Api\V1\LandingApiController::class, 'show']);
Route::get('/v1/public/landing-config', [\App\Http\Controllers\Api\V1\LandingApiController::class, 'show']);
Route::get('/public/check-subdomain', [AuthApiController::class, 'checkSubdomain']);
Route::get('/v1/public/check-subdomain', [AuthApiController::class, 'checkSubdomain']);
Route::get('/auth/check-subdomain', [AuthApiController::class, 'checkSubdomain']);
Route::get('/v1/auth/check-subdomain', [AuthApiController::class, 'checkSubdomain']);
Route::get('/subscription/plans', [\App\Http\Controllers\Api\V1\LandingApiController::class, 'plans']);
Route::get('/v1/subscription/plans', [\App\Http\Controllers\Api\V1\LandingApiController::class, 'plans']);
Route::post('/public/contact-us', [\App\Http\Controllers\Api\V1\LandingApiController::class, 'submitContact']);
Route::post('/v1/public/contact-us', [\App\Http\Controllers\Api\V1\LandingApiController::class, 'submitContact']);
Route::post('/public/contact', [\App\Http\Controllers\Api\V1\LandingApiController::class, 'submitContact']);
Route::post('/v1/public/contact', [\App\Http\Controllers\Api\V1\LandingApiController::class, 'submitContact']);
Route::get('/pricing-plans', [\App\Http\Controllers\Api\V1\LandingApiController::class, 'plans']);
Route::get('/v1/pricing-plans', [\App\Http\Controllers\Api\V1\LandingApiController::class, 'plans']);
Route::get('/pricing-plans/{id}', [\App\Http\Controllers\Api\V1\LandingApiController::class, 'planDetail']);
Route::get('/v1/pricing-plans/{id}', [\App\Http\Controllers\Api\V1\LandingApiController::class, 'planDetail']);

Route::get('/storefront/catalog', [\App\Http\Controllers\Tenant\StorefrontController::class, 'apiCatalog']);
Route::get('/v1/storefront/catalog', [\App\Http\Controllers\Tenant\StorefrontController::class, 'apiCatalog']);
Route::post('/storefront/order', [\App\Http\Controllers\Tenant\StorefrontController::class, 'placeOrder']);
Route::post('/v1/storefront/order', [\App\Http\Controllers\Tenant\StorefrontController::class, 'placeOrder']);
Route::get('/storefront/payment-methods', [\App\Http\Controllers\Tenant\StorefrontController::class, 'paymentMethods']);
Route::get('/v1/storefront/payment-methods', [\App\Http\Controllers\Tenant\StorefrontController::class, 'paymentMethods']);
Route::post('/storefront/coupons/validate', [\App\Http\Controllers\Tenant\StorefrontController::class, 'validateCoupon']);
Route::post('/v1/storefront/coupons/validate', [\App\Http\Controllers\Tenant\StorefrontController::class, 'validateCoupon']);
Route::get('/storefront/faqs', [\App\Http\Controllers\Tenant\StorefrontController::class, 'apiFaqs']);
Route::get('/v1/storefront/faqs', [\App\Http\Controllers\Tenant\StorefrontController::class, 'apiFaqs']);

Route::post('/storefront/payment/initiate', [\App\Http\Controllers\Tenant\StorefrontController::class, 'initiateGatewayPayment']);
Route::post('/v1/storefront/payment/initiate', [\App\Http\Controllers\Tenant\StorefrontController::class, 'initiateGatewayPayment']);
Route::post('/storefront/payment/verify', [\App\Http\Controllers\Tenant\StorefrontController::class, 'verifyGatewayPayment']);
Route::post('/v1/storefront/payment/verify', [\App\Http\Controllers\Tenant\StorefrontController::class, 'verifyGatewayPayment']);

Route::get('/storefront/products/{id}/reviews', [\App\Http\Controllers\Tenant\StorefrontReviewController::class, 'index']);
Route::get('/v1/storefront/products/{id}/reviews', [\App\Http\Controllers\Tenant\StorefrontReviewController::class, 'index']);
Route::post('/storefront/products/{id}/reviews', [\App\Http\Controllers\Tenant\StorefrontReviewController::class, 'store']);
Route::post('/v1/storefront/products/{id}/reviews', [\App\Http\Controllers\Tenant\StorefrontReviewController::class, 'store']);
Route::get('/storefront/customer/reviews', [\App\Http\Controllers\Tenant\StorefrontReviewController::class, 'customerReviews']);
Route::get('/v1/storefront/customer/reviews', [\App\Http\Controllers\Tenant\StorefrontReviewController::class, 'customerReviews']);

// Public Storefront Inquiries Sink
Route::post('/storefront/inquiry', [StoreInquiryController::class, 'submitPublicInquiry']);
Route::post('/v1/storefront/inquiry', [StoreInquiryController::class, 'submitPublicInquiry']);
Route::post('/api/storefront/inquiry', [StoreInquiryController::class, 'submitPublicInquiry']);

// Public Storefront Dynamic Menus
Route::get('/storefront/menus', [\App\Http\Controllers\Tenant\StorefrontMenuController::class, 'publicMenus']);
Route::get('/v1/storefront/menus', [\App\Http\Controllers\Tenant\StorefrontMenuController::class, 'publicMenus']);
Route::get('/api/v1/storefront/menus', [\App\Http\Controllers\Tenant\StorefrontMenuController::class, 'publicMenus']);

// Storefront Customer Auth, Profile, Wishlist, Addresses, & Authoritative Cart Calculation
$storefrontCustomerRoutes = function () {
    Route::post('/customer/register', [\App\Http\Controllers\Api\V1\StorefrontCustomerApiController::class, 'register']);
    Route::post('/customer/login', [\App\Http\Controllers\Api\V1\StorefrontCustomerApiController::class, 'login']);
    Route::post('/customer/logout', [\App\Http\Controllers\Api\V1\StorefrontCustomerApiController::class, 'logout']);
    Route::get('/customer/profile', [\App\Http\Controllers\Api\V1\StorefrontCustomerApiController::class, 'profile']);
    Route::match(['put', 'post'], '/customer/profile', [\App\Http\Controllers\Api\V1\StorefrontCustomerApiController::class, 'updateProfile']);
    Route::get('/customer/addresses', [\App\Http\Controllers\Api\V1\StorefrontCustomerApiController::class, 'addresses']);
    Route::post('/customer/addresses', [\App\Http\Controllers\Api\V1\StorefrontCustomerApiController::class, 'storeAddress']);
    Route::put('/customer/addresses/{id}', [\App\Http\Controllers\Api\V1\StorefrontCustomerApiController::class, 'updateAddress']);
    Route::delete('/customer/addresses/{id}', [\App\Http\Controllers\Api\V1\StorefrontCustomerApiController::class, 'deleteAddress']);
    Route::get('/customer/wishlist', [\App\Http\Controllers\Api\V1\StorefrontCustomerApiController::class, 'wishlist']);
    Route::post('/customer/wishlist/toggle', [\App\Http\Controllers\Api\V1\StorefrontCustomerApiController::class, 'toggleWishlist']);
    Route::delete('/customer/wishlist/{productId}', [\App\Http\Controllers\Api\V1\StorefrontCustomerApiController::class, 'removeWishlist']);
    Route::get('/customer/orders', [\App\Http\Controllers\Api\V1\StorefrontCustomerApiController::class, 'orders']);
    Route::post('/cart/calculate', [\App\Http\Controllers\Api\V1\StorefrontCustomerApiController::class, 'calculateCart']);
    Route::post('/customer/send-verification', [\App\Http\Controllers\Tenant\StorefrontController::class, 'sendVerification']);
    Route::post('/customer/verify-code', [\App\Http\Controllers\Tenant\StorefrontController::class, 'verifyCode']);
};
Route::prefix('storefront')->group($storefrontCustomerRoutes);
Route::prefix('v1/storefront')->group($storefrontCustomerRoutes);


// Convenient root aliases for storefront customer features
Route::get('/addresses', [\App\Http\Controllers\Api\V1\StorefrontCustomerApiController::class, 'addresses']);
Route::post('/addresses', [\App\Http\Controllers\Api\V1\StorefrontCustomerApiController::class, 'storeAddress']);
Route::put('/addresses/{id}', [\App\Http\Controllers\Api\V1\StorefrontCustomerApiController::class, 'updateAddress']);
Route::delete('/addresses/{id}', [\App\Http\Controllers\Api\V1\StorefrontCustomerApiController::class, 'deleteAddress']);
Route::get('/wishlist', [\App\Http\Controllers\Api\V1\StorefrontCustomerApiController::class, 'wishlist']);
Route::post('/wishlist/toggle', [\App\Http\Controllers\Api\V1\StorefrontCustomerApiController::class, 'toggleWishlist']);
Route::delete('/wishlist/{productId}', [\App\Http\Controllers\Api\V1\StorefrontCustomerApiController::class, 'removeWishlist']);

Route::prefix('v1/tax')->middleware([AuthenticateTenantApi::class, PreventDemoModifications::class])->group(function () {
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
Route::prefix('tenant/password')->middleware([PreventDemoModifications::class])->group(function () {
    Route::post('/email', [PasswordResetController::class, 'sendResetLink']);
    Route::post('/reset', [PasswordResetController::class, 'reset']);
});

Route::post('/tenant/profile/change-password', [PasswordResetController::class, 'changePassword'])
    ->middleware([AuthenticateTenantApi::class, PreventDemoModifications::class]);

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
Route::get('/tenant/receipt/{sale}/pdf', [InvoiceController::class, 'signedPdf'])
    ->middleware('signed')
    ->name('receipt.signed.pdf');

// Inbound Two-Way E-Commerce Webhook Receiver (Shopify / WooCommerce / Generic)
Route::post('/v1/integrations/webhooks/{tenant_uuid}/orders', [EcommerceWebhookController::class, 'handleOrders']);
Route::post('/integrations/webhooks/{tenant_uuid}/orders', [EcommerceWebhookController::class, 'handleOrders']);

// Subscription payment gateway webhooks — the URL for each is shown, copyable,
// on its Super Admin ▸ Payment Gateways card. Signature is checked against the
// gateway's stored webhook secret; the event is written to the audit log.
Route::post('/v1/webhooks/{gateway}', [SubscriptionWebhookController::class, 'handle'])
    ->name('webhooks.gateway');
Route::post('/webhooks/{gateway}', [SubscriptionWebhookController::class, 'handle']);
foreach (SubscriptionWebhookController::GATEWAYS as $gw) {
    Route::post("/v1/webhooks/{$gw}", [SubscriptionWebhookController::class, 'handle'])
        ->defaults('gateway', $gw)
        ->name("webhooks.{$gw}");
    Route::post("/webhooks/{$gw}", [SubscriptionWebhookController::class, 'handle'])
        ->defaults('gateway', $gw);
}

// Server-Driven UI Bootstrap, View Schemas, and Form Action Routes
Route::middleware([AuthenticateTenantApi::class, PreventDemoModifications::class])->group(function () {
    Route::get('/app/bootstrap', [AppBootstrapController::class, 'bootstrap']);
    Route::get('/tenant/bootstrap', [AppBootstrapController::class, 'bootstrap']);
    Route::get('/v1/tenant/bootstrap', [AppBootstrapController::class, 'bootstrap']);
    Route::get('/v1/bootstrap', [AppBootstrapController::class, 'bootstrap']);
    Route::get('/bootstrap', [AppBootstrapController::class, 'bootstrap']);
    Route::get('/app/translations', [LanguageApiController::class, 'appTranslations']);
    Route::post('/app/mode', [AppBootstrapController::class, 'switchMode'])->middleware('tenant.api.permission:settings,edit');

    // Dashboard chrome and its notification feed are server-driven so badge
    // counts and action endpoints can evolve without a client release.
    Route::get('/tenant/views/dashboard', [DashboardController::class, 'show']);
    Route::get('/app/views/dashboard', [DashboardController::class, 'show']);
    Route::get('/v1/tenant/views/dashboard', [DashboardController::class, 'show']);
    Route::get('/tenant/notifications/feed', [NotificationController::class, 'feed']);
    Route::get('/v1/tenant/notifications/feed', [NotificationController::class, 'feed']);
    Route::get('/tenant/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::get('/v1/tenant/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);

    // Notification dismissal & clear-all endpoints
    Route::post('/tenant/notifications/clear-all', [NotificationController::class, 'clearAll']);
    Route::post('/v1/tenant/notifications/clear-all', [NotificationController::class, 'clearAll']);
    Route::post('/notifications/clear-all', [NotificationController::class, 'clearAll']);
    Route::post('/tenant/notifications/dismiss', [NotificationController::class, 'dismiss']);
    Route::post('/v1/tenant/notifications/dismiss', [NotificationController::class, 'dismiss']);
    Route::post('/notifications/dismiss', [NotificationController::class, 'dismiss']);
    Route::post('/tenant/notifications/{type}/{id}/dismiss', [NotificationController::class, 'dismiss']);
    Route::post('/v1/tenant/notifications/{type}/{id}/dismiss', [NotificationController::class, 'dismiss']);
    Route::post('/notifications/{type}/{id}/dismiss', [NotificationController::class, 'dismiss']);
    Route::post('/tenant/notifications/{id}/dismiss', [NotificationController::class, 'dismiss']);
    Route::post('/v1/tenant/notifications/{id}/dismiss', [NotificationController::class, 'dismiss']);
    Route::post('/notifications/{id}/dismiss', [NotificationController::class, 'dismiss']);
    Route::delete('/tenant/notifications/{id}', [NotificationController::class, 'dismiss']);
    Route::delete('/v1/tenant/notifications/{id}', [NotificationController::class, 'dismiss']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'dismiss']);

    // Unified multi-format preview for invoices, sales receipts and quotes.
    Route::get('/tenant/documents/{type}/{id}/preview-modal', [DocumentPreviewController::class, 'previewModal']);
    Route::get('/v1/tenant/documents/{type}/{id}/preview-modal', [DocumentPreviewController::class, 'previewModal']);
    Route::get('/tenant/documents/{type}/{id}/render-html', [DocumentPreviewController::class, 'renderHtml']);
    Route::get('/v1/tenant/documents/{type}/{id}/render-html', [DocumentPreviewController::class, 'renderHtml']);

    // Stable per-channel action endpoints consumed by SDUI channel tiles.
    Route::post('/tenant/dispatch/sms', [DispatchController::class, 'dispatchSms'])->middleware('tenant.api.permission:pos,create');
    Route::post('/v1/tenant/dispatch/sms', [DispatchController::class, 'dispatchSms'])->middleware('tenant.api.permission:pos,create');
    Route::post('/tenant/dispatch/email', [DispatchController::class, 'dispatchEmail'])->middleware('tenant.api.permission:pos,create');
    Route::post('/v1/tenant/dispatch/email', [DispatchController::class, 'dispatchEmail'])->middleware('tenant.api.permission:pos,create');

    // Quotations SDUI View & Pre-population Schemas
    Route::get('/tenant/quotations/create-modal', [QuotationController::class, 'createModal'])->middleware('tenant.api.permission:quotes,view');
    Route::get('/v1/tenant/quotations/create-modal', [QuotationController::class, 'createModal'])->middleware('tenant.api.permission:quotes,view');
    Route::get('/tenant/views/quotations/create-modal', [QuotationController::class, 'createModal'])->middleware('tenant.api.permission:quotes,view');
    Route::get('/v1/tenant/views/quotations/create-modal', [QuotationController::class, 'createModal'])->middleware('tenant.api.permission:quotes,view');
    Route::get('/quotations/create-modal', [QuotationController::class, 'createModal'])->middleware('tenant.api.permission:quotes,view');
    Route::get('/tenant/views/quotations/create', [QuotationController::class, 'createSchema'])->middleware('tenant.api.permission:quotes,view');
    Route::get('/tenant/quotations/create', [QuotationController::class, 'createSchema'])->middleware('tenant.api.permission:quotes,view');
    Route::get('/v1/tenant/views/quotations/create', [QuotationController::class, 'createSchema'])->middleware('tenant.api.permission:quotes,view');
    Route::get('/v1/tenant/quotations/create', [QuotationController::class, 'createSchema'])->middleware('tenant.api.permission:quotes,view');
    Route::get('/v1/pos/quotations/create', [QuotationController::class, 'createSchema'])->middleware('tenant.api.permission:quotes,view');
    Route::get('/quotations/create', [QuotationController::class, 'createSchema'])->middleware('tenant.api.permission:quotes,view');
    // Quotations SDUI Sheet & Dispatch Actions (Placed before wildcard {id} routes)
    Route::get('/tenant/views/quotations/actions-sheet/{id?}', [QuotationController::class, 'actionsSheet'])->middleware('tenant.api.permission:quotes,view');
    Route::get('/v1/tenant/views/quotations/actions-sheet/{id?}', [QuotationController::class, 'actionsSheet'])->middleware('tenant.api.permission:quotes,view');
    Route::get('/tenant/quotations/actions-sheet/{id?}', [QuotationController::class, 'actionsSheet'])->middleware('tenant.api.permission:quotes,view');
    Route::get('/v1/tenant/quotations/actions-sheet/{id?}', [QuotationController::class, 'actionsSheet'])->middleware('tenant.api.permission:quotes,view');
    Route::get('/quotations/actions-sheet/{id?}', [QuotationController::class, 'actionsSheet'])->middleware('tenant.api.permission:quotes,view');
    Route::get('/tenant/views/quotations/{id}/actions-sheet', [QuotationController::class, 'actionsSheet'])->middleware('tenant.api.permission:quotes,view');
    Route::get('/v1/tenant/views/quotations/{id}/actions-sheet', [QuotationController::class, 'actionsSheet'])->middleware('tenant.api.permission:quotes,view');
    Route::get('/tenant/quotations/{id}/actions-sheet', [QuotationController::class, 'actionsSheet'])->middleware('tenant.api.permission:quotes,view');
    Route::get('/v1/tenant/quotations/{id}/actions-sheet', [QuotationController::class, 'actionsSheet'])->middleware('tenant.api.permission:quotes,view');
    Route::get('/quotations/{id}/actions-sheet', [QuotationController::class, 'actionsSheet'])->middleware('tenant.api.permission:quotes,view');

    Route::get('/tenant/views/quotations/preview-sheet/{id?}', [QuotationController::class, 'previewSheet'])->middleware('tenant.api.permission:quotes,view');
    Route::get('/v1/tenant/views/quotations/preview-sheet/{id?}', [QuotationController::class, 'previewSheet'])->middleware('tenant.api.permission:quotes,view');
    Route::get('/tenant/quotations/preview-sheet/{id?}', [QuotationController::class, 'previewSheet'])->middleware('tenant.api.permission:quotes,view');
    Route::get('/v1/tenant/quotations/preview-sheet/{id?}', [QuotationController::class, 'previewSheet'])->middleware('tenant.api.permission:quotes,view');
    Route::get('/quotations/preview-sheet/{id?}', [QuotationController::class, 'previewSheet'])->middleware('tenant.api.permission:quotes,view');
    Route::get('/tenant/views/quotations/{id}/preview-sheet', [QuotationController::class, 'previewSheet'])->middleware('tenant.api.permission:quotes,view');
    Route::get('/v1/tenant/views/quotations/{id}/preview-sheet', [QuotationController::class, 'previewSheet'])->middleware('tenant.api.permission:quotes,view');
    Route::get('/tenant/quotations/{id}/preview-sheet', [QuotationController::class, 'previewSheet'])->middleware('tenant.api.permission:quotes,view');
    Route::get('/v1/tenant/quotations/{id}/preview-sheet', [QuotationController::class, 'previewSheet'])->middleware('tenant.api.permission:quotes,view');
    Route::get('/quotations/{id}/preview-sheet', [QuotationController::class, 'previewSheet'])->middleware('tenant.api.permission:quotes,view');

    Route::get('/tenant/views/quotations/send-sheet/{id?}', [QuotationController::class, 'sendSheet'])->middleware('tenant.api.permission:quotes,view');
    Route::get('/v1/tenant/views/quotations/send-sheet/{id?}', [QuotationController::class, 'sendSheet'])->middleware('tenant.api.permission:quotes,view');
    Route::get('/tenant/quotations/send-sheet/{id?}', [QuotationController::class, 'sendSheet'])->middleware('tenant.api.permission:quotes,view');
    Route::get('/v1/tenant/quotations/send-sheet/{id?}', [QuotationController::class, 'sendSheet'])->middleware('tenant.api.permission:quotes,view');
    Route::get('/quotations/send-sheet/{id?}', [QuotationController::class, 'sendSheet'])->middleware('tenant.api.permission:quotes,view');
    Route::get('/tenant/views/quotations/{id}/send-sheet', [QuotationController::class, 'sendSheet'])->middleware('tenant.api.permission:quotes,view');
    Route::get('/v1/tenant/views/quotations/{id}/send-sheet', [QuotationController::class, 'sendSheet'])->middleware('tenant.api.permission:quotes,view');
    Route::get('/tenant/quotations/{id}/send-sheet', [QuotationController::class, 'sendSheet'])->middleware('tenant.api.permission:quotes,view');
    Route::get('/v1/tenant/quotations/{id}/send-sheet', [QuotationController::class, 'sendSheet'])->middleware('tenant.api.permission:quotes,view');
    Route::get('/quotations/{id}/send-sheet', [QuotationController::class, 'sendSheet'])->middleware('tenant.api.permission:quotes,view');

    // Quotations SDUI Screen Views (via NAVIGATE_TO)
    Route::get('/tenant/views/quotations/{id}/preview', [QuotationController::class, 'previewView'])->middleware('tenant.api.permission:quotes,view');
    Route::get('/v1/tenant/views/quotations/{id}/preview', [QuotationController::class, 'previewView'])->middleware('tenant.api.permission:quotes,view');
    Route::get('/tenant/quotations/{id}/preview', [QuotationController::class, 'previewView'])->middleware('tenant.api.permission:quotes,view');
    Route::get('/v1/tenant/quotations/{id}/preview', [QuotationController::class, 'previewView'])->middleware('tenant.api.permission:quotes,view');
    Route::get('/quotations/{id}/preview', [QuotationController::class, 'previewView'])->middleware('tenant.api.permission:quotes,view');

    Route::get('/tenant/views/quotations/{id}/send', [QuotationController::class, 'sendView'])->middleware('tenant.api.permission:quotes,view');
    Route::get('/v1/tenant/views/quotations/{id}/send', [QuotationController::class, 'sendView'])->middleware('tenant.api.permission:quotes,view');
    Route::get('/tenant/quotations/{id}/send', [QuotationController::class, 'sendView'])->middleware('tenant.api.permission:quotes,view');
    Route::get('/v1/tenant/quotations/{id}/send', [QuotationController::class, 'sendView'])->middleware('tenant.api.permission:quotes,view');
    Route::get('/quotations/{id}/send', [QuotationController::class, 'sendView'])->middleware('tenant.api.permission:quotes,view');

    Route::get('/tenant/quotations/{id}/pdf', [QuotationApiController::class, 'pdf'])->middleware('tenant.api.permission:quotes,view');
    Route::get('/v1/tenant/quotations/{id}/pdf', [QuotationApiController::class, 'pdf'])->middleware('tenant.api.permission:quotes,view');
    Route::get('/tenant/views/quotations/{id}/pdf', [QuotationApiController::class, 'pdf'])->middleware('tenant.api.permission:quotes,view');

    // Quotations Show Schema
    Route::get('/tenant/views/quotations/{id}', [QuotationController::class, 'showSchema'])->whereNumber('id')->middleware('tenant.api.permission:quotes,view');
    Route::get('/v1/tenant/views/quotations/{id}', [QuotationController::class, 'showSchema'])->whereNumber('id')->middleware('tenant.api.permission:quotes,view');
    Route::get('/tenant/quotations/{id}', [QuotationController::class, 'showSchema'])->whereNumber('id')->middleware('tenant.api.permission:quotes,view');
    Route::get('/v1/tenant/quotations/{id}', [QuotationController::class, 'showSchema'])->whereNumber('id')->middleware('tenant.api.permission:quotes,view');
    Route::get('/quotations/{id}', [QuotationController::class, 'showSchema'])->whereNumber('id')->middleware('tenant.api.permission:quotes,view');

    Route::post('/tenant/quotations/{id}/dispatch', [QuotationController::class, 'dispatchQuotation'])->middleware('tenant.api.permission:quotes,create');
    Route::post('/v1/tenant/quotations/{id}/dispatch', [QuotationController::class, 'dispatchQuotation'])->middleware('tenant.api.permission:quotes,create');
    Route::post('/quotations/{id}/dispatch', [QuotationController::class, 'dispatchQuotation'])->middleware('tenant.api.permission:quotes,create');

    Route::post('/tenant/quotations', [QuotationController::class, 'store'])->middleware('tenant.api.permission:quotes,create');
    Route::post('/v1/tenant/quotations', [QuotationController::class, 'store'])->middleware('tenant.api.permission:quotes,create');

    // Invoices SDUI View & Creation Schemas
    Route::get('/tenant/views/invoices/create', [ApiInvoiceController::class, 'createSchema']);
    Route::get('/tenant/invoices/create', [ApiInvoiceController::class, 'createSchema']);
    Route::get('/v1/tenant/views/invoices/create', [ApiInvoiceController::class, 'createSchema']);
    Route::get('/v1/tenant/invoices/create', [ApiInvoiceController::class, 'createSchema']);
    Route::get('/invoices/create', [ApiInvoiceController::class, 'createSchema']);
    Route::post('/tenant/invoices', [ApiInvoiceController::class, 'store']);
    Route::post('/v1/tenant/invoices', [ApiInvoiceController::class, 'store']);

    Route::get('/tenant/views/invoices/{id}/actions-sheet', [ApiInvoiceController::class, 'actionsSheet'])->middleware('tenant.api.permission:sales,view');
    Route::get('/v1/tenant/views/invoices/{id}/actions-sheet', [ApiInvoiceController::class, 'actionsSheet'])->middleware('tenant.api.permission:sales,view');
    Route::get('/tenant/views/invoices/actions-sheet/{id?}', [ApiInvoiceController::class, 'actionsSheet'])->middleware('tenant.api.permission:sales,view');
    Route::get('/v1/tenant/views/invoices/actions-sheet/{id?}', [ApiInvoiceController::class, 'actionsSheet'])->middleware('tenant.api.permission:sales,view');
    Route::get('/tenant/invoices/{id}/actions-sheet', [ApiInvoiceController::class, 'actionsSheet'])->middleware('tenant.api.permission:sales,view');
    Route::get('/v1/tenant/invoices/{id}/actions-sheet', [ApiInvoiceController::class, 'actionsSheet'])->middleware('tenant.api.permission:sales,view');
    Route::get('/tenant/invoices/actions-sheet/{id?}', [ApiInvoiceController::class, 'actionsSheet'])->middleware('tenant.api.permission:sales,view');
    Route::get('/v1/tenant/invoices/actions-sheet/{id?}', [ApiInvoiceController::class, 'actionsSheet'])->middleware('tenant.api.permission:sales,view');
    Route::get('/invoices/{id}/actions-sheet', [ApiInvoiceController::class, 'actionsSheet'])->middleware('tenant.api.permission:sales,view');
    Route::get('/invoices/actions-sheet/{id?}', [ApiInvoiceController::class, 'actionsSheet'])->middleware('tenant.api.permission:sales,view');

    // Due Receivables Payment Reminder Bottom Sheet
    Route::get('/tenant/receivables/{id}/reminder-sheet', [ReceivablesController::class, 'reminderSheet'])->middleware('tenant.api.permission:customers,view');
    Route::get('/v1/tenant/receivables/{id}/reminder-sheet', [ReceivablesController::class, 'reminderSheet'])->middleware('tenant.api.permission:customers,view');
    Route::get('/receivables/{id}/reminder-sheet', [ReceivablesController::class, 'reminderSheet'])->middleware('tenant.api.permission:customers,view');

    // Unified Document Dispatch (SMS, WhatsApp, Email, Custom Webhook)
    Route::post('/tenant/dispatch/{type}/{id}', [DispatchController::class, 'dispatchDocument'])->middleware('tenant.api.permission:pos,create');
    Route::post('/v1/tenant/dispatch/{type}/{id}', [DispatchController::class, 'dispatchDocument'])->middleware('tenant.api.permission:pos,create');
    Route::post('/dispatch/{type}/{id}', [DispatchController::class, 'dispatchDocument'])->middleware('tenant.api.permission:pos,create');

    // Central Unified Dispatch Route & Handlers
    Route::get('/tenant/dispatch/channels', [DocumentDispatchController::class, 'getEnabledChannels']);
    Route::get('/v1/tenant/dispatch/channels', [DocumentDispatchController::class, 'getEnabledChannels']);
    Route::get('/documents/channels', [DocumentDispatchController::class, 'getEnabledChannels']);
    Route::get('/v1/documents/channels', [DocumentDispatchController::class, 'getEnabledChannels']);
    Route::get('/tenant/documents/{type}/{id}/dispatch-options', [DocumentDispatchController::class, 'getDispatchOptions']);
    Route::get('/v1/tenant/documents/{type}/{id}/dispatch-options', [DocumentDispatchController::class, 'getDispatchOptions']);
    Route::get('/documents/{type}/{id}/dispatch-options', [DocumentDispatchController::class, 'getDispatchOptions']);
    Route::get('/v1/documents/{type}/{id}/dispatch-options', [DocumentDispatchController::class, 'getDispatchOptions']);
    Route::post('/tenant/documents/dispatch', [DocumentDispatchController::class, 'dispatchDocument']);
    Route::post('/v1/tenant/documents/dispatch', [DocumentDispatchController::class, 'dispatchDocument']);
    Route::post('/documents/dispatch', [DocumentDispatchController::class, 'dispatchDocument']);
    Route::post('/v1/documents/dispatch', [DocumentDispatchController::class, 'dispatchDocument']);
    Route::post('/tenant/dispatch/send', [UnifiedDispatchController::class, 'dispatch']);
    Route::post('/v1/tenant/dispatch/send', [UnifiedDispatchController::class, 'dispatch']);
    Route::post('/tenant/dispatch/batch', [UnifiedDispatchController::class, 'batchDispatch']);
    Route::post('/v1/tenant/dispatch/batch', [UnifiedDispatchController::class, 'batchDispatch']);
    Route::post('/tenant/dispatch/batch-send', [UnifiedDispatchController::class, 'batchDispatch']);
    Route::post('/v1/tenant/dispatch/batch-send', [UnifiedDispatchController::class, 'batchDispatch']);
    Route::post('/dispatch/send', [UnifiedDispatchController::class, 'dispatch']);

    // Document Action & Invoice Preview Bottom Sheets
    Route::get('/tenant/documents/{type}/{id}/actions-sheet', [DocumentActionController::class, 'actionsSheet']);
    Route::get('/v1/tenant/documents/{type}/{id}/actions-sheet', [DocumentActionController::class, 'actionsSheet']);
    Route::get('/tenant/documents/{type}/actions-sheet/{id?}', [DocumentActionController::class, 'actionsSheet']);
    Route::get('/v1/tenant/documents/{type}/actions-sheet/{id?}', [DocumentActionController::class, 'actionsSheet']);

    Route::get('/tenant/invoices/preview-sheet/{id?}', [InvoicePreviewController::class, 'previewSheet']);
    Route::get('/v1/tenant/invoices/preview-sheet/{id?}', [InvoicePreviewController::class, 'previewSheet']);
    Route::get('/tenant/invoices/{id}/preview-sheet', [InvoicePreviewController::class, 'previewSheet']);
    Route::get('/v1/tenant/invoices/{id}/preview-sheet', [InvoicePreviewController::class, 'previewSheet']);
    Route::get('/invoices/preview-sheet/{id?}', [InvoicePreviewController::class, 'previewSheet']);
    Route::get('/invoices/{id}/preview-sheet', [InvoicePreviewController::class, 'previewSheet']);

    // Lead Management SDUI Views & Schemas
    Route::get('/tenant/views/leads', [LeadController::class, 'dashboard'])->middleware('tenant.api.permission:leads,view');
    Route::get('/tenant/views/lead-management', [LeadController::class, 'dashboard'])->middleware('tenant.api.permission:leads,view');
    Route::get('/v1/tenant/views/leads', [LeadController::class, 'dashboard'])->middleware('tenant.api.permission:leads,view');
    Route::get('/v1/tenant/views/lead-management', [LeadController::class, 'dashboard'])->middleware('tenant.api.permission:leads,view');
    Route::get('/tenant/views/create-lead', [LeadController::class, 'createSchema']);
    Route::get('/tenant/leads/create', [LeadController::class, 'createSchema']);
    Route::get('/v1/tenant/views/create-lead', [LeadController::class, 'createSchema']);
    Route::get('/v1/tenant/leads/create', [LeadController::class, 'createSchema']);
    Route::get('/leads/create', [LeadController::class, 'createSchema']);
    Route::get('/tenant/leads/list', [LeadController::class, 'leadsList'])->middleware('tenant.api.permission:leads,view');
    Route::get('/v1/tenant/leads/list', [LeadController::class, 'leadsList'])->middleware('tenant.api.permission:leads,view');
    Route::get('/tenant/leads/followups', [LeadController::class, 'followups'])->middleware('tenant.api.permission:leads,view');
    Route::get('/v1/tenant/leads/followups', [LeadController::class, 'followups'])->middleware('tenant.api.permission:leads,view');

    // Customer Live Autocomplete & Deep Search
    Route::get('/tenant/customers/search', [CustomerController::class, 'search'])->middleware('tenant.api.permission:customers,view');
    Route::get('/v1/tenant/customers/search', [CustomerController::class, 'search'])->middleware('tenant.api.permission:customers,view');
    Route::get('/app/customers/search', [CustomerController::class, 'search'])->middleware('tenant.api.permission:customers,view');
    Route::get('/customers/search', [CustomerController::class, 'search'])->middleware('tenant.api.permission:customers,view');

    // Server-Driven UI Dynamic Schema Views
    Route::get('/tenant/views/{view}', [SduiViewController::class, 'show']);
    Route::get('/app/views/{view}', [SduiViewController::class, 'show']);

    // Catch-all for multi-segment and double-slash SDUI view requests (e.g. /tenant/views//quotations/create, /tenant/views//create-lead, /tenant/views//invoices/create)
    Route::get('/tenant/views/{subpath}', function (Request $request, string $subpath, PermissionChecker $permissions) {
        $trimmed = ltrim($subpath, '/');
        if (preg_match('#(?:^|/)quotations/([A-Za-z0-9\-_]+)/preview(?:-sheet)?$#', $trimmed, $m)) {
            return app(QuotationController::class)->previewView($request, $m[1]);
        }
        if (preg_match('#(?:^|/)quotations/([A-Za-z0-9\-_]+)/send(?:-sheet)?$#', $trimmed, $m)) {
            return app(QuotationController::class)->sendView($request, $m[1]);
        }
        if (preg_match('#(?:^|/)quotations/([A-Za-z0-9\-_]+)$#', $trimmed, $m)) {
            return app(QuotationController::class)->showSchema($request, $m[1]);
        }
        if ($trimmed === 'quotations/create-modal' || str_ends_with($trimmed, 'quotations/create-modal')) {
            return app(QuotationController::class)->createModal($request);
        }
        if ($trimmed === 'quotations/create' || str_ends_with($trimmed, 'quotations/create')) {
            return app(QuotationController::class)->createSchema($request);
        }
        if (str_contains($trimmed, 'invoices/create') || str_ends_with($trimmed, 'invoices/create')) {
            return app(ApiInvoiceController::class)->createSchema($request);
        }
        if (preg_match('#(?:^|/)leads/([A-Za-z0-9\-_]+)#', $trimmed, $m)) {
            $request->merge(['id' => $m[1]]);

            return app(LeadController::class)->showSchema($request, $m[1]);
        }
        if ($trimmed === 'lead-detail' || str_ends_with($trimmed, 'lead-detail')) {
            return app(LeadController::class)->leadDetail($request);
        }
        if ($trimmed === 'create-lead' || str_ends_with($trimmed, 'create-lead') || $trimmed === 'leads/create' || str_ends_with($trimmed, 'leads/create')) {
            return app(LeadController::class)->createSchema($request);
        }
        if ($trimmed === 'leads' || $trimmed === 'lead-management' || $trimmed === 'leadmanagement' || str_ends_with($trimmed, 'views/leads') || str_ends_with($trimmed, 'lead-management')) {
            return app(LeadController::class)->dashboard($request);
        }

        return app(SduiViewController::class)->show($request, $trimmed, $permissions);
    })->where('subpath', '.*');

    Route::get('/app/views/{subpath}', function (Request $request, string $subpath, PermissionChecker $permissions) {
        $trimmed = ltrim($subpath, '/');
        if (preg_match('#(?:^|/)quotations/([A-Za-z0-9\-_]+)/preview(?:-sheet)?$#', $trimmed, $m)) {
            return app(QuotationController::class)->previewView($request, $m[1]);
        }
        if (preg_match('#(?:^|/)quotations/([A-Za-z0-9\-_]+)/send(?:-sheet)?$#', $trimmed, $m)) {
            return app(QuotationController::class)->sendView($request, $m[1]);
        }
        if (preg_match('#(?:^|/)quotations/([A-Za-z0-9\-_]+)$#', $trimmed, $m)) {
            return app(QuotationController::class)->showSchema($request, $m[1]);
        }
        if ($trimmed === 'quotations/create-modal' || str_ends_with($trimmed, 'quotations/create-modal')) {
            return app(QuotationController::class)->createModal($request);
        }
        if ($trimmed === 'quotations/create' || str_ends_with($trimmed, 'quotations/create')) {
            return app(QuotationController::class)->createSchema($request);
        }
        if (str_contains($trimmed, 'invoices/create') || str_ends_with($trimmed, 'invoices/create')) {
            return app(ApiInvoiceController::class)->createSchema($request);
        }
        if (preg_match('#(?:^|/)leads/([A-Za-z0-9\-_]+)#', $trimmed, $m)) {
            $request->merge(['id' => $m[1]]);

            return app(LeadController::class)->showSchema($request, $m[1]);
        }
        if ($trimmed === 'lead-detail' || str_ends_with($trimmed, 'lead-detail')) {
            return app(LeadController::class)->leadDetail($request);
        }
        if ($trimmed === 'create-lead' || str_ends_with($trimmed, 'create-lead') || $trimmed === 'leads/create' || str_ends_with($trimmed, 'leads/create')) {
            return app(LeadController::class)->createSchema($request);
        }
        if ($trimmed === 'leads' || $trimmed === 'lead-management' || $trimmed === 'leadmanagement' || str_ends_with($trimmed, 'views/leads') || str_ends_with($trimmed, 'lead-management')) {
            return app(LeadController::class)->dashboard($request);
        }

        return app(SduiViewController::class)->show($request, $trimmed, $permissions);
    })->where('subpath', '.*');

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
    Route::get('/tenant/roles/schema', [RolePermissionController::class, 'getPermissionsSchema'])->middleware('tenant.api.permission:users,view');
    Route::get('/tenant/permissions', [RolePermissionController::class, 'getPermissionsSchema'])->middleware('tenant.api.permission:users,view');
    Route::get('/roles/schema', [RolePermissionController::class, 'getPermissionsSchema'])->middleware('tenant.api.permission:users,view');
    Route::get('/permissions', [RolePermissionController::class, 'getPermissionsSchema'])->middleware('tenant.api.permission:users,view');
    Route::get('/v1/tenant/roles/schema', [RolePermissionController::class, 'getPermissionsSchema'])->middleware('tenant.api.permission:users,view');
    Route::get('/v1/tenant/permissions', [RolePermissionController::class, 'getPermissionsSchema'])->middleware('tenant.api.permission:users,view');
    Route::get('/tenant/roles', [RoleApiController::class, 'index'])->middleware('tenant.api.permission:users,view');
    Route::post('/tenant/roles', [RoleApiController::class, 'store'])->middleware('tenant.api.permission:users,create');
    Route::match(['put', 'patch'], '/tenant/roles/{id}', [RoleApiController::class, 'update'])->middleware('tenant.api.permission:users,edit');
    Route::delete('/tenant/roles/{id}', [RoleApiController::class, 'destroy'])->middleware('tenant.api.permission:users,edit');

    // Payment methods — unversioned tenant URLs emitted by the SDUI
    // "Payment Methods" settings screen (mirrors /v1/pos/settings/payment-methods).
    // Company-wide: one list consumed by every module's checkout.
    Route::get('/tenant/settings/payment-methods', [SettingsApiController::class, 'paymentMethodsIndex'])->middleware('tenant.api.permission:settings,view');
    Route::post('/tenant/settings/payment-methods', [SettingsApiController::class, 'paymentMethodsStore'])->middleware('tenant.api.permission:settings,edit');
    Route::get('/tenant/settings/payment-methods/{id}/edit-sheet', [SettingsApiController::class, 'paymentMethodsEditSheet'])->middleware('tenant.api.permission:settings,view');
    Route::match(['put', 'post'], '/tenant/settings/payment-methods/{id}', [SettingsApiController::class, 'paymentMethodsUpdate'])->middleware('tenant.api.permission:settings,edit');
    Route::post('/tenant/settings/payment-methods/{id}/toggle', [SettingsApiController::class, 'paymentMethodsToggle'])->middleware('tenant.api.permission:settings,edit');
    Route::post('/tenant/settings/payment-methods/{id}/delete', [SettingsApiController::class, 'paymentMethodsDestroy'])->middleware('tenant.api.permission:settings,edit');
    Route::delete('/tenant/settings/payment-methods/{id}', [SettingsApiController::class, 'paymentMethodsDestroy'])->middleware('tenant.api.permission:settings,edit');

    // Tax rules (rates) — unversioned tenant URLs emitted by the SDUI
    // "Taxes & Compliance" settings screen (mirrors /v1/pos/taxes). They live
    // under Store Settings, not on a separate screen.
    Route::get('/tenant/settings/tax-rules', [PosSyncApiController::class, 'taxRulesIndex'])->middleware('tenant.api.permission:settings,view');
    Route::post('/tenant/settings/tax-rules', [PosSyncApiController::class, 'taxRulesStore'])->middleware('tenant.api.permission:settings,edit');
    Route::post('/tenant/settings/tax-rules/seed-country', [PosSyncApiController::class, 'taxRulesSeedCountry'])->middleware('tenant.api.permission:settings,edit');
    Route::get('/tenant/settings/tax-rules/{id}/edit-sheet', [PosSyncApiController::class, 'taxRulesEditSheet'])->middleware('tenant.api.permission:settings,view');
    Route::match(['put', 'post'], '/tenant/settings/tax-rules/{id}', [PosSyncApiController::class, 'taxRulesUpdate'])->middleware('tenant.api.permission:settings,edit');
    Route::post('/tenant/settings/tax-rules/{id}/set-default', [PosSyncApiController::class, 'taxRulesSetDefault'])->middleware('tenant.api.permission:settings,edit');
    Route::post('/tenant/settings/tax-rules/{id}/toggle', [PosSyncApiController::class, 'taxRulesToggle'])->middleware('tenant.api.permission:settings,edit');
    Route::post('/tenant/settings/tax-rules/{id}/delete', [PosSyncApiController::class, 'taxRulesDestroy'])->middleware('tenant.api.permission:settings,edit');
    Route::delete('/tenant/settings/tax-rules/{id}', [PosSyncApiController::class, 'taxRulesDestroy'])->middleware('tenant.api.permission:settings,edit');

    // Customer search endpoints (Headless CRM / SDUI auto-linking)
    Route::get('/tenant/customers/search', [PosSyncApiController::class, 'customersSearch'])->middleware('tenant.api.permission:customers,view');
    Route::get('/v1/tenant/customers/search', [PosSyncApiController::class, 'customersSearch'])->middleware('tenant.api.permission:customers,view');

    // Sales Reps endpoints
    Route::get('/tenant/staff/sales-reps', [LeadModuleController::class, 'salesReps'])->middleware('tenant.api.permission:users,view');
    Route::get('/v1/tenant/staff/sales-reps', [LeadModuleController::class, 'salesReps'])->middleware('tenant.api.permission:users,view');

    // Lead Management SDUI Schema and RESTful API endpoints
    Route::get('/tenant/leads/schema', [LeadModuleController::class, 'createSchema'])->middleware('tenant.api.permission:leads,view');
    Route::get('/v1/tenant/leads/schema', [LeadModuleController::class, 'createSchema'])->middleware('tenant.api.permission:leads,view');

    Route::get('/tenant/leads', [LeadModuleController::class, 'leadsIndex'])->middleware('tenant.api.permission:leads,view');
    Route::get('/v1/tenant/leads', [LeadModuleController::class, 'leadsIndex'])->middleware('tenant.api.permission:leads,view');
    Route::post('/tenant/leads', [LeadModuleController::class, 'leadsStore'])->middleware('tenant.api.permission:leads,create');
    Route::post('/v1/tenant/leads', [LeadModuleController::class, 'leadsStore'])->middleware('tenant.api.permission:leads,create');
    Route::get('/tenant/leads/{id}', [LeadModuleController::class, 'leadsShow'])->middleware('tenant.api.permission:leads,view');
    Route::get('/v1/tenant/leads/{id}', [LeadModuleController::class, 'leadsShow'])->middleware('tenant.api.permission:leads,view');
    Route::match(['put', 'patch'], '/tenant/leads/{id}', [LeadModuleController::class, 'leadsUpdate'])->middleware('tenant.api.permission:leads,edit');
    Route::match(['put', 'patch'], '/v1/tenant/leads/{id}', [LeadModuleController::class, 'leadsUpdate'])->middleware('tenant.api.permission:leads,edit');
    Route::post('/tenant/leads/{id}/convert-to-invoice', [LeadModuleController::class, 'leadConvertToInvoice'])->middleware('tenant.api.permission:leads,convert');
    Route::post('/v1/tenant/leads/{id}/convert-to-invoice', [LeadModuleController::class, 'leadConvertToInvoice'])->middleware('tenant.api.permission:leads,convert');
    Route::post('/tenant/leads/{id}/convert', [LeadModuleController::class, 'leadConvert'])->middleware('tenant.api.permission:leads,convert');
    Route::post('/v1/tenant/leads/{id}/convert', [LeadModuleController::class, 'leadConvert'])->middleware('tenant.api.permission:leads,convert');
    Route::post('/tenant/leads/{id}/reminders', [LeadModuleController::class, 'leadAddReminder'])->middleware('tenant.api.permission:leads,edit');
    Route::post('/v1/tenant/leads/{id}/reminders', [LeadModuleController::class, 'leadAddReminder'])->middleware('tenant.api.permission:leads,edit');

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

    // Storefront Domain, Banner, Auth & Payment Gateway settings
    Route::get('/tenant/storefront/domain-config', [StorefrontSettingsController::class, 'getDomainConfig']);
    Route::match(['post', 'put'], '/tenant/storefront/domain-config', [StorefrontSettingsController::class, 'updateDomainConfig']);
    Route::get('/v1/tenant/storefront/domain-config', [StorefrontSettingsController::class, 'getDomainConfig']);
    Route::match(['post', 'put'], '/v1/tenant/storefront/domain-config', [StorefrontSettingsController::class, 'updateDomainConfig']);

    Route::get('/tenant/storefront/banner-auth', [StorefrontSettingsController::class, 'getBannerAuth']);
    Route::match(['post', 'put'], '/tenant/storefront/banner-auth', [StorefrontSettingsController::class, 'updateBannerAuth']);
    Route::get('/v1/tenant/storefront/banner-auth', [StorefrontSettingsController::class, 'getBannerAuth']);
    Route::match(['post', 'put'], '/v1/tenant/storefront/banner-auth', [StorefrontSettingsController::class, 'updateBannerAuth']);

    Route::get('/tenant/storefront/payment-gateways', [StorefrontSettingsController::class, 'getPaymentGateways']);
    Route::match(['post', 'put'], '/tenant/storefront/payment-gateways', [StorefrontSettingsController::class, 'updatePaymentGateways']);
    Route::get('/v1/tenant/storefront/payment-gateways', [StorefrontSettingsController::class, 'getPaymentGateways']);
    Route::match(['post', 'put'], '/v1/tenant/storefront/payment-gateways', [StorefrontSettingsController::class, 'updatePaymentGateways']);

    // Tenant Storefront Inquiries Management
    Route::get('/tenant/storefront/inquiries', [StoreInquiryController::class, 'index']);
    Route::match(['post', 'put'], '/tenant/storefront/inquiries/{id}/status', [StoreInquiryController::class, 'updateStatus']);
    Route::delete('/tenant/storefront/inquiries/{id}', [StoreInquiryController::class, 'destroy']);
    Route::post('/tenant/storefront/inquiries/{id}/delete', [StoreInquiryController::class, 'destroy']);
    Route::get('/v1/tenant/storefront/inquiries', [StoreInquiryController::class, 'index']);
    Route::match(['post', 'put'], '/v1/tenant/storefront/inquiries/{id}/status', [StoreInquiryController::class, 'updateStatus']);
    Route::delete('/v1/tenant/storefront/inquiries/{id}', [StoreInquiryController::class, 'destroy']);
    Route::post('/v1/tenant/storefront/inquiries/{id}/delete', [StoreInquiryController::class, 'destroy']);

    // Tenant Promotional Coupons & Discounts CRUD
    Route::get('/tenant/coupons', [CouponApiController::class, 'index']);
    Route::post('/tenant/coupons', [CouponApiController::class, 'store']);
    Route::get('/tenant/coupons/{id}', [CouponApiController::class, 'show']);
    Route::match(['put', 'post'], '/tenant/coupons/{id}', [CouponApiController::class, 'update']);
    Route::delete('/tenant/coupons/{id}', [CouponApiController::class, 'destroy']);
    Route::post('/tenant/coupons/{id}/delete', [CouponApiController::class, 'destroy']);

    Route::get('/v1/tenant/coupons', [CouponApiController::class, 'index']);
    Route::post('/v1/tenant/coupons', [CouponApiController::class, 'store']);
    Route::get('/v1/tenant/coupons/{id}', [CouponApiController::class, 'show']);
    Route::match(['put', 'post'], '/v1/tenant/coupons/{id}', [CouponApiController::class, 'update']);
    Route::delete('/v1/tenant/coupons/{id}', [CouponApiController::class, 'destroy']);
    Route::post('/v1/tenant/coupons/{id}/delete', [CouponApiController::class, 'destroy']);

    // Tenant Store FAQs & Help Center CRUD
    Route::get('/tenant/faqs', [FaqApiController::class, 'index']);
    Route::post('/tenant/faqs', [FaqApiController::class, 'store']);
    Route::get('/tenant/faqs/{id}', [FaqApiController::class, 'show']);
    Route::match(['put', 'post'], '/tenant/faqs/{id}', [FaqApiController::class, 'update']);
    Route::delete('/tenant/faqs/{id}', [FaqApiController::class, 'destroy']);
    Route::post('/tenant/faqs/{id}/delete', [FaqApiController::class, 'destroy']);

    Route::get('/v1/tenant/faqs', [FaqApiController::class, 'index']);
    Route::post('/v1/tenant/faqs', [FaqApiController::class, 'store']);
    Route::get('/v1/tenant/faqs/{id}', [FaqApiController::class, 'show']);
    Route::match(['put', 'post'], '/v1/tenant/faqs/{id}', [FaqApiController::class, 'update']);
    Route::delete('/v1/tenant/faqs/{id}', [FaqApiController::class, 'destroy']);
    Route::post('/v1/tenant/faqs/{id}/delete', [FaqApiController::class, 'destroy']);

    // Tenant Storefront Reviews & Moderation
    Route::get('/tenant/storefront/reviews', [\App\Http\Controllers\Tenant\StorefrontReviewController::class, 'tenantIndex']);
    Route::post('/tenant/storefront/reviews', [\App\Http\Controllers\Tenant\StorefrontReviewController::class, 'tenantStore']);
    Route::match(['post', 'put'], '/tenant/storefront/reviews/{id}/toggle-approval', [\App\Http\Controllers\Tenant\StorefrontReviewController::class, 'toggleApproval']);
    Route::delete('/tenant/storefront/reviews/{id}', [\App\Http\Controllers\Tenant\StorefrontReviewController::class, 'tenantDestroy']);
    Route::post('/tenant/storefront/reviews/{id}/delete', [\App\Http\Controllers\Tenant\StorefrontReviewController::class, 'tenantDestroy']);
    Route::match(['post', 'put'], '/tenant/storefront/reviews/settings', [\App\Http\Controllers\Tenant\StorefrontReviewController::class, 'updateSettings']);

    Route::get('/v1/tenant/storefront/reviews', [\App\Http\Controllers\Tenant\StorefrontReviewController::class, 'tenantIndex']);
    Route::post('/v1/tenant/storefront/reviews', [\App\Http\Controllers\Tenant\StorefrontReviewController::class, 'tenantStore']);
    Route::match(['post', 'put'], '/v1/tenant/storefront/reviews/{id}/toggle-approval', [\App\Http\Controllers\Tenant\StorefrontReviewController::class, 'toggleApproval']);
    Route::delete('/v1/tenant/storefront/reviews/{id}', [\App\Http\Controllers\Tenant\StorefrontReviewController::class, 'tenantDestroy']);
    Route::post('/v1/tenant/storefront/reviews/{id}/delete', [\App\Http\Controllers\Tenant\StorefrontReviewController::class, 'tenantDestroy']);
    Route::match(['post', 'put'], '/v1/tenant/storefront/reviews/settings', [\App\Http\Controllers\Tenant\StorefrontReviewController::class, 'updateSettings']);

    // Tenant Storefront CMS Pages & Navigation Menus
    Route::get('/tenant/storefront/pages', [\App\Http\Controllers\Tenant\StorefrontMenuController::class, 'pagesIndex']);
    Route::post('/tenant/storefront/pages', [\App\Http\Controllers\Tenant\StorefrontMenuController::class, 'pagesStore']);
    Route::get('/tenant/storefront/pages/{id}', [\App\Http\Controllers\Tenant\StorefrontMenuController::class, 'pagesShow']);
    Route::match(['put', 'post'], '/tenant/storefront/pages/{id}', [\App\Http\Controllers\Tenant\StorefrontMenuController::class, 'pagesUpdate']);
    Route::delete('/tenant/storefront/pages/{id}', [\App\Http\Controllers\Tenant\StorefrontMenuController::class, 'pagesDestroy']);
    Route::post('/tenant/storefront/pages/{id}/delete', [\App\Http\Controllers\Tenant\StorefrontMenuController::class, 'pagesDestroy']);

    Route::get('/v1/tenant/storefront/pages', [\App\Http\Controllers\Tenant\StorefrontMenuController::class, 'pagesIndex']);
    Route::post('/v1/tenant/storefront/pages', [\App\Http\Controllers\Tenant\StorefrontMenuController::class, 'pagesStore']);
    Route::get('/v1/tenant/storefront/pages/{id}', [\App\Http\Controllers\Tenant\StorefrontMenuController::class, 'pagesShow']);
    Route::match(['put', 'post'], '/v1/tenant/storefront/pages/{id}', [\App\Http\Controllers\Tenant\StorefrontMenuController::class, 'pagesUpdate']);
    Route::delete('/v1/tenant/storefront/pages/{id}', [\App\Http\Controllers\Tenant\StorefrontMenuController::class, 'pagesDestroy']);
    Route::post('/v1/tenant/storefront/pages/{id}/delete', [\App\Http\Controllers\Tenant\StorefrontMenuController::class, 'pagesDestroy']);

    Route::get('/tenant/storefront/menus', [\App\Http\Controllers\Tenant\StorefrontMenuController::class, 'menusIndex']);
    Route::post('/tenant/storefront/menus', [\App\Http\Controllers\Tenant\StorefrontMenuController::class, 'menusStore']);
    Route::post('/tenant/storefront/menus/reorder', [\App\Http\Controllers\Tenant\StorefrontMenuController::class, 'reorder']);
    Route::match(['put', 'post'], '/tenant/storefront/menus/{id}', [\App\Http\Controllers\Tenant\StorefrontMenuController::class, 'menusUpdate'])->whereNumber('id');
    Route::match(['put', 'post'], '/tenant/storefront/menus/{id}/toggle-visibility', [\App\Http\Controllers\Tenant\StorefrontMenuController::class, 'toggleVisibility'])->whereNumber('id');
    Route::delete('/tenant/storefront/menus/{id}', [\App\Http\Controllers\Tenant\StorefrontMenuController::class, 'menusDestroy'])->whereNumber('id');
    Route::post('/tenant/storefront/menus/{id}/delete', [\App\Http\Controllers\Tenant\StorefrontMenuController::class, 'menusDestroy'])->whereNumber('id');

    Route::get('/v1/tenant/storefront/menus', [\App\Http\Controllers\Tenant\StorefrontMenuController::class, 'menusIndex']);
    Route::post('/v1/tenant/storefront/menus', [\App\Http\Controllers\Tenant\StorefrontMenuController::class, 'menusStore']);
    Route::post('/v1/tenant/storefront/menus/reorder', [\App\Http\Controllers\Tenant\StorefrontMenuController::class, 'reorder']);
    Route::match(['put', 'post'], '/v1/tenant/storefront/menus/{id}', [\App\Http\Controllers\Tenant\StorefrontMenuController::class, 'menusUpdate'])->whereNumber('id');
    Route::match(['put', 'post'], '/v1/tenant/storefront/menus/{id}/toggle-visibility', [\App\Http\Controllers\Tenant\StorefrontMenuController::class, 'toggleVisibility'])->whereNumber('id');
    Route::delete('/v1/tenant/storefront/menus/{id}', [\App\Http\Controllers\Tenant\StorefrontMenuController::class, 'menusDestroy'])->whereNumber('id');
    Route::post('/v1/tenant/storefront/menus/{id}/delete', [\App\Http\Controllers\Tenant\StorefrontMenuController::class, 'menusDestroy'])->whereNumber('id');

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
        Route::get('/tickets/{id}/share-sheet', [RepairApiController::class, 'ticketsShareDispatchSheet'])->middleware('tenant.api.permission:repair,view');
        Route::post('/tickets/{id}/dispatch', [RepairApiController::class, 'ticketsDispatch'])->middleware('tenant.api.permission:repair,view');
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

    // Restaurant & Cafe POS Module Routes
    Route::prefix('tenant/restaurant')->group(function () {
        Route::get('/floors', [RestaurantApiController::class, 'floorsIndex'])->middleware('tenant.api.permission:pos,view');
        Route::post('/floors', [RestaurantApiController::class, 'floorsStore'])->middleware('tenant.api.permission:pos,edit');
        Route::put('/floors/{id}', [RestaurantApiController::class, 'floorsUpdate'])->middleware('tenant.api.permission:pos,edit');
        Route::delete('/floors/{id}', [RestaurantApiController::class, 'floorsDestroy'])->middleware('tenant.api.permission:pos,edit');
        Route::get('/tables', [RestaurantApiController::class, 'tablesIndex'])->middleware('tenant.api.permission:pos,view');
        Route::post('/tables', [RestaurantApiController::class, 'tablesStore'])->middleware('tenant.api.permission:pos,edit');
        Route::get('/tables/{id}', [RestaurantApiController::class, 'tableShow'])->middleware('tenant.api.permission:pos,view');
        Route::get('/tables/{id}/actions-sheet', [RestaurantApiController::class, 'tableActionsSheet'])->middleware('tenant.api.permission:pos,view');
        Route::put('/tables/{id}', [RestaurantApiController::class, 'tablesUpdate'])->middleware('tenant.api.permission:pos,edit');
        Route::post('/tables/{id}/status', [RestaurantApiController::class, 'tablesSetStatus'])->middleware('tenant.api.permission:pos,edit');
        Route::delete('/tables/{id}', [RestaurantApiController::class, 'tablesDestroy'])->middleware('tenant.api.permission:pos,edit');
        Route::post('/orders/send-to-kitchen', [RestaurantApiController::class, 'sendToKitchen'])->middleware('tenant.api.permission:pos,create');
        Route::post('/orders/{saleId}/settle', [RestaurantApiController::class, 'settle'])->middleware('tenant.api.permission:pos,create');
        Route::get('/kot', [RestaurantApiController::class, 'kotIndex'])->middleware('tenant.api.permission:pos,view');
        Route::post('/kot/{id}/status', [RestaurantApiController::class, 'kotUpdateStatus'])->middleware('tenant.api.permission:pos,edit');
        Route::post('/kot/{id}/dismiss-alarm', [RestaurantApiController::class, 'kotDismissAlarm'])->middleware('tenant.api.permission:pos,edit');
        Route::post('/kot/{id}/print', [RestaurantApiController::class, 'kotPrint'])->middleware('tenant.api.permission:pos,view');
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
    Route::get('/ui/navigation', [SettingsApiController::class, 'getDrawerNavigation']);
    Route::get('/v1/ui/navigation', [SettingsApiController::class, 'getDrawerNavigation']);
    Route::get('/tenant/ui/navigation', [SettingsApiController::class, 'getDrawerNavigation']);
    Route::get('/v1/tenant/ui/navigation', [SettingsApiController::class, 'getDrawerNavigation']);
    Route::get('/v1/tenant/navigation', [SettingsApiController::class, 'getDrawerNavigation']);
    Route::get('/navigation/menu', [NavigationController::class, 'getDrawerMenu']);
    Route::get('/drawer/menu', [NavigationController::class, 'getDrawerMenu']);
    Route::get('/drawer-menu', [NavigationController::class, 'getDrawerMenu']);
    Route::get('/menu', [NavigationController::class, 'getDrawerMenu']);
    Route::get('/tenant/navigation/menu', [NavigationController::class, 'getDrawerMenu']);
    Route::get('/app/navigation/menu', [NavigationController::class, 'getDrawerMenu']);
    Route::get('/v1/tenant/navigation/menu', [NavigationController::class, 'getDrawerMenu']);
    Route::get('/v1/navigation/menu', [NavigationController::class, 'getDrawerMenu']);
    Route::get('/tenant/drawer/menu', [NavigationController::class, 'getDrawerMenu']);
    Route::get('/app/drawer/menu', [NavigationController::class, 'getDrawerMenu']);

    // Navigation Menu Customization Persistence
    Route::post('/navigation/menu', [NavigationMenuController::class, 'saveMenuSettings']);
    Route::post('/drawer/menu', [NavigationMenuController::class, 'saveMenuSettings']);
    Route::post('/drawer-menu', [NavigationMenuController::class, 'saveMenuSettings']);
    Route::post('/menu', [NavigationMenuController::class, 'saveMenuSettings']);
    Route::post('/tenant/navigation/menu', [NavigationMenuController::class, 'saveMenuSettings']);
    Route::post('/app/navigation/menu', [NavigationMenuController::class, 'saveMenuSettings']);
    Route::post('/settings/navigation-menu', [NavigationMenuController::class, 'saveMenuSettings']);
    Route::post('/tenant/settings/navigation-menu', [NavigationMenuController::class, 'saveMenuSettings']);

    // Dynamic Theme Tokens & Zero-White-Leak Surface
    Route::get('/tenant/theme', [SettingsApiController::class, 'getTheme']);
    Route::get('/v1/tenant/theme', [SettingsApiController::class, 'getTheme']);
    Route::get('/app/theme', [SettingsApiController::class, 'getTheme']);
    Route::get('/v1/theme', [SettingsApiController::class, 'getTheme']);
    Route::get('/theme', [SettingsApiController::class, 'getTheme']);

    // Store Profile Management & Real-Time Cache Invalidation
    Route::get('/tenant/store-profile', [StoreProfileController::class, 'show'])->middleware('tenant.api.permission:settings,view');
    Route::get('/v1/tenant/store-profile', [StoreProfileController::class, 'show'])->middleware('tenant.api.permission:settings,view');
    Route::get('/app/store-profile', [StoreProfileController::class, 'show'])->middleware('tenant.api.permission:settings,view');
    Route::match(['post', 'put'], '/tenant/store-profile', [StoreProfileController::class, 'update'])->middleware('tenant.api.permission:settings,edit');
    Route::match(['post', 'put'], '/v1/tenant/store-profile', [StoreProfileController::class, 'update'])->middleware('tenant.api.permission:settings,edit');
    Route::match(['post', 'put'], '/app/store-profile', [StoreProfileController::class, 'update'])->middleware('tenant.api.permission:settings,edit');

    // Form Field Labels & Dynamic Custom Fields
    Route::get('/tenant/settings/form-labels', [SettingsApiController::class, 'getFormLabels'])->middleware('tenant.api.permission:settings,view');
    Route::post('/tenant/settings/form-labels', [SettingsApiController::class, 'updateFormLabels'])->middleware('tenant.api.permission:settings,edit');
    Route::get('/app/settings/form-labels', [SettingsApiController::class, 'getFormLabels'])->middleware('tenant.api.permission:settings,view');
    Route::post('/app/settings/form-labels', [SettingsApiController::class, 'updateFormLabels'])->middleware('tenant.api.permission:settings,edit');

    // Tenant App Preferences: Notifications & Audio Alerts
    Route::get('/tenant/settings/notifications-audio', [TenantAppPreferencesController::class, 'getNotificationAlertsScreen'])->middleware('tenant.api.permission:settings,view');
    Route::get('/app/settings/notifications-audio', [TenantAppPreferencesController::class, 'getNotificationAlertsScreen'])->middleware('tenant.api.permission:settings,view');
    Route::get('/v1/tenant/settings/notifications-audio', [TenantAppPreferencesController::class, 'getNotificationAlertsScreen'])->middleware('tenant.api.permission:settings,view');
    Route::match(['post', 'put'], '/tenant/settings/notifications-audio', [TenantAppPreferencesController::class, 'saveNotificationPreferences'])->middleware('tenant.api.permission:settings,edit');
    Route::match(['post', 'put'], '/app/settings/notifications-audio', [TenantAppPreferencesController::class, 'saveNotificationPreferences'])->middleware('tenant.api.permission:settings,edit');
    Route::match(['post', 'put'], '/v1/tenant/settings/notifications-audio', [TenantAppPreferencesController::class, 'saveNotificationPreferences'])->middleware('tenant.api.permission:settings,edit');
    Route::post('/tenant/settings/auto-reminders', [TenantAppPreferencesController::class, 'saveAutoReminders'])->middleware('tenant.api.permission:settings,edit');
    Route::post('/v1/tenant/settings/auto-reminders', [TenantAppPreferencesController::class, 'saveAutoReminders'])->middleware('tenant.api.permission:settings,edit');

    Route::get('/tenant/settings/app-preferences/notifications', [TenantAppPreferencesController::class, 'getNotificationAlertsScreen'])->middleware('tenant.api.permission:settings,view');
    Route::get('/app/settings/app-preferences/notifications', [TenantAppPreferencesController::class, 'getNotificationAlertsScreen'])->middleware('tenant.api.permission:settings,view');
    Route::get('/v1/tenant/settings/app-preferences/notifications', [TenantAppPreferencesController::class, 'getNotificationAlertsScreen'])->middleware('tenant.api.permission:settings,view');
    Route::get('/tenant/settings/app-preferences', [TenantAppPreferencesController::class, 'getNotificationAlertsScreen'])->middleware('tenant.api.permission:settings,view');
    Route::get('/v1/tenant/settings/app-preferences', [TenantAppPreferencesController::class, 'getNotificationAlertsScreen'])->middleware('tenant.api.permission:settings,view');
    Route::match(['post', 'put'], '/tenant/settings/app-preferences/notifications', [TenantAppPreferencesController::class, 'saveNotificationPreferences'])->middleware('tenant.api.permission:settings,edit');
    Route::match(['post', 'put'], '/app/settings/app-preferences/notifications', [TenantAppPreferencesController::class, 'saveNotificationPreferences'])->middleware('tenant.api.permission:settings,edit');
    Route::match(['post', 'put'], '/v1/tenant/settings/app-preferences/notifications', [TenantAppPreferencesController::class, 'saveNotificationPreferences'])->middleware('tenant.api.permission:settings,edit');
    Route::match(['post', 'put'], '/tenant/settings/app-preferences', [TenantAppPreferencesController::class, 'saveNotificationPreferences'])->middleware('tenant.api.permission:settings,edit');
    Route::match(['post', 'put'], '/v1/tenant/settings/app-preferences', [TenantAppPreferencesController::class, 'saveNotificationPreferences'])->middleware('tenant.api.permission:settings,edit');
    Route::post('/tenant/settings/app-preferences/notifications/upload-audio', [TenantAppPreferencesController::class, 'uploadAudio'])->middleware('tenant.api.permission:settings,edit');
    Route::post('/app/settings/app-preferences/notifications/upload-audio', [TenantAppPreferencesController::class, 'uploadAudio'])->middleware('tenant.api.permission:settings,edit');
    Route::post('/v1/tenant/settings/app-preferences/notifications/upload-audio', [TenantAppPreferencesController::class, 'uploadAudio'])->middleware('tenant.api.permission:settings,edit');

    // Tenant App Preferences & Drawer Color Palette Routes
    Route::match(['get', 'post', 'put'], '/tenant/preferences', [\App\Http\Controllers\Api\AppPreferenceController::class, 'updatePreferences']);
    Route::match(['get', 'post', 'put'], '/app/preferences', [\App\Http\Controllers\Api\AppPreferenceController::class, 'updatePreferences']);
    Route::match(['get', 'post', 'put'], '/v1/tenant/preferences', [\App\Http\Controllers\Api\AppPreferenceController::class, 'updatePreferences']);
    Route::match(['get', 'post', 'put'], '/tenant/app-preferences', [\App\Http\Controllers\Api\AppPreferenceController::class, 'updatePreferences']);
    Route::match(['get', 'post', 'put'], '/app/app-preferences', [\App\Http\Controllers\Api\AppPreferenceController::class, 'updatePreferences']);
    Route::match(['get', 'post', 'put'], '/v1/tenant/app-preferences', [\App\Http\Controllers\Api\AppPreferenceController::class, 'updatePreferences']);
    Route::match(['get', 'post', 'put'], '/tenant/settings/app-preferences/drawer', [\App\Http\Controllers\Api\AppPreferenceController::class, 'updatePreferences']);
    Route::match(['get', 'post', 'put'], '/app/settings/app-preferences/drawer', [\App\Http\Controllers\Api\AppPreferenceController::class, 'updatePreferences']);
    Route::match(['get', 'post', 'put'], '/v1/tenant/settings/app-preferences/drawer', [\App\Http\Controllers\Api\AppPreferenceController::class, 'updatePreferences']);

    Route::delete('/tenant/products/{id}', [PosSyncApiController::class, 'inventoryDestroyProduct'])->middleware('tenant.api.permission:products,edit');
    Route::delete('/app/products/{id}', [PosSyncApiController::class, 'inventoryDestroyProduct'])->middleware('tenant.api.permission:products,edit');

    // Tenant Notification Gateways & SDUI API Integrations
    Route::get('/tenant/api-integrations', [ApiIntegrationsController::class, 'index'])->middleware('tenant.api.permission:settings,view');
    Route::get('/app/api-integrations', [ApiIntegrationsController::class, 'index'])->middleware('tenant.api.permission:settings,view');
    Route::get('/v1/tenant/api-integrations', [ApiIntegrationsController::class, 'index'])->middleware('tenant.api.permission:settings,view');
    Route::match(['post', 'put'], '/tenant/api-integrations/{channel}', [ApiIntegrationsController::class, 'saveChannel'])->middleware('tenant.api.permission:settings,edit');
    Route::match(['post', 'put'], '/app/api-integrations/{channel}', [ApiIntegrationsController::class, 'saveChannel'])->middleware('tenant.api.permission:settings,edit');
    Route::match(['post', 'put'], '/v1/tenant/api-integrations/{channel}', [ApiIntegrationsController::class, 'saveChannel'])->middleware('tenant.api.permission:settings,edit');
    Route::post('/tenant/api-integrations/{channel}/test', [ApiIntegrationsController::class, 'testChannel'])->middleware('tenant.api.permission:settings,edit');
    Route::post('/app/api-integrations/{channel}/test', [ApiIntegrationsController::class, 'testChannel'])->middleware('tenant.api.permission:settings,edit');
    Route::post('/v1/tenant/api-integrations/{channel}/test', [ApiIntegrationsController::class, 'testChannel'])->middleware('tenant.api.permission:settings,edit');
    Route::post('/tenant/notifications/dispatch', [ApiIntegrationsController::class, 'dispatchDocument'])->middleware('tenant.api.permission:pos,create');
    Route::post('/app/notifications/dispatch', [ApiIntegrationsController::class, 'dispatchDocument'])->middleware('tenant.api.permission:pos,create');
    Route::post('/v1/tenant/notifications/dispatch', [ApiIntegrationsController::class, 'dispatchDocument'])->middleware('tenant.api.permission:pos,create');
    Route::post('/tenant/api-keys/regenerate', [ApiIntegrationsController::class, 'regenerateApiKey'])->middleware('tenant.api.permission:settings,edit');
    Route::post('/app/api-keys/regenerate', [ApiIntegrationsController::class, 'regenerateApiKey'])->middleware('tenant.api.permission:settings,edit');
    Route::post('/v1/tenant/api-keys/regenerate', [ApiIntegrationsController::class, 'regenerateApiKey'])->middleware('tenant.api.permission:settings,edit');

    // Direct SMS Gateway Settings & Test Dispatch Endpoints
    Route::post('/tenant/settings/sms-gateway', [TenantSettingsController::class, 'saveSmsCredentials'])->middleware('tenant.api.permission:settings,edit');
    Route::post('/app/settings/sms-gateway', [TenantSettingsController::class, 'saveSmsCredentials'])->middleware('tenant.api.permission:settings,edit');
    Route::post('/v1/tenant/settings/sms-gateway', [TenantSettingsController::class, 'saveSmsCredentials'])->middleware('tenant.api.permission:settings,edit');
    Route::post('/tenant/settings/sms-gateway/test', [TenantSettingsController::class, 'sendTestSms'])->middleware('tenant.api.permission:settings,edit');
    Route::post('/app/settings/sms-gateway/test', [TenantSettingsController::class, 'sendTestSms'])->middleware('tenant.api.permission:settings,edit');
    Route::post('/v1/tenant/settings/sms-gateway/test', [TenantSettingsController::class, 'sendTestSms'])->middleware('tenant.api.permission:settings,edit');

    // Server-Driven UI Declarative Form Submissions
    Route::match(['post', 'put'], '/tenant/settings/{section}', [SduiViewController::class, 'submitSettings'])->middleware('tenant.api.permission:settings,edit');
    Route::match(['post', 'put'], '/app/settings/{section}', [SduiViewController::class, 'submitSettings'])->middleware('tenant.api.permission:settings,edit');
    Route::match(['post', 'put'], '/v1/tenant/settings/{section}', [SduiViewController::class, 'submitSettings'])->middleware('tenant.api.permission:settings,edit');

    // Post-Checkout Invoicing & Sales History Actions
    Route::post('/tenant/sales/{id}/send-invoice', [SaleApiController::class, 'sendInvoice']);
    Route::post('/app/sales/{id}/send-invoice', [SaleApiController::class, 'sendInvoice']);
    Route::post('/tenant/sales/{id}/print', [SaleApiController::class, 'printInvoice']);
    Route::post('/app/sales/{id}/print', [SaleApiController::class, 'printInvoice']);

    // Raw invoice PDF bytes for the native Post-Sale Action Sheet's
    // "Preview & Print" row — token-authenticated (bearer), returns
    // application/pdf, never HTML, so it renders straight into the device's
    // native PDF viewer with no web session / /login bounce.
    Route::get('/tenant/invoices/{sale}/pdf-stream', [InvoiceController::class, 'pdfStream'])
        ->middleware('tenant.api.permission:sales,view')
        ->name('invoice.pdf.stream');
    Route::get('/app/invoices/{sale}/pdf-stream', [InvoiceController::class, 'pdfStream'])
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
        Route::get('/search', [CustomerController::class, 'search'])->middleware('tenant.api.permission:customers,view');
        Route::post('/', [PosSyncApiController::class, 'customersStore'])->middleware('tenant.api.permission:customers,create');
        Route::get('/{id}/ledger', [PosSyncApiController::class, 'customerLedger'])->middleware('tenant.api.permission:customers,view');
        Route::post('/{id}/payment', [PosSyncApiController::class, 'customerRecordPayment'])->middleware('tenant.api.permission:finance,edit');
    });
    Route::prefix('app/customers')->group(function () {
        Route::get('/', [PosSyncApiController::class, 'customersIndex'])->middleware('tenant.api.permission:customers,view');
        Route::get('/search', [CustomerController::class, 'search'])->middleware('tenant.api.permission:customers,view');
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
    Route::get('/auth/check-subdomain', [AuthApiController::class, 'checkSubdomain']);
    Route::get('/public/check-subdomain', [AuthApiController::class, 'checkSubdomain']);
    Route::get('/check-subdomain', [AuthApiController::class, 'checkSubdomain']);
    Route::get('/auth/registration-meta', [PosSyncApiController::class, 'registrationMeta']);
    Route::get('/app/registration-meta', [PosSyncApiController::class, 'registrationMeta']);
    Route::get('/auth/branding', [PosSyncApiController::class, 'branding']);
    Route::get('/auth/public-settings', [PosSyncApiController::class, 'publicSettings']);
    Route::get('/app/public-settings', [PosSyncApiController::class, 'publicSettings']);
    Route::get('/public/settings', [PosSyncApiController::class, 'publicSettings']);
    Route::get('/public/landing', [\App\Http\Controllers\Api\V1\LandingApiController::class, 'show']);
    Route::get('/public/landing-config', [\App\Http\Controllers\Api\V1\LandingApiController::class, 'show']);
    Route::get('/public/plans', [\App\Http\Controllers\Api\V1\LandingApiController::class, 'plans']);
    Route::post('/public/contact-us', [\App\Http\Controllers\Api\V1\LandingApiController::class, 'submitContact']);
    Route::post('/public/contact', [\App\Http\Controllers\Api\V1\LandingApiController::class, 'submitContact']);
    Route::get('/auth/landing', [\App\Http\Controllers\Api\V1\LandingApiController::class, 'show']);
    Route::get('/auth/auth-config', [PosSyncApiController::class, 'authConfig']);
    Route::get('/auth-config', [PosSyncApiController::class, 'authConfig']);
    Route::get('/app/auth-config', [PosSyncApiController::class, 'authConfig']);
    Route::get('/auth/push-config', [PushDeviceApiController::class, 'config']);

    // Protected POS Endpoints (Require API Key or Bearer Token)
    Route::middleware([AuthenticateTenantApi::class, PreventDemoModifications::class])->group(function () {
        Route::get('/auth/session', [PosSyncApiController::class, 'session']);
        Route::get('/auth/me', [PosSyncApiController::class, 'session']);
        Route::get('/v1/auth/me', [PosSyncApiController::class, 'session']);
        Route::post('/auth/desktop-session', [PosSyncApiController::class, 'desktopWebSession']);
        Route::get('/status', [PosSyncApiController::class, 'status']);
        Route::post('/push-devices', [PushDeviceApiController::class, 'store']);
        Route::delete('/push-devices', [PushDeviceApiController::class, 'destroy']);
        Route::post('/push-devices/test', [PushDeviceApiController::class, 'test']);
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
        Route::match(['get', 'post'], '/receivables/{sale}/remind', [PosSyncApiController::class, 'remindReceivable'])->middleware('tenant.api.permission:finance,edit');
        Route::get('/receivables/{sale}/reminder-sheet', [ReceivablesController::class, 'reminderSheet'])->middleware('tenant.api.permission:customers,view');
        Route::put('/receivables/{sale}/reminder', [PosSyncApiController::class, 'scheduleReceivableReminder'])->middleware('tenant.api.permission:finance,edit');
        Route::post('/dispatch/{type}/{id}', [DispatchController::class, 'dispatchDocument'])->middleware('tenant.api.permission:pos,create');

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
        Route::post('/taxes/seed-country', [PosSyncApiController::class, 'taxRulesSeedCountry'])->middleware('tenant.api.permission:settings,edit');
        Route::get('/taxes/{id}/edit-sheet', [PosSyncApiController::class, 'taxRulesEditSheet'])->middleware('tenant.api.permission:settings,view');
        Route::match(['put', 'post'], '/taxes/{id}', [PosSyncApiController::class, 'taxRulesUpdate'])->middleware('tenant.api.permission:settings,edit');
        Route::delete('/taxes/{id}', [PosSyncApiController::class, 'taxRulesDestroy'])->middleware('tenant.api.permission:settings,edit');
        Route::post('/taxes/{id}/delete', [PosSyncApiController::class, 'taxRulesDestroy'])->middleware('tenant.api.permission:settings,edit');
        Route::post('/taxes/{id}/set-default', [PosSyncApiController::class, 'taxRulesSetDefault'])->middleware('tenant.api.permission:settings,edit');
        Route::post('/taxes/{id}/toggle', [PosSyncApiController::class, 'taxRulesToggle'])->middleware('tenant.api.permission:settings,edit');
        Route::get('/settings/navigation-labels', [SettingsApiController::class, 'getNavigationLabels'])->middleware('tenant.api.permission:settings,view');
        Route::post('/settings/navigation-labels', [SettingsApiController::class, 'updateNavigationLabels'])->middleware('tenant.api.permission:settings,edit');
        Route::get('/navigation/drawer', [SettingsApiController::class, 'getDrawerNavigation']);
        Route::get('/navigation', [SettingsApiController::class, 'getDrawerNavigation']);
        Route::get('/ui/navigation', [SettingsApiController::class, 'getDrawerNavigation']);
        Route::get('/navigation/menu', [NavigationController::class, 'getDrawerMenu']);
        Route::get('/drawer/menu', [NavigationController::class, 'getDrawerMenu']);
        Route::get('/drawer-menu', [NavigationController::class, 'getDrawerMenu']);
        Route::get('/menu', [NavigationController::class, 'getDrawerMenu']);
        Route::get('/settings/form-labels', [SettingsApiController::class, 'getFormLabels'])->middleware('tenant.api.permission:settings,view');
        Route::post('/settings/form-labels', [SettingsApiController::class, 'updateFormLabels'])->middleware('tenant.api.permission:settings,edit');

        // Subscription & Billing
        Route::get('/subscription', [PosSyncApiController::class, 'subscription']);
        Route::get('/subscription/features', [PosSyncApiController::class, 'subscriptionFeatures']);
        Route::get('/subscription/entitlements', [PosSyncApiController::class, 'subscriptionEntitlements']);
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
        Route::get('/settings/payment-methods/{id}/edit-sheet', [SettingsApiController::class, 'paymentMethodsEditSheet'])->middleware('tenant.api.permission:settings,view');
        Route::match(['put', 'post'], '/settings/payment-methods/{id}', [SettingsApiController::class, 'paymentMethodsUpdate'])->middleware('tenant.api.permission:settings,edit');
        Route::delete('/settings/payment-methods/{id}', [SettingsApiController::class, 'paymentMethodsDestroy'])->middleware('tenant.api.permission:settings,edit');
        Route::post('/settings/payment-methods/{id}/delete', [SettingsApiController::class, 'paymentMethodsDestroy'])->middleware('tenant.api.permission:settings,edit');
        Route::post('/settings/payment-methods/{id}/toggle', [SettingsApiController::class, 'paymentMethodsToggle'])->middleware('tenant.api.permission:settings,edit');
        Route::get('/settings/payment-methods/{id}/transactions', [SettingsApiController::class, 'paymentMethodTransactions'])->middleware('tenant.api.permission:settings,view');
        Route::get('/settings/notifications-audio', [TenantAppPreferencesController::class, 'getNotificationAlertsScreen'])->middleware('tenant.api.permission:settings,view');
        Route::match(['post', 'put'], '/settings/notifications-audio', [TenantAppPreferencesController::class, 'saveNotificationPreferences'])->middleware('tenant.api.permission:settings,edit');
        Route::get('/settings/app-preferences/notifications', [TenantAppPreferencesController::class, 'getNotificationAlertsScreen'])->middleware('tenant.api.permission:settings,view');
        Route::match(['post', 'put'], '/settings/app-preferences/notifications', [TenantAppPreferencesController::class, 'saveNotificationPreferences'])->middleware('tenant.api.permission:settings,edit');
        Route::get('/settings/app-preferences', [TenantAppPreferencesController::class, 'getNotificationAlertsScreen'])->middleware('tenant.api.permission:settings,view');
        Route::match(['post', 'put'], '/settings/app-preferences', [TenantAppPreferencesController::class, 'saveNotificationPreferences'])->middleware('tenant.api.permission:settings,edit');
        Route::post('/settings/app-preferences/notifications/upload-audio', [TenantAppPreferencesController::class, 'uploadAudio'])->middleware('tenant.api.permission:settings,edit');
        Route::get('/storefront/banner-auth', [StorefrontSettingsController::class, 'getBannerAuth'])->middleware(['entitled:ecommerce_storefront', 'tenant.api.permission:storefront,view']);
        Route::match(['post', 'put'], '/storefront/banner-auth', [StorefrontSettingsController::class, 'updateBannerAuth'])->middleware(['entitled:ecommerce_storefront', 'tenant.api.permission:storefront,edit']);
        Route::get('/storefront/payment-gateways', [StorefrontSettingsController::class, 'getPaymentGateways'])->middleware(['entitled:ecommerce_storefront', 'tenant.api.permission:gateways,view']);
        Route::match(['post', 'put'], '/storefront/payment-gateways', [StorefrontSettingsController::class, 'updatePaymentGateways'])->middleware(['entitled:ecommerce_storefront', 'tenant.api.permission:gateways,edit']);
        Route::get('/storefront/domain-config', [StorefrontSettingsController::class, 'getDomainConfig'])->middleware(['entitled:ecommerce_storefront', 'tenant.api.permission:storefront,view']);
        Route::match(['post', 'put'], '/storefront/domain-config', [StorefrontSettingsController::class, 'updateDomainConfig'])->middleware(['entitled:ecommerce_storefront', 'tenant.api.permission:storefront,edit']);
        Route::get('/storefront/inquiries', [StoreInquiryController::class, 'index'])->middleware(['entitled:ecommerce_storefront', 'tenant.api.permission:storefront,inquiries.view']);
        Route::match(['post', 'put'], '/storefront/inquiries/{id}/status', [StoreInquiryController::class, 'updateStatus'])->middleware(['entitled:ecommerce_storefront', 'tenant.api.permission:storefront,inquiries.action']);
        Route::delete('/storefront/inquiries/{id}', [StoreInquiryController::class, 'destroy'])->middleware(['entitled:ecommerce_storefront', 'tenant.api.permission:storefront,inquiries.action']);
        Route::post('/storefront/inquiries/{id}/delete', [StoreInquiryController::class, 'destroy'])->middleware(['entitled:ecommerce_storefront', 'tenant.api.permission:storefront,inquiries.action']);
        Route::get('/coupons', [CouponApiController::class, 'index'])->middleware('tenant.api.permission:settings,view');
        Route::post('/coupons', [CouponApiController::class, 'store'])->middleware('tenant.api.permission:settings,edit');
        Route::get('/coupons/{id}', [CouponApiController::class, 'show'])->middleware('tenant.api.permission:settings,view');
        Route::match(['put', 'post'], '/coupons/{id}', [CouponApiController::class, 'update'])->middleware('tenant.api.permission:settings,edit');
        Route::delete('/coupons/{id}', [CouponApiController::class, 'destroy'])->middleware('tenant.api.permission:settings,edit');
        Route::post('/coupons/{id}/delete', [CouponApiController::class, 'destroy'])->middleware('tenant.api.permission:settings,edit');
        Route::get('/faqs', [FaqApiController::class, 'index'])->middleware('tenant.api.permission:settings,view');
        Route::post('/faqs', [FaqApiController::class, 'store'])->middleware('tenant.api.permission:settings,edit');
        Route::get('/faqs/{id}', [FaqApiController::class, 'show'])->middleware('tenant.api.permission:settings,view');
        Route::match(['put', 'post'], '/faqs/{id}', [FaqApiController::class, 'update'])->middleware('tenant.api.permission:settings,edit');
        Route::delete('/faqs/{id}', [FaqApiController::class, 'destroy'])->middleware('tenant.api.permission:settings,edit');
        Route::post('/faqs/{id}/delete', [FaqApiController::class, 'destroy'])->middleware('tenant.api.permission:settings,edit');
        Route::get('/storefront/reviews', [\App\Http\Controllers\Tenant\StorefrontReviewController::class, 'tenantIndex'])->middleware(['entitled:ecommerce_storefront', 'tenant.api.permission:reviews,view']);
        Route::post('/storefront/reviews', [\App\Http\Controllers\Tenant\StorefrontReviewController::class, 'tenantStore'])->middleware(['entitled:ecommerce_storefront', 'tenant.api.permission:reviews,create']);
        Route::match(['post', 'put'], '/storefront/reviews/{id}/toggle-approval', [\App\Http\Controllers\Tenant\StorefrontReviewController::class, 'toggleApproval'])->middleware(['entitled:ecommerce_storefront', 'tenant.api.permission:reviews,edit']);
        Route::delete('/storefront/reviews/{id}', [\App\Http\Controllers\Tenant\StorefrontReviewController::class, 'tenantDestroy'])->middleware(['entitled:ecommerce_storefront', 'tenant.api.permission:reviews,delete']);
        Route::post('/storefront/reviews/{id}/delete', [\App\Http\Controllers\Tenant\StorefrontReviewController::class, 'tenantDestroy'])->middleware(['entitled:ecommerce_storefront', 'tenant.api.permission:reviews,delete']);
        Route::match(['post', 'put'], '/storefront/reviews/settings', [\App\Http\Controllers\Tenant\StorefrontReviewController::class, 'updateSettings'])->middleware(['entitled:ecommerce_storefront', 'tenant.api.permission:settings,edit']);

        // Storefront CMS Pages & Menus
        Route::get('/storefront/pages', [\App\Http\Controllers\Tenant\StorefrontMenuController::class, 'pagesIndex'])->middleware(['entitled:ecommerce_storefront', 'tenant.api.permission:storefront,menus.manage']);
        Route::post('/storefront/pages', [\App\Http\Controllers\Tenant\StorefrontMenuController::class, 'pagesStore'])->middleware(['entitled:ecommerce_storefront', 'tenant.api.permission:storefront,menus.manage']);
        Route::get('/storefront/pages/{id}', [\App\Http\Controllers\Tenant\StorefrontMenuController::class, 'pagesShow'])->middleware(['entitled:ecommerce_storefront', 'tenant.api.permission:storefront,menus.manage']);
        Route::match(['put', 'post'], '/storefront/pages/{id}', [\App\Http\Controllers\Tenant\StorefrontMenuController::class, 'pagesUpdate'])->middleware(['entitled:ecommerce_storefront', 'tenant.api.permission:storefront,menus.manage']);
        Route::delete('/storefront/pages/{id}', [\App\Http\Controllers\Tenant\StorefrontMenuController::class, 'pagesDestroy'])->middleware(['entitled:ecommerce_storefront', 'tenant.api.permission:storefront,menus.manage']);
        Route::post('/storefront/pages/{id}/delete', [\App\Http\Controllers\Tenant\StorefrontMenuController::class, 'pagesDestroy'])->middleware(['entitled:ecommerce_storefront', 'tenant.api.permission:storefront,menus.manage']);

        Route::get('/storefront/menus', [\App\Http\Controllers\Tenant\StorefrontMenuController::class, 'menusIndex'])->middleware(['entitled:ecommerce_storefront', 'tenant.api.permission:storefront,menus.manage']);
        Route::post('/storefront/menus', [\App\Http\Controllers\Tenant\StorefrontMenuController::class, 'menusStore'])->middleware(['entitled:ecommerce_storefront', 'tenant.api.permission:storefront,menus.manage']);
        Route::post('/storefront/menus/reorder', [\App\Http\Controllers\Tenant\StorefrontMenuController::class, 'reorder'])->middleware(['entitled:ecommerce_storefront', 'tenant.api.permission:storefront,menus.manage']);
        Route::match(['put', 'post'], '/storefront/menus/{id}', [\App\Http\Controllers\Tenant\StorefrontMenuController::class, 'menusUpdate'])->whereNumber('id')->middleware(['entitled:ecommerce_storefront', 'tenant.api.permission:storefront,menus.manage']);
        Route::match(['put', 'post'], '/storefront/menus/{id}/toggle-visibility', [\App\Http\Controllers\Tenant\StorefrontMenuController::class, 'toggleVisibility'])->whereNumber('id')->middleware(['entitled:ecommerce_storefront', 'tenant.api.permission:storefront,menus.manage']);
        Route::delete('/storefront/menus/{id}', [\App\Http\Controllers\Tenant\StorefrontMenuController::class, 'menusDestroy'])->whereNumber('id')->middleware(['entitled:ecommerce_storefront', 'tenant.api.permission:storefront,menus.manage']);
        Route::post('/storefront/menus/{id}/delete', [\App\Http\Controllers\Tenant\StorefrontMenuController::class, 'menusDestroy'])->whereNumber('id')->middleware(['entitled:ecommerce_storefront', 'tenant.api.permission:storefront,menus.manage']);

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
        Route::get('/restaurant/tables/{id}/actions-sheet', [RestaurantApiController::class, 'tableActionsSheet'])->middleware('tenant.api.permission:pos,view');
        Route::get('/tables/{id}/actions-sheet', [RestaurantApiController::class, 'tableActionsSheet'])->middleware('tenant.api.permission:pos,view');
        Route::get('/tenant/tables/{id}/actions-sheet', [RestaurantApiController::class, 'tableActionsSheet'])->middleware('tenant.api.permission:pos,view');
        Route::put('/restaurant/tables/{id}', [RestaurantApiController::class, 'tablesUpdate'])->middleware('tenant.api.permission:pos,edit');
        Route::post('/restaurant/tables/{id}/status', [RestaurantApiController::class, 'tablesSetStatus'])->middleware('tenant.api.permission:pos,edit');
        Route::delete('/restaurant/tables/{id}', [RestaurantApiController::class, 'tablesDestroy'])->middleware('tenant.api.permission:pos,edit');

        Route::post('/restaurant/orders/send-to-kitchen', [RestaurantApiController::class, 'sendToKitchen'])->middleware('tenant.api.permission:pos,create');
        Route::post('/restaurant/orders/{saleId}/settle', [RestaurantApiController::class, 'settle'])->middleware('tenant.api.permission:pos,create');

        Route::get('/restaurant/kot', [RestaurantApiController::class, 'kotIndex'])->middleware('tenant.api.permission:pos,view');
        Route::post('/restaurant/kot/{id}/status', [RestaurantApiController::class, 'kotUpdateStatus'])->middleware('tenant.api.permission:pos,edit');
        Route::post('/restaurant/kot/{id}/dismiss-alarm', [RestaurantApiController::class, 'kotDismissAlarm'])->middleware('tenant.api.permission:pos,edit');
        Route::post('/restaurant/kot/{id}/print', [RestaurantApiController::class, 'kotPrint'])->middleware('tenant.api.permission:pos,view');
        Route::post('/kot/{id}/print', [RestaurantApiController::class, 'kotPrint'])->middleware('tenant.api.permission:pos,view');

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
        Route::get('/tickets/{id}/share-sheet', [RepairApiController::class, 'ticketsShareDispatchSheet'])->middleware('tenant.api.permission:repair,view');
        Route::post('/tickets/{id}/dispatch', [RepairApiController::class, 'ticketsDispatch'])->middleware('tenant.api.permission:repair,view');
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
        Route::get('/roles/schema', [RolePermissionController::class, 'getPermissionsSchema'])->middleware('tenant.api.permission:users,view');
        Route::get('/permissions', [RolePermissionController::class, 'getPermissionsSchema'])->middleware('tenant.api.permission:users,view');
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

Route::middleware(['auth:sanctum', 'tenant'])->group(function () {
    Route::post('/tenant/dispatch/send', [UnifiedDispatchController::class, 'dispatch']);
    Route::post('/v1/tenant/dispatch/send', [UnifiedDispatchController::class, 'dispatch']);
    Route::post('/tenant/dispatch/batch', [UnifiedDispatchController::class, 'batchDispatch']);
    Route::post('/v1/tenant/dispatch/batch', [UnifiedDispatchController::class, 'batchDispatch']);
    Route::post('/tenant/dispatch/batch-send', [UnifiedDispatchController::class, 'batchDispatch']);
    Route::post('/v1/tenant/dispatch/batch-send', [UnifiedDispatchController::class, 'batchDispatch']);

    Route::post('/tenant/settings/sms-gateway', [TenantSettingsController::class, 'saveSmsCredentials']);
    Route::post('/v1/tenant/settings/sms-gateway', [TenantSettingsController::class, 'saveSmsCredentials']);

    Route::post('/tenant/settings/sms-gateway/test', [TenantSettingsController::class, 'sendTestSms']);
    Route::post('/v1/tenant/settings/sms-gateway/test', [TenantSettingsController::class, 'sendTestSms']);
});

Route::match(['post', 'put'], '/superadmin/tenants/{tenantId}/modules', [SuperAdminTenantController::class, 'updateModules']);
Route::match(['post', 'put'], '/v1/superadmin/tenants/{tenantId}/modules', [SuperAdminTenantController::class, 'updateModules']);
