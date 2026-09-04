<?php

use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PublicPageController;
use App\Http\Middleware\EnsureAppIsInstalled;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

Route::middleware(EnsureAppIsInstalled::class)->get('/', [LandingPageController::class, 'index'])->name('home');

Route::middleware(EnsureAppIsInstalled::class)->get('/login', function () {
    return redirect()->route('tenant.login');
})->name('login');

Route::middleware(EnsureAppIsInstalled::class)->get('/register', function () {
    return redirect()->route('tenant.register');
})->name('register');

Route::middleware(EnsureAppIsInstalled::class)->get('/page/{page:slug}', [PublicPageController::class, 'show'])->name('page.show');
Route::middleware(EnsureAppIsInstalled::class)->get('/pages/{page:slug}', [PublicPageController::class, 'show'])->name('pages.show');

Route::get('/locale/{locale}', [LocaleController::class, 'switch'])->name('locale.switch');
Route::middleware(EnsureAppIsInstalled::class)->group(function () {
    Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])->name('social.redirect');
    Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])->name('social.callback');
});

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
