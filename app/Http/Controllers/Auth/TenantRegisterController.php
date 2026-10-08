<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\RegisterTenantRequest;
use App\Services\Module\ModuleManagerService;
use App\Services\Tenancy\TenantProvisioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TenantRegisterController extends Controller
{
    /**
     * Show the tenant registration form with dynamically populated business verticals.
     */
    public function showRegistrationForm(ModuleManagerService $moduleService): View
    {
        $businessTypes = $moduleService->getAvailableBusinessTypes();

        return view('auth.tenant-register', [
            'businessTypes' => $businessTypes,
        ]);
    }

    /**
     * Handle incoming tenant registration request.
     */
    public function register(
        RegisterTenantRequest $request,
        TenantProvisioningService $provisioner,
        ModuleManagerService $moduleService
    ): RedirectResponse|JsonResponse {
        $validated = $request->validated();
        $systemMode = $moduleService->toSystemPosMode($validated['operating_mode'] ?? 'general');

        $result = $provisioner->registerTenant([
            'store_name' => $validated['store_name'],
            'owner_name' => $validated['store_name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'slug' => $validated['subdomain'] ?? null,
            'pos_mode' => $systemMode,
            'plan_name' => $validated['plan_name'] ?? 'trial',
            'activation_code' => $validated['activation_code'] ?? null,
        ]);

        Auth::guard('web')->login($result['user']);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Tenant registered successfully.',
                'data' => [
                    'company' => $result['company'],
                    'user' => $result['user'],
                    'redirect_url' => route('tenant.dashboard'),
                ],
            ]);
        }

        return redirect()->route('tenant.dashboard')->with('status', 'Welcome to your new store dashboard!');
    }
}
