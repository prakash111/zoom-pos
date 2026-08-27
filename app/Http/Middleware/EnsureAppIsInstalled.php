<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAppIsInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! file_exists(storage_path('installed')) && ! $request->is('install*')) {
            return redirect('/install');
        }

        return $next($request);
    }
}
