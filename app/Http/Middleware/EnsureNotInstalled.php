<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNotInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (file_exists(storage_path('installed')) || file_exists(storage_path('--installed'))) {
            abort(403, 'This application is already installed.');
        }

        return $next($request);
    }
}
