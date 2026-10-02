<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class ProfileController extends Controller
{
    use ResolvesTenantSyncContext;

    public const DEMO_ALERT = 'Action disabled: Modifications are restricted in demo mode.';

    /**
     * Show tenant profile & settings screen.
     */
    public function show(): View
    {
        return view('tenant.settings.profile');
    }

    /**
     * Handle authenticated password update with demo mode protection and dual field normalization.
     */
    public function updatePassword(Request $request): JsonResponse|RedirectResponse
    {
        // 1. Strict Demo Mode Protection Guard
        if (config('app.demo_mode')) {
            if ($request->expectsJson() || $request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'status' => 'error',
                    'message' => self::DEMO_ALERT,
                    'error' => self::DEMO_ALERT,
                ], 403);
            }

            return redirect()->back()->with('error', self::DEMO_ALERT);
        }

        // 2. Normalize password / new_password input aliases
        if ($request->filled('password') && ! $request->filled('new_password')) {
            $request->merge([
                'new_password' => $request->input('password'),
                'new_password_confirmation' => $request->input('password_confirmation'),
            ]);
        } elseif ($request->filled('new_password') && ! $request->filled('password')) {
            $request->merge([
                'password' => $request->input('new_password'),
                'password_confirmation' => $request->input('new_password_confirmation'),
            ]);
        }

        // 3. Validation
        $validator = Validator::make($request->all(), [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson() || $request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation error.',
                    'details' => $validator->errors()->toArray(),
                ], 422);
            }

            return redirect()->back()
                ->withErrors($validator, 'updatePassword')
                ->withErrors($validator)
                ->withInput();
        }

        // 4. Resolve authenticated user
        $user = auth('web')->user() ?? auth()->user();
        if (! $user && app()->bound('tenant.company_id')) {
            $user = $this->resolveUser($request, $this->resolveCompany($request));
        }

        if (! $user) {
            if ($request->expectsJson() || $request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'error' => 'Not authenticated.',
                ], 401);
            }

            return redirect()->back()->with('error', 'Not authenticated.');
        }

        // 5. Verify current password
        if (! Hash::check($request->input('current_password'), $user->password)) {
            $errorMessage = 'Your current password is incorrect.';
            if ($request->expectsJson() || $request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'error' => $errorMessage,
                    'details' => ['current_password' => ['Incorrect password.']],
                ], 422);
            }

            return redirect()->back()
                ->withErrors(['current_password' => 'Incorrect password.'], 'updatePassword')
                ->withErrors(['current_password' => 'Incorrect password.'])
                ->with('error', $errorMessage);
        }

        // 6. Persist new hashed password
        $user->update([
            'password' => Hash::make($request->input('password')),
        ]);

        $successMessage = 'Password changed successfully.';
        if ($request->expectsJson() || $request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => true,
                'message' => $successMessage,
            ]);
        }

        return redirect()->back()->with('status', $successMessage);
    }
}
