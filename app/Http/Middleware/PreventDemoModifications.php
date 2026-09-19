<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Two demo-mode locks, both gated on `config('app.demo_mode')`:
 *
 *  1. Tenant scope — a demo tenant/user (`is_demo`) can't change passwords,
 *     upload files or touch settings, but can still explore (POS sales, add a
 *     product…).
 *  2. Super Admin scope — the ENTIRE platform panel is read-only: no settings
 *     saves (general, localization, currency, SMTP, gateways, branding,
 *     maintenance, backups) and no tenant create/edit/suspend/delete. Applies
 *     to every non-safe HTTP method and to Livewire actions on
 *     `SuperAdmin\*` components; only login/logout are exempt.
 */
class PreventDemoModifications
{
    public const MESSAGE = 'Action Disabled: Settings modification, file uploads, and password changes are locked in Demo Mode.';

    /** Flash error for a blocked Super Admin web request. */
    public const SUPERADMIN_FLASH = 'Demo Mode Active: Platform settings and tenant data are read-only to prevent sandbox disruptions.';

    /** JSON message for a blocked Super Admin API request. */
    public const SUPERADMIN_API_MESSAGE = 'Demo Mode Active: Platform settings are locked in read-only mode.';

    /**
     * Livewire action methods that only change view state (pagination, search,
     * sort, tab, modal visibility). Everything else on a Super Admin component
     * is treated as a mutation and blocked.
     */
    private const SAFE_LIVEWIRE_METHODS = [
        'gotopage', 'nextpage', 'previouspage', 'setpage', 'resetpage',
        'sortby', 'sort', 'togglesort', 'orderby',
        '$refresh', 'render', 'mount', '$set',
        'search', 'clearsearch', 'resetsearch',
        'filter', 'clearfilters', 'resetfilters', 'applyfilters',
        'setactivetab', 'switchtab', 'selecttab', 'settab', 'changetab', 'tab',
        'openmodal', 'closemodal', 'showmodal', 'hidemodal',
        'open', 'close', 'cancel', 'dismiss', 'back',
        'loadmore', 'refresh', 'reload', 'select', 'expand', 'collapse',
    ];

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
            || in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        // 2. Super Admin panel — whole platform read-only, any signed-in admin.
        if ($this->targetsSuperAdmin($request) && $this->isSuperAdminMutation($request)) {
            return $this->denySuperAdmin($request);
        }

        // 1. Tenant scope — only a demo tenant/user is restricted.
        if ($this->actorIsDemo($request) && $this->isSensitive($request)) {
            return response()->json([
                'status' => 'error',
                'message' => self::MESSAGE,
            ], 403);
        }

        return $next($request);
    }

    // ---- Super Admin read-only lock -------------------------------------

    private function targetsSuperAdmin(Request $request): bool
    {
        $path = ltrim($request->path(), '/');

        if (Str::startsWith($path, ['superadmin', 'api/superadmin', 'api/v1/superadmin'])) {
            return true;
        }

        // Livewire follow-up request carrying a SuperAdmin\* component.
        if ($path === 'livewire/update') {
            foreach ($this->superAdminComponents($request) as $ignored) {
                return true;
            }
        }

        return false;
    }

    private function isSuperAdminMutation(Request $request): bool
    {
        $path = ltrim($request->path(), '/');

        // A plain non-safe HTTP route under /superadmin — anything but auth.
        if (Str::startsWith($path, ['superadmin', 'api/superadmin', 'api/v1/superadmin'])) {
            return ! Str::is(['*/login', '*/logout', '*/login/*'], '/'.$path);
        }

        // Livewire: block any action that isn't pure view-state navigation, and
        // any property write on a Super Admin component.
        foreach ($this->superAdminComponents($request) as $component) {
            foreach ((array) ($component['calls'] ?? []) as $call) {
                $method = strtolower(trim((string) ($call['method'] ?? '')));
                if ($method !== '' && ! in_array($method, self::SAFE_LIVEWIRE_METHODS, true)) {
                    return true;
                }
            }
            if (! empty($component['updates']) && is_array($component['updates'])
                && ! $this->onlyViewStateUpdates($component['updates'])) {
                return true;
            }
        }

        return false;
    }

    /** @return \Generator<array{calls:mixed,updates:mixed}> */
    private function superAdminComponents(Request $request): \Generator
    {
        foreach ((array) $request->input('components', []) as $component) {
            $snapshot = $component['snapshot'] ?? null;
            $decoded = is_string($snapshot) ? json_decode($snapshot, true) : $snapshot;
            $name = strtolower((string) data_get($decoded, 'memo.name', ''));
            if ($name !== '' && Str::contains($name, ['super-admin', 'superadmin', 'super_admin'])) {
                yield [
                    'calls' => $component['calls'] ?? [],
                    'updates' => $component['updates'] ?? [],
                ];
            }
        }
    }

    /** Search / pagination / sort / tab writes are fine; data writes are not. */
    private function onlyViewStateUpdates(array $updates): bool
    {
        foreach (array_keys($updates) as $key) {
            $k = strtolower((string) $key);
            if (! Str::is(
                ['search', 'q', 'query', 'keyword', 'term', 'filter', 'filters',
                    'page', 'perpage', 'per_page', 'pagesize', 'sort', 'sortfield',
                    'sortby', 'sortdirection', 'sortdir', 'direction', 'orderby',
                    'tab', 'activetab', 'active_tab', 'currenttab', 'status',
                    'showmodal', 'show_modal', 'modal', 'expanded', 'selected',
                    '*_search', '*filter*', '*tab*'],
                $k
            )) {
                return false;
            }
        }

        return true;
    }

    private function denySuperAdmin(Request $request): Response
    {
        if ($request->is('api/*')) {
            return response()->json([
                'status' => 'error',
                'message' => self::SUPERADMIN_API_MESSAGE,
            ], 403);
        }

        return back()->with('error', self::SUPERADMIN_FLASH);
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
