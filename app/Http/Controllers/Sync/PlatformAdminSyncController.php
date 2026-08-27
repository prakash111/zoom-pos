<?php

namespace App\Http\Controllers\Sync;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Mirrors the legacy api/platform_admin.php action dispatch at the same URL
 * (/api/platform_admin.php). The Super Admin panel itself is built directly
 * against Eloquent in Milestone 2 (Blade/Livewire, session-guard) rather than
 * this endpoint; this route only exists to keep the legacy URL/action
 * contract available for any tooling that still calls it directly.
 */
class PlatformAdminSyncController extends Controller
{
    public function dispatch(Request $request)
    {
        $action = $request->input('action', 'status');

        return response()->json([
            'success' => false,
            'error' => "Not yet implemented in Laravel rebuild: {$action}",
        ], 501);
    }
}
