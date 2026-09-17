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
        'allmodules' => 'ALL_MODULES',
        'enterprise' => 'ALL_MODULES',
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

        $email = $key === 'ALL_MODULES'
            ? 'demo@zoomnearby.com'
            : (DemoAccountsSeeder::ACCOUNTS[$key]['email'] ?? null);
        $canonicalDemoEmails = [
            'RETAIL' => 'retail.demo@zoomnearby.com',
            'RESTAURANT' => 'restaurant.demo@zoomnearby.com',
            'PHARMACY' => 'pharmacy.demo@zoomnearby.com',
            'REPAIR_TECHNICIAN' => 'repairs.demo@zoomnearby.com',
            'SALON_BOOKINGS' => 'salon.demo@zoomnearby.com',
        ];
        $candidateEmails = array_values(array_unique(array_filter([
            $email,
            $key === 'ALL_MODULES' ? 'allmodules.demo@zoomnearby.com' : null,
            $canonicalDemoEmails[$key] ?? null,
        ])));

        // DemoEnvironmentResetSeeder creates the enterprise workspace under
        // the canonical demo email and also keeps an alias for older links.
        // Accept either account, and tolerate an older record that was created
        // before the is_demo flag was introduced when its company is clearly a
        // demo workspace. This prevents a false "run the seeder" error.
        $user = User::query()->withoutGlobalScopes()
            ->whereIn('email', $candidateEmails)
            ->where(function ($query): void {
                $query->where('is_demo', true)
                    ->orWhereHas('company', function ($companyQuery): void {
                        $companyQuery->where('is_demo', true);
                    });
            })
            ->first();

        if ($user && ! $user->is_demo) {
            $user->forceFill(['is_demo' => true])->save();
        }

        abort_if($user === null, 404, 'Demo account not provisioned — run `php artisan db:seed --class=DemoAccountsSeeder`.');

        Auth::guard('web')->login($user);
        app()->instance('tenant.company_id', $user->company_id);
        $request->session()->regenerate();

        return redirect('/tenant');
    }
}
