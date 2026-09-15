<?php

namespace App\Http\Controllers;

use App\Models\User;
use Database\Seeders\DemoAccountsSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * 1-click demo sign-in for the Blade web panel. Only reachable while
 * `config('app.demo_mode')` is true; logs the request into the matching
 * `is_demo` tenant and drops them on the tenant dashboard.
 */
class DemoLoginController extends Controller
{
    /** URL slug (any of these) => the ACCOUNTS key. */
    private const SLUGS = [
        'retail' => 'RETAIL',
        'cafe' => 'RESTAURANT',
        'restaurant' => 'RESTAURANT',
        'pharmacy' => 'PHARMACY',
        'repair' => 'REPAIR_TECHNICIAN',
        'repair_technician' => 'REPAIR_TECHNICIAN',
        'salon' => 'SALON_BOOKINGS',
        'salon_bookings' => 'SALON_BOOKINGS',
    ];

    public function login(Request $request, string $type): RedirectResponse
    {
        abort_unless(config('app.demo_mode'), 404);

        $key = self::SLUGS[strtolower(str_replace('-', '_', $type))] ?? null;
        abort_if($key === null, 404, 'Unknown demo store type.');

        $email = DemoAccountsSeeder::ACCOUNTS[$key]['email'];
        $user = User::query()->withoutGlobalScopes()
            ->where('email', $email)
            ->where('is_demo', true)
            ->first();

        abort_if($user === null, 404, 'Demo account not provisioned — run `php artisan db:seed --class=DemoAccountsSeeder`.');

        Auth::guard('web')->login($user);
        app()->instance('tenant.company_id', $user->company_id);
        $request->session()->regenerate();

        return redirect('/tenant');
    }
}
