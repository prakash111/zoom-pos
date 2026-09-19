<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoicePreviewController extends Controller
{
    use ResolvesTenantSyncContext;

    public function previewSheet(Request $request, int|string|null $id = null): JsonResponse
    {
        // Keep every invoice preview on the same format-aware, unified
        // bottom-sheet contract used by POS, quotations, and repair tickets.
        // This legacy route remains as a compatibility alias for existing
        // clients while the shared controller owns the schema.
        $requestedId = $id ?? $request->input('id') ?? $request->input('invoice_id') ?? $request->input('sale_id');

        if (! $requestedId) {
            $tenantId = auth()->user()?->tenant_id
                ?? auth()->user()?->company_id
                ?? (app()->bound('tenant.company_id') ? app('tenant.company_id') : null)
                ?? $request->attributes->get('company_id');
            if (! $tenantId) {
                $tenantId = $this->resolveCompany($request)->id;
            }
            $requestedId = Sale::withoutGlobalScope('company')
                ->where('company_id', $tenantId)
                ->latest('id')
                ->value('id');
        }

        if (! $requestedId) {
            return response()->json(['success' => false, 'error' => 'Invoice not found.'], 404);
        }

        return app(DocumentPreviewController::class)->previewModal($request, 'invoice', $requestedId);
    }
}
