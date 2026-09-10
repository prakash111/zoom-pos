<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Locks a Demo-Mode tenant out of sensitive writes — password changes, file
 * uploads and settings modifications — while leaving normal exploration
 * (browsing, POS sales, adding a product) untouched.
 *
 * Only active when `config('app.demo_mode')` is true AND the authenticated
 * user (or their company) carries `is_demo`.
 */
class PreventDemoModifications
{
    public const MESSAGE = 'Action Disabled: Settings modification, file uploads, and password changes are locked in Demo Mode.';

    /**
     * Path globs (matched against the leading-slash request path) that a demo
     * account may never mutate.
     */
    private const BLOCKED_GLOBS = [
        // Credentials
        '*password*', '*change-password*', '*reset-password*', '*credentials*',
        // Uploads / media
        '*/upload', '*/uploads', '*/uploads/*', '*/media', '*/media/*',
        '*/logo', '*/logo/*', '*/favicon', '*/favicon/*',
        '*/drawer-cover', '*/drawer-cover/*', '*/qr-image', '*/qr-image/*',
        '*/image', '*/images', '*/images/*', '*/photo', '*/avatar', '*/banner',
        // Settings / configuration
        '*tenant/settings*', '*app/settings*', '*/settings/*',
        '*store-profile*', '*/branding', '*/branding/*',
        '*tax-rules*', '*/taxes', '*payment-methods*',
        '*api-integrations*', '*/integrations*', '*/webhook*',
        '*nav-config*', '*navigation-labels*', '*navigation-menu*',
        '*form-labels*', '*form-field*', '*printer*',
        '*/mode', '*app/mode*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('app.demo_mode')
            || in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)
            || ! $this->actorIsDemo($request)) {
            return $next($request);
        }

        if ($this->isSensitive($request)) {
            return response()->json([
                'status' => 'error',
                'message' => self::MESSAGE,
            ], 403);
        }

        return $next($request);
    }

    private function actorIsDemo(Request $request): bool
    {
        $user = $request->user() ?? auth()->user();
        if ($user && (bool) ($user->is_demo ?? false)) {
            return true;
        }

        $companyId = $request->attributes->get('company_id') ?? $user?->company_id;
        if ($companyId
            && (bool) Company::withoutGlobalScopes()->whereKey($companyId)->value('is_demo')) {
            return true;
        }

        // Unauthenticated flows (forgot / reset password): the demo actor is
        // whoever the request names by e-mail.
        $email = trim((string) $request->input('email'));
        if ($email !== '') {
            return (bool) User::withoutGlobalScopes()
                ->where('email', $email)->value('is_demo');
        }

        return false;
    }

    private function isSensitive(Request $request): bool
    {
        // Any multipart body / uploaded file from a demo account is blocked.
        if (str_contains(strtolower((string) $request->header('Content-Type')), 'multipart/form-data')) {
            return true;
        }
        if (count($request->allFiles()) > 0) {
            return true;
        }

        $path = '/'.ltrim($request->path(), '/');
        foreach (self::BLOCKED_GLOBS as $glob) {
            if (Str::is($glob, $path)) {
                return true;
            }
        }

        return false;
    }
}
