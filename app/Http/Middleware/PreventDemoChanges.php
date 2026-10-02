<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class PreventDemoChanges
{
    public const MESSAGE = 'Action disabled: Modifications are restricted in demo mode.';

    /**
     * Safe Livewire navigation and view-state methods that do not mutate settings or credentials.
     */
    private const SAFE_LIVEWIRE_METHODS = [
        'gotopage', 'nextpage', 'previouspage', 'setpage', 'resetpage',
        'sortby', 'sort', 'togglesort', 'orderby',
        '$refresh', 'render', 'mount', '$set',
        'search', 'clearsearch', 'resetsearch',
        'filter', 'clearfilters', 'resetfilters', 'applyfilters',
        'setactivetab', 'switchtab', 'selecttab', 'settab', 'changetab', 'tab',
        'openmodal', 'closemodal', 'showmodal', 'hidemodal',
        'openaddpaymentmethodmodal', 'openeditpaymentmethodmodal', 'closepaymentmethodmodal',
        'openaddtaxrulemodal', 'openedittaxrulemodal', 'closetaxrulemodal',
        'opencreatekeymodal', 'closecreatekeymodal',
        'open', 'close', 'cancel', 'dismiss', 'back',
        'loadmore', 'refresh', 'reload', 'select', 'expand', 'collapse',
    ];

    /**
     * Protected path patterns where mutations (POST, PUT, PATCH, DELETE) are forbidden in demo mode.
     */
    private const BLOCKED_PATTERNS = [
        // Password updates and authentication credentials
        '*password*',
        '*change-password*',
        '*reset-password*',
        '*update-password*',
        '*credentials*',

        // Tenant settings and configuration
        '*tenant/settings*',
        '*/settings/*',
        '*app/settings*',
        '*settings/stores*',
        '*settings/templates*',
        '*settings/navigation-menu*',

        // Store profile & branding
        '*store-profile*',
        '*/store-profile/*',
        '*/branding*',
        '*/branding/*',

        // Store branches mutation (create/edit/delete)
        '*stores/create*',
        '*stores/update*',
        '*stores/delete*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('app.demo_mode') || in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        // Exempt store switching (session/context navigation, not a data mutation)
        if ($this->isStoreSwitchRequest($request)) {
            return $next($request);
        }

        if ($this->isProtectedTarget($request)) {
            return $this->deny($request);
        }

        return $next($request);
    }

    private function isStoreSwitchRequest(Request $request): bool
    {
        $path = ltrim($request->path(), '/');

        return Str::is(['*settings/stores/*/switch', '*stores/*/switch', '*switch-store*'], $path);
    }

    private function isProtectedTarget(Request $request): bool
    {
        $path = '/'.ltrim($request->path(), '/');

        foreach (self::BLOCKED_PATTERNS as $pattern) {
            if (Str::is($pattern, $path)) {
                return true;
            }
        }

        // Intercept Livewire updates targeting Settings or Profile components
        if (Str::startsWith(ltrim($path, '/'), 'livewire/update')) {
            foreach ((array) $request->input('components', []) as $component) {
                $snapshot = $component['snapshot'] ?? null;
                $decoded = is_string($snapshot) ? json_decode($snapshot, true) : $snapshot;
                $name = strtolower((string) data_get($decoded, 'memo.name', ''));

                if (Str::contains($name, ['tenant.settings', 'settings', 'profile'])) {
                    foreach ((array) ($component['calls'] ?? []) as $call) {
                        $method = strtolower(trim((string) ($call['method'] ?? '')));
                        if ($method !== '' && ! in_array($method, self::SAFE_LIVEWIRE_METHODS, true)) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    private function deny(Request $request): Response
    {
        if ($request->expectsJson() || $request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => self::MESSAGE,
                'error' => self::MESSAGE,
            ], 403);
        }

        return back()->with('error', self::MESSAGE);
    }
}
