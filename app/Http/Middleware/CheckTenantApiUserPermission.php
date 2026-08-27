<?php

namespace App\Http\Middleware;

use App\Services\Auth\PermissionChecker;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckTenantApiUserPermission
{
    public function __construct(private readonly PermissionChecker $permissions) {}

    public function handle(Request $request, Closure $next, string $module, string $action = 'view'): Response
    {
        // Integration API keys may be company-scoped. Desktop credentials are
        // user-bound and must obey exactly the same matrix as the web UI.
        $user = auth()->user();
        if ($user && ! $this->permissions->allows($user, $module, $action)) {
            return response()->json([
                'success' => false,
                'error' => 'Forbidden',
                'message' => "Your role cannot {$action} {$module}.",
            ], 403);
        }

        return $next($request);
    }
}
