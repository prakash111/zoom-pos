<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class UpdateUserPresence
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user() ?? $request->user();

        if ($user) {
            // Cache user heartbeat for 2 minutes
            Cache::put('user-is-online-' . $user->id, true, now()->addMinutes(2));

            // Throttle database update to once every 2 minutes
            if (!$user->last_seen_at || $user->last_seen_at->diffInMinutes(now()) >= 2) {
                $user->updateQuietly(['last_seen_at' => now()]);
            }
        }

        return $next($request);
    }
}
