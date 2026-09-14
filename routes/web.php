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

// 1-click demo sign-in (web panel). Disabled entirely unless DEMO_MODE=true.
if (config('app.demo_mode')) {
    Route::middleware(EnsureAppIsInstalled::class)
        ->get('/demo-login/{type}', [DemoLoginController::class, 'login'])
        ->name('demo.login');
}

Route::middleware(EnsureAppIsInstalled::class)->get('/page/{page:slug}', [PublicPageController::class, 'show'])->name('page.show');
Route::middleware(EnsureAppIsInstalled::class)->get('/pages/{page:slug}', [PublicPageController::class, 'show'])->name('pages.show');

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

Route::get('/tenant/views/quotations/{id}', function (\Illuminate\Http\Request $request, $id) {
    return app(\App\Http\Controllers\Api\QuotationController::class)->showSchema($request, $id);
});
Route::get('/tenant/quotations/{id}', function (\Illuminate\Http\Request $request, $id) {
    return app(\App\Http\Controllers\Api\QuotationController::class)->showSchema($request, $id);
});
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

