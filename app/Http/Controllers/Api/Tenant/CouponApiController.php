<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CouponApiController extends Controller
{
    use ResolvesTenantSyncContext;

    /**
     * GET /api/v1/tenant/coupons
     */
    public function index(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $coupons = Coupon::where('company_id', $company->id)->orderByDesc('id')->get();

        return response()->json([
            'success' => true,
            'data' => $coupons,
            'count' => $coupons->count(),
        ]);
    }

    /**
     * POST /api/v1/tenant/coupons
     */
    public function store(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $code = strtoupper(trim((string) $request->input('code', '')));
        $request->merge(['code' => $code]);

        $validator = Validator::make($request->all(), [
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('coupons', 'code')->where('company_id', $company->id),
            ],
            'discount_type' => ['required', 'string', 'in:percentage,fixed'],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit_total' => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_customer' => ['nullable', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation error.',
                'details' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $validated['company_id'] = $company->id;
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['usage_limit_per_customer'] = $validated['usage_limit_per_customer'] ?? 1;

        $coupon = Coupon::create($validated);
        AuditLog::record('coupon.created', $company->id, $user?->id, ['coupon_id' => $coupon->id, 'code' => $coupon->code]);

        return response()->json([
            'success' => true,
            'message' => "Coupon '{$coupon->code}' created successfully.",
            'data' => $coupon,
        ], 201);
    }

    /**
     * GET /api/v1/tenant/coupons/{id}
     */
    public function show(Request $request, int|string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $coupon = Coupon::where('company_id', $company->id)->where('id', $id)->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $coupon,
        ]);
    }

    /**
     * PUT/POST /api/v1/tenant/coupons/{id}
     */
    public function update(Request $request, int|string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);
        $coupon = Coupon::where('company_id', $company->id)->where('id', $id)->firstOrFail();

        if ($request->has('code')) {
            $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        }

        $validator = Validator::make($request->all(), [
            'code' => [
                'sometimes', 'required', 'string', 'max:50',
                Rule::unique('coupons', 'code')->where('company_id', $company->id)->ignore($coupon->id),
            ],
            'discount_type' => ['sometimes', 'required', 'string', 'in:percentage,fixed'],
            'discount_value' => ['sometimes', 'required', 'numeric', 'min:0'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit_total' => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_customer' => ['nullable', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation error.',
                'details' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        if ($request->has('is_active')) {
            $validated['is_active'] = $request->boolean('is_active');
        }

        $coupon->update($validated);
        AuditLog::record('coupon.updated', $company->id, $user?->id, ['coupon_id' => $coupon->id, 'code' => $coupon->code]);

        return response()->json([
            'success' => true,
            'message' => "Coupon '{$coupon->code}' updated successfully.",
            'data' => $coupon,
        ]);
    }

    /**
     * DELETE /api/v1/tenant/coupons/{id}
     */
    public function destroy(Request $request, int|string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);
        $coupon = Coupon::where('company_id', $company->id)->where('id', $id)->firstOrFail();

        $code = $coupon->code;
        $coupon->delete();
        AuditLog::record('coupon.deleted', $company->id, $user?->id, ['coupon_id' => $id, 'code' => $code]);

        return response()->json([
            'success' => true,
            'message' => "Coupon '{$code}' deleted successfully.",
        ]);
    }
}
