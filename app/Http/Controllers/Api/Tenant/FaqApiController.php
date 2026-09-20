<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Faq;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FaqApiController extends Controller
{
    use ResolvesTenantSyncContext;

    /**
     * GET /api/v1/tenant/faqs
     */
    public function index(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $faqs = Faq::where('company_id', $company->id)->ordered()->get();

        if ($faqs->isEmpty()) {
            Faq::seedDefaultsForCompany($company->id);
            $faqs = Faq::where('company_id', $company->id)->ordered()->get();
        }

        return response()->json([
            'success' => true,
            'data' => $faqs,
            'count' => $faqs->count(),
        ]);
    }

    /**
     * POST /api/v1/tenant/faqs
     */
    public function store(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $validator = Validator::make($request->all(), [
            'question' => ['required', 'string', 'max:255'],
            'answer' => ['required', 'string', 'max:2000'],
            'category' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer'],
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
        $validated['category'] = $validated['category'] ?? 'General';
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['is_active'] = $request->boolean('is_active', true);

        $faq = Faq::create($validated);
        AuditLog::record('faq.created', $company->id, $user?->id, ['faq_id' => $faq->id]);

        return response()->json([
            'success' => true,
            'message' => 'Store FAQ created successfully.',
            'data' => $faq,
        ], 201);
    }

    /**
     * GET /api/v1/tenant/faqs/{id}
     */
    public function show(Request $request, int|string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $faq = Faq::where('company_id', $company->id)->where('id', $id)->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $faq,
        ]);
    }

    /**
     * PUT/POST /api/v1/tenant/faqs/{id}
     */
    public function update(Request $request, int|string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);
        $faq = Faq::where('company_id', $company->id)->where('id', $id)->firstOrFail();

        $validator = Validator::make($request->all(), [
            'question' => ['sometimes', 'required', 'string', 'max:255'],
            'answer' => ['sometimes', 'required', 'string', 'max:2000'],
            'category' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer'],
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

        $faq->update($validated);
        AuditLog::record('faq.updated', $company->id, $user?->id, ['faq_id' => $faq->id]);

        return response()->json([
            'success' => true,
            'message' => 'Store FAQ updated successfully.',
            'data' => $faq,
        ]);
    }

    /**
     * DELETE /api/v1/tenant/faqs/{id}
     */
    public function destroy(Request $request, int|string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);
        $faq = Faq::where('company_id', $company->id)->where('id', $id)->firstOrFail();

        $faq->delete();
        AuditLog::record('faq.deleted', $company->id, $user?->id, ['faq_id' => $id]);

        return response()->json([
            'success' => true,
            'message' => 'Store FAQ deleted successfully.',
        ]);
    }
}
