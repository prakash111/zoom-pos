<?php

namespace App\Http\Controllers\Sync;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

/** Mirrors legacy api/health.php / api/health/index.php. */
class HealthController extends Controller
{
    public function index()
    {
        try {
            DB::select('select 1');
            $healthy = true;
        } catch (\Throwable) {
            $healthy = false;
        }

        return response()->json([
            'status' => $healthy ? 'ok' : 'error',
            'healthy' => $healthy,
            'version' => config('app.version', '1.0.0'),
        ]);
    }
}
