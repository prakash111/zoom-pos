<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ClearStoreContext
{
    public function handle(Request $request, Closure $next): Response
    {
        foreach (['tenant.store_id', 'tenant.store_is_primary'] as $key) {
            if (app()->bound($key)) {
                app()->forgetInstance($key);
            }
        }

        return $next($request);
    }
}
