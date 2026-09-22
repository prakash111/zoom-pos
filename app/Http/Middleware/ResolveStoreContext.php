<?php

namespace App\Http\Middleware;

use App\Services\Stores\StoreContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveStoreContext
{
    public function handle(Request $request, Closure $next): Response
    {
        app(StoreContext::class)->bind(
            $request,
            $request->user(),
            $request->attributes->get('company_id') ?? (app()->bound('tenant.company_id') ? app('tenant.company_id') : null)
        );

        return $next($request);
    }
}
