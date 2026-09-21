<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Tenant\StorefrontController;
use App\Models\Company;
use App\Models\Page;
use App\Models\PlatformBranding;
use App\Models\TenantCustomPage;
use Illuminate\Http\Request;

class PublicPageController extends Controller
{
    public function show(Request $request, string $slug)
    {
        $storefrontController = app(StorefrontController::class);
        $host = strtolower($request->getHost());
        $baseHost = parse_url(config('app.url'), PHP_URL_HOST);
        $isTenantHost = ($baseHost && str_ends_with($host, '.'.$baseHost) && $host !== $baseHost)
            || ($baseHost && $host !== $baseHost && $host !== 'localhost' && $host !== '127.0.0.1')
            || $request->filled('store') || $request->filled('company_id');

        // 1. If visiting a tenant host or tenant context is bound, resolve tenant custom CMS page
        if (app()->bound('tenant.company_id') || $isTenantHost) {
            $company = $storefrontController->resolveCompany($request);
            if ($company) {
                TenantCustomPage::seedDefaultsForCompany($company->id);
                $tenantPage = TenantCustomPage::withoutGlobalScopes()
                    ->where('company_id', $company->id)
                    ->where('slug', $slug)
                    ->where('is_published', true)
                    ->first();

                if ($tenantPage) {
                    return $storefrontController->showCmsPage($request, $slug);
                }
            }
        }

        // 2. Try platform marketing CMS page
        $page = Page::where('slug', $slug)->first();
        if ($page && $page->is_active) {
            return view('public.page', [
                'page' => $page,
                'branding' => PlatformBranding::current(),
                'footerPages' => Page::where('is_active', true)->where('show_in_footer', true)->orderBy('title')->get(),
            ]);
        }

        // 3. Fallback: check if tenant custom page exists for default store
        $company = $storefrontController->resolveCompany($request);
        if ($company) {
            TenantCustomPage::seedDefaultsForCompany($company->id);
            $tenantPage = TenantCustomPage::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('slug', $slug)
                ->where('is_published', true)
                ->first();

            if ($tenantPage) {
                return $storefrontController->showCmsPage($request, $slug);
            }
        }

        abort(404);
    }
}
