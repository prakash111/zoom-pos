<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\TenantInquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StoreInquiryController extends Controller
{
    use ResolvesTenantSyncContext;

    /**
     * Resolve target company from HTTP context, hostname, subdomain, or request body.
     */
    protected function resolveTargetCompany(Request $request): ?Company
    {
        if (app()->bound('tenant.company_id')) {
            $company = Company::withoutGlobalScopes()->find(app('tenant.company_id'));
            if ($company) {
                return $company;
            }
        }

        $host = strtolower($request->getHost());
        $baseHost = parse_url(config('app.url'), PHP_URL_HOST) ?: config('app.domain', 'saas.zoomnearby.com');

        if ($baseHost && str_ends_with($host, '.'.$baseHost)) {
            $slug = substr($host, 0, -(strlen($baseHost) + 1));
            if ($slug && ! in_array($slug, Company::RESERVED_SLUGS, true)) {
                $company = Company::withoutGlobalScopes()->where('slug', $slug)->first();
                if ($company) {
                    return $company;
                }
            }
        }

        if ($baseHost && $host !== $baseHost && $host !== 'localhost' && $host !== '127.0.0.1') {
            $company = Company::withoutGlobalScopes()->where('custom_domain', $host)->first();
            if ($company) {
                return $company;
            }
        }

        $storeSlug = $request->input('store')
            ?: $request->query('store')
            ?: $request->input('store_slug')
            ?: $request->input('slug')
            ?: $request->query('slug');

        if ($storeSlug) {
            $company = Company::withoutGlobalScopes()
                ->where('slug', strtolower(trim((string) $storeSlug)))
                ->orWhere('subdomain', strtolower(trim((string) $storeSlug)))
                ->first();
            if ($company) {
                return $company;
            }
        }

        $companyId = $request->input('company_id')
            ?: $request->input('tenant_id')
            ?: $request->query('company_id')
            ?: $request->query('tenant_id');

        if ($companyId) {
            $company = Company::withoutGlobalScopes()->find($companyId);
            if ($company) {
                return $company;
            }
        }

        if ($request->user()?->company_id) {
            $company = Company::withoutGlobalScopes()->find($request->user()->company_id);
            if ($company) {
                return $company;
            }
        }

        return Company::withoutGlobalScopes()->first();
    }

    /**
     * Public storefront inquiry submission endpoint.
     * POST /api/v1/storefront/inquiry
     * POST /storefront/inquiry
     */
    public function submitPublicInquiry(Request $request): JsonResponse
    {
        $company = $this->resolveTargetCompany($request);
        if (! $company) {
            return response()->json([
                'success' => false,
                'error' => 'Store Not Found',
                'message' => 'Unable to determine the target storefront for this inquiry.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation error.',
                'details' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        if (empty($data['email']) && empty($data['phone'])) {
            return response()->json([
                'success' => false,
                'error' => 'Contact details required',
                'message' => 'Please provide either an email address or a phone number so the store can reach you.',
            ], 422);
        }

        $inquiry = TenantInquiry::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'tenant_id' => $company->id,
            'name' => trim($data['name']),
            'email' => ! empty($data['email']) ? strtolower(trim($data['email'])) : null,
            'phone' => ! empty($data['phone']) ? trim($data['phone']) : null,
            'subject' => ! empty($data['subject']) ? trim($data['subject']) : 'Online Store Inquiry',
            'message' => trim($data['message']),
            'status' => 'unread',
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Your inquiry has been sent successfully. The store owner will contact you shortly.',
            'data' => [
                'id' => (string) $inquiry->id,
                'name' => $inquiry->name,
                'email' => $inquiry->email,
                'phone' => $inquiry->phone,
                'subject' => $inquiry->subject,
                'message' => $inquiry->message,
                'status' => $inquiry->status,
                'created_at' => $inquiry->created_at?->toISOString(),
            ],
        ], 201);
    }

    /**
     * Tenant Inquiries List.
     * GET /api/v1/tenant/storefront/inquiries
     * GET /api/v1/pos/storefront/inquiries
     */
    public function index(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $status = $request->query('status');
        $search = $request->query('search');
        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));

        $query = TenantInquiry::withoutGlobalScopes()
            ->where('company_id', $company->id);

        if ($status && in_array(strtolower($status), ['unread', 'contacted', 'closed'], true)) {
            $query->where('status', strtolower($status));
        }

        if ($search && trim($search) !== '') {
            $term = '%'.trim($search).'%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('subject', 'like', $term)
                    ->orWhere('message', 'like', $term);
            });
        }

        $unreadCount = TenantInquiry::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('status', 'unread')
            ->count();

        $totalCount = (clone $query)->count();

        $inquiries = $query->orderByDesc('created_at')->paginate($perPage);

        $formatted = collect($inquiries->items())->map(function ($item) {
            return [
                'id' => (string) $item->id,
                'company_id' => (string) $item->company_id,
                'tenant_id' => (string) $item->tenant_id,
                'name' => $item->name,
                'email' => $item->email,
                'phone' => $item->phone,
                'subject' => $item->subject,
                'message' => $item->message,
                'status' => $item->status,
                'ip_address' => $item->ip_address,
                'created_at' => $item->created_at?->toISOString(),
                'updated_at' => $item->updated_at?->toISOString(),
                'created_at_human' => $item->created_at?->diffForHumans(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'inquiries' => $formatted,
                'unread_count' => $unreadCount,
                'total_count' => $totalCount,
                'pagination' => [
                    'current_page' => $inquiries->currentPage(),
                    'last_page' => $inquiries->lastPage(),
                    'per_page' => $inquiries->perPage(),
                    'total' => $inquiries->total(),
                ],
            ],
        ]);
    }

    /**
     * Update inquiry status.
     * PUT /api/v1/tenant/storefront/inquiries/{id}/status
     * POST /api/v1/tenant/storefront/inquiries/{id}/status
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $validator = Validator::make($request->all(), [
            'status' => ['required', 'string', 'in:unread,contacted,closed'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation error.',
                'details' => $validator->errors(),
            ], 422);
        }

        $inquiry = TenantInquiry::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('id', $id)
            ->first();

        if (! $inquiry) {
            return response()->json([
                'success' => false,
                'error' => 'Inquiry not found',
            ], 404);
        }

        $oldStatus = $inquiry->status;
        $inquiry->update([
            'status' => strtolower($request->input('status')),
        ]);

        AuditLog::record('storefront.inquiry_status_updated', $company->id, $user?->id, [
            'inquiry_id' => $inquiry->id,
            'old_status' => $oldStatus,
            'new_status' => $inquiry->status,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Inquiry status updated successfully.',
            'data' => [
                'id' => (string) $inquiry->id,
                'status' => $inquiry->status,
                'updated_at' => $inquiry->updated_at?->toISOString(),
            ],
        ]);
    }

    /**
     * Delete an inquiry.
     * DELETE /api/v1/tenant/storefront/inquiries/{id}
     * POST /api/v1/tenant/storefront/inquiries/{id}/delete
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $inquiry = TenantInquiry::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('id', $id)
            ->first();

        if (! $inquiry) {
            return response()->json([
                'success' => false,
                'error' => 'Inquiry not found',
            ], 404);
        }

        $inquiry->delete();

        AuditLog::record('storefront.inquiry_deleted', $company->id, $user?->id, [
            'inquiry_id' => $id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Inquiry deleted successfully.',
        ]);
    }
}
