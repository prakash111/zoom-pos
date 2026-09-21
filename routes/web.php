<?php

use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\DemoLoginController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PublicContactController;
use App\Http\Controllers\PublicPageController;
use App\Http\Controllers\Webhooks\SubscriptionWebhookController;
use App\Http\Middleware\EnsureAppIsInstalled;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

Route::middleware(EnsureAppIsInstalled::class)->get('/', [LandingPageController::class, 'index'])->name('home');
Route::middleware(EnsureAppIsInstalled::class)->get('/home', [LandingPageController::class, 'index'])->name('landing.home');
Route::middleware(EnsureAppIsInstalled::class)->get('/store', [\App\Http\Controllers\Tenant\StorefrontController::class, 'index'])->name('tenant.store');
Route::middleware(EnsureAppIsInstalled::class)->post('/store/order', [\App\Http\Controllers\Tenant\StorefrontController::class, 'placeOrder'])->name('tenant.store.order');
Route::middleware(EnsureAppIsInstalled::class)->get('/store/account', [\App\Http\Controllers\Tenant\StorefrontController::class, 'account'])->name('tenant.store.account');
Route::middleware(EnsureAppIsInstalled::class)->get('/store/track/{code}', [\App\Http\Controllers\Tenant\StorefrontController::class, 'trackOrder'])->name('tenant.store.track');
Route::middleware(EnsureAppIsInstalled::class)->get('/store/payment-methods', [\App\Http\Controllers\Tenant\StorefrontController::class, 'paymentMethods'])->name('tenant.store.payment_methods');
Route::middleware(EnsureAppIsInstalled::class)->post('/store/coupons/validate', [\App\Http\Controllers\Tenant\StorefrontController::class, 'validateCoupon'])->name('tenant.store.coupon.validate');
Route::middleware(EnsureAppIsInstalled::class)->get('/store/faqs', [\App\Http\Controllers\Tenant\StorefrontController::class, 'faqsPage'])->name('tenant.store.faqs');
Route::middleware(EnsureAppIsInstalled::class)->get('/store/api/faqs', [\App\Http\Controllers\Tenant\StorefrontController::class, 'apiFaqs'])->name('tenant.store.api.faqs');
Route::middleware(EnsureAppIsInstalled::class)->post('/store/auth/send-verification', [\App\Http\Controllers\Tenant\StorefrontController::class, 'sendVerification'])->name('tenant.store.auth.send_verification');
Route::middleware(EnsureAppIsInstalled::class)->post('/store/auth/verify-code', [\App\Http\Controllers\Tenant\StorefrontController::class, 'verifyCode'])->name('tenant.store.auth.verify_code');

// Storefront Online Payment Gateway Handlers (Razorpay / Stripe)
Route::middleware(EnsureAppIsInstalled::class)->post('/store/payment/initiate', [\App\Http\Controllers\Tenant\StorefrontController::class, 'initiateGatewayPayment'])->name('tenant.store.payment.initiate');
Route::middleware(EnsureAppIsInstalled::class)->post('/store/payment/verify', [\App\Http\Controllers\Tenant\StorefrontController::class, 'verifyGatewayPayment'])->name('tenant.store.payment.verify');

// Storefront Product Ratings & Customer Reviews
Route::middleware(EnsureAppIsInstalled::class)->get('/store/products/{id}/reviews', [\App\Http\Controllers\Tenant\StorefrontReviewController::class, 'index'])->name('tenant.store.reviews.index');
Route::middleware(EnsureAppIsInstalled::class)->post('/store/products/{id}/reviews', [\App\Http\Controllers\Tenant\StorefrontReviewController::class, 'store'])->name('tenant.store.reviews.store');
Route::middleware(EnsureAppIsInstalled::class)->get('/store/customer/reviews', [\App\Http\Controllers\Tenant\StorefrontReviewController::class, 'customerReviews'])->name('tenant.store.customer.reviews');

// Storefront Public Inquiries
Route::middleware(EnsureAppIsInstalled::class)->post('/store/inquiry', [\App\Http\Controllers\Tenant\StoreInquiryController::class, 'submitPublicInquiry'])->name('tenant.store.inquiry');
Route::middleware(EnsureAppIsInstalled::class)->post('/storefront/inquiry', [\App\Http\Controllers\Tenant\StoreInquiryController::class, 'submitPublicInquiry']);
Route::middleware(EnsureAppIsInstalled::class)->post('/c/{slug}/inquiry', [\App\Http\Controllers\Tenant\StoreInquiryController::class, 'submitPublicInquiry']);

// Storefront Dynamic CMS Pages
Route::middleware(EnsureAppIsInstalled::class)->get('/store/page/{slug}', [\App\Http\Controllers\Tenant\StorefrontController::class, 'showCmsPage'])->name('tenant.store.page');

// Social Login Routes for Storefront
Route::get('/store/auth/{provider}/redirect', [\App\Http\Controllers\Tenant\StorefrontAuthController::class, 'redirectToProvider'])->name('tenant.store.auth.redirect');
Route::get('/store/auth/{provider}/callback', [\App\Http\Controllers\Tenant\StorefrontAuthController::class, 'handleProviderCallback'])->name('tenant.store.auth.callback');

Route::middleware(EnsureAppIsInstalled::class)->get('/login', function () {
    return redirect()->route('tenant.login');
})->name('login');

Route::middleware(EnsureAppIsInstalled::class)->post('/logout', function () {
    Auth::guard('web')->logout();

    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('tenant.login');
})->name('logout');

Route::middleware(EnsureAppIsInstalled::class)->get('/register', function () {
    return redirect()->route('tenant.register');
})->name('register');

// Demo branding assets are intentionally available at the tenant-prefixed
// URLs used by seeded workspaces. Keep the file name constrained so this
// cannot become an arbitrary storage-file endpoint.
$serveDemoBranding = function (string $asset) {
        abort_unless(preg_match('/^[A-Za-z0-9_.-]+$/', $asset) === 1, 404);

        $path = storage_path('app/public/demo-branding/'.$asset);
        abort_unless(is_file($path), 404);

        $contents = file_get_contents($path);
        // Older demo seeds stored the data URI itself. Serve its decoded SVG
        // bytes so the public branding URL is a valid image either way.
        if (is_string($contents) && str_starts_with($contents, 'data:image/svg+xml;base64,')) {
            $contents = base64_decode(substr($contents, strlen('data:image/svg+xml;base64,')), true) ?: $contents;
        }

        if (is_string($contents) && str_starts_with(ltrim($contents), '<svg')) {
            return response($contents, 200, [
                'Content-Type' => 'image/svg+xml',
                'Cache-Control' => 'public, max-age=86400',
                'Access-Control-Allow-Origin' => '*',
            ]);
        }

        return response()->file($path, [
            'Cache-Control' => 'public, max-age=86400',
            'Access-Control-Allow-Origin' => '*',
        ]);
};

foreach (['/tenant/demo-branding/{asset}', '/demo-branding/{asset}'] as $demoBrandingPath) {
    Route::middleware(EnsureAppIsInstalled::class)
        ->get($demoBrandingPath, $serveDemoBranding)
        ->where('asset', '[A-Za-z0-9_.-]+');
}

// Storage assets fallback with explicit permissive CORS headers
Route::options('/storage/{path}', function () {
    return response('', 204, [
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, OPTIONS',
        'Access-Control-Allow-Headers' => 'DNT,User-Agent,X-Requested-With,If-Modified-Since,Cache-Control,Content-Type,Range',
    ]);
})->where('path', '.*');

Route::get('/storage/{path}', function ($path) {
    $filePath = storage_path('app/public/'.$path);
    abort_unless(is_file($filePath), 404);

    return response()->file($filePath, [
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, OPTIONS',
        'Access-Control-Allow-Headers' => 'DNT,User-Agent,X-Requested-With,If-Modified-Since,Cache-Control,Content-Type,Range',
        'Cache-Control' => 'public, max-age=86400',
    ]);
})->where('path', '.*');

// 1-click demo sign-in (web panel). Disabled entirely unless DEMO_MODE=true.
if (config('app.demo_mode')) {
    Route::middleware(EnsureAppIsInstalled::class)
        ->get('/demo-login/{type}', [DemoLoginController::class, 'login'])
        ->name('demo.login');
}

Route::middleware(EnsureAppIsInstalled::class)->get('/page/{slug}', [PublicPageController::class, 'show'])->name('page.show');
Route::middleware(EnsureAppIsInstalled::class)->get('/pages/{slug}', [PublicPageController::class, 'show'])->name('pages.show');

Route::middleware(EnsureAppIsInstalled::class)
    ->get('/contact', [PublicContactController::class, 'index'])
    ->name('contact.index');

Route::middleware([EnsureAppIsInstalled::class, 'throttle:6,1'])
    ->post('/contact', [PublicContactController::class, 'store'])
    ->name('contact.store');

Route::get('/locale/{locale}', [LocaleController::class, 'switch'])->name('locale.switch');
Route::middleware(EnsureAppIsInstalled::class)->group(function () {
    Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])->name('social.redirect');
    Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])->name('social.callback');
});

// Browser return URL after a hosted subscription checkout (Razorpay / PayPal
// / Paystack / Flutterwave redirect flows). Shown, copyable, on the gateway
// card in Super Admin ▸ Payment Gateways.
Route::get('/subscription/payment/callback/{gateway}', [SubscriptionWebhookController::class, 'callback'])
    ->name('subscription.payment.callback');

Route::post('/webhooks/{gateway}', [SubscriptionWebhookController::class, 'handle'])
    ->name('webhooks.web.gateway');
Route::post('/v1/webhooks/{gateway}', [SubscriptionWebhookController::class, 'handle'])
    ->name('webhooks.web.v1.gateway');

Route::get('/pos-standalone', function () {
    return view('pos-standalone');
})->name('pos.standalone');

Route::get('/desktop/session/{token}', function (string $token) {
    abort_unless(preg_match('/^[A-Za-z0-9]{80}$/', $token) === 1, 404);
    $payload = Cache::pull('desktop-web-session:'.hash('sha256', $token));
    abort_unless(is_array($payload), 401, 'This desktop sign-in link expired or was already used.');

    $user = User::query()->withoutGlobalScope('company')->find($payload['user_id'] ?? null);
    abort_unless($user && $user->company_id === ($payload['company_id'] ?? null) && $user->status !== 'inactive', 401);

    Auth::guard('web')->login($user);
    request()->session()->regenerate();

    return redirect($payload['destination'] ?? route('tenant.dashboard'));
})->middleware(EnsureAppIsInstalled::class)->name('desktop.session');

Route::redirect('/admin/settings/general', '/superadmin/settings?tab=general');
Route::redirect('/admin/settings/regional', '/superadmin/settings?tab=general');

Route::get('/tenant/views/quotations/create', function (\Illuminate\Http\Request $request) {
    return app(\App\Http\Controllers\Api\QuotationController::class)->createSchema($request);
});
Route::get('/tenant/views/quotations/{id}', function (\Illuminate\Http\Request $request, $id) {
    return app(\App\Http\Controllers\Api\QuotationController::class)->showSchema($request, $id);
})->where('id', '^(?!create$).+');
Route::get('/tenant/quotations/{id}', function (\Illuminate\Http\Request $request, $id) {
    return app(\App\Http\Controllers\Api\QuotationController::class)->showSchema($request, $id);
})->where('id', '^(?!create$).+');
Route::get('/tenant/views/quotations/{id}/preview', function (\Illuminate\Http\Request $request, $id) {
    return app(\App\Http\Controllers\Api\QuotationController::class)->previewView($request, $id);
});
Route::get('/tenant/quotations/{id}/preview', function (\Illuminate\Http\Request $request, $id) {
    return app(\App\Http\Controllers\Api\QuotationController::class)->previewView($request, $id);
});
Route::get('/tenant/views/quotations/{id}/send', function (\Illuminate\Http\Request $request, $id) {
    return app(\App\Http\Controllers\Api\QuotationController::class)->sendView($request, $id);
});
Route::get('/tenant/quotations/{id}/send', function (\Illuminate\Http\Request $request, $id) {
    return app(\App\Http\Controllers\Api\QuotationController::class)->sendView($request, $id);
});
Route::get('/tenant/views/quotations/{id}/preview-sheet', function (\Illuminate\Http\Request $request, $id) {
    return app(\App\Http\Controllers\Api\QuotationController::class)->previewSheet($request, $id);
});
Route::get('/tenant/quotations/{id}/preview-sheet', function (\Illuminate\Http\Request $request, $id) {
    return app(\App\Http\Controllers\Api\QuotationController::class)->previewSheet($request, $id);
});
Route::get('/tenant/views/quotations/{id}/send-sheet', function (\Illuminate\Http\Request $request, $id) {
    return app(\App\Http\Controllers\Api\QuotationController::class)->sendSheet($request, $id);
});
Route::get('/tenant/quotations/{id}/send-sheet', function (\Illuminate\Http\Request $request, $id) {
    return app(\App\Http\Controllers\Api\QuotationController::class)->sendSheet($request, $id);
});
Route::get('/tenant/quotations/{id}/pdf', function (\Illuminate\Http\Request $request, $id) {
    return app(\App\Http\Controllers\Api\V1\QuotationApiController::class)->pdf($request, $id);
});
Route::get('/tenant/views/quotations/{id}/pdf', function (\Illuminate\Http\Request $request, $id) {
    return app(\App\Http\Controllers\Api\V1\QuotationApiController::class)->pdf($request, $id);
});
Route::post('/tenant/quotations/{id}/dispatch', function (\Illuminate\Http\Request $request, $id) {
    return app(\App\Http\Controllers\Api\QuotationController::class)->dispatchQuotation($request, $id);
});
Route::get('/tenant/views/invoices/create', function (\Illuminate\Http\Request $request) {
    return app(\App\Http\Controllers\Api\InvoiceController::class)->createSchema($request);
});
Route::get('/tenant/invoices/create', function (\Illuminate\Http\Request $request) {
    return app(\App\Http\Controllers\Api\InvoiceController::class)->createSchema($request);
});

Route::middleware([\App\Http\Middleware\AuthenticateTenantApi::class])->group(function () {
    Route::get('/tenant/storefront/domain-config', [\App\Http\Controllers\Api\Tenant\StorefrontSettingsController::class, 'getDomainConfig']);
    Route::match(['put', 'post'], '/tenant/storefront/domain-config', [\App\Http\Controllers\Api\Tenant\StorefrontSettingsController::class, 'updateDomainConfig']);
    Route::get('/tenant/storefront/inquiries', [\App\Http\Controllers\Tenant\StoreInquiryController::class, 'index']);
    Route::match(['put', 'post'], '/tenant/storefront/inquiries/{id}/status', [\App\Http\Controllers\Tenant\StoreInquiryController::class, 'updateStatus']);
    Route::match(['delete', 'post'], '/tenant/storefront/inquiries/{id}/delete', [\App\Http\Controllers\Tenant\StoreInquiryController::class, 'destroy']);
    Route::delete('/tenant/storefront/inquiries/{id}', [\App\Http\Controllers\Tenant\StoreInquiryController::class, 'destroy']);
    Route::get('/tenant/storefront/reviews', [\App\Http\Controllers\Tenant\StorefrontReviewController::class, 'tenantIndex']);
    Route::post('/tenant/storefront/reviews', [\App\Http\Controllers\Tenant\StorefrontReviewController::class, 'tenantStore']);
    Route::match(['post', 'put'], '/tenant/storefront/reviews/{id}/toggle-approval', [\App\Http\Controllers\Tenant\StorefrontReviewController::class, 'toggleApproval']);
    Route::delete('/tenant/storefront/reviews/{id}', [\App\Http\Controllers\Tenant\StorefrontReviewController::class, 'tenantDestroy']);
    Route::post('/tenant/storefront/reviews/{id}/delete', [\App\Http\Controllers\Tenant\StorefrontReviewController::class, 'tenantDestroy']);
    Route::match(['post', 'put'], '/tenant/storefront/reviews/settings', [\App\Http\Controllers\Tenant\StorefrontReviewController::class, 'updateSettings']);
});

