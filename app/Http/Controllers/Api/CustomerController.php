<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class CustomerController extends Controller
{
    use ResolvesTenantSyncContext;

    /**
     * Search customers across name, phone, email, document/tax_id, and company name.
     * GET /api/tenant/customers/search
     * GET /api/v1/tenant/customers/search
     */
    public function search(Request $request): JsonResponse
    {
        $company = null;
        try {
            $company = $this->resolveCompany($request);
        } catch (\Throwable $e) {
            $companyId = auth()->user()?->company_id ?? auth()->user()?->tenant_id ?? app('tenant.company_id');
            if ($companyId) {
                $company = Company::find($companyId);
            }
        }

        $companyId = $company?->id ?? auth()->user()?->company_id ?? auth()->user()?->tenant_id ?? app('tenant.company_id');
        $query = trim((string) ($request->input('q') ?: $request->input('query') ?: $request->input('search') ?: ''));

        $builder = Customer::query()->withoutGlobalScope('company');

        if ($companyId) {
            $builder->where('company_id', $companyId);
        }

        if ($query !== '') {
            $hasCompanyCol = Schema::hasColumn('customers', 'company_name');
            $builder->where(function ($sub) use ($query, $hasCompanyCol) {
                $sub->where('name', 'LIKE', "%{$query}%")
                    ->orWhere('phone', 'LIKE', "%{$query}%")
                    ->orWhere('email', 'LIKE', "%{$query}%")
                    ->orWhere('document', 'LIKE', "%{$query}%")
                    ->orWhere('tax_id', 'LIKE', "%{$query}%")
                    ->orWhere('gstin', 'LIKE', "%{$query}%")
                    ->orWhere('custom_fields->company_name', 'LIKE', "%{$query}%");

                if ($hasCompanyCol) {
                    $sub->orWhere('company_name', 'LIKE', "%{$query}%");
                }
            });
        }

        $customers = $builder->orderBy('name', 'asc')
            ->limit(50)
            ->get();

        $data = $customers->map(function (Customer $c) {
            $companyName = $c->company_name ?? ($c->custom_fields['company_name'] ?? '');
            $label = $c->name;
            if ($c->phone) {
                $label .= " ({$c->phone})";
            }
            if ($companyName && $companyName !== $c->name) {
                $label .= " - {$companyName}";
            }

            return [
                'id' => (string) ($c->external_id ?: $c->id),
                'server_id' => $c->id,
                'value' => $c->id,
                'label' => $label,
                'name' => $c->name,
                'title' => $c->name,
                'subtitle' => $c->phone ?: ($c->email ?: ''),
                'phone' => $c->phone ?? '',
                'email' => $c->email ?? '',
                'company_name' => $companyName,
                'due_amount' => $c->due_balance > 0 ? 'Due: ' . number_format((float) $c->due_balance, 2) : null,
                'avatar_icon' => 'person',
                'badge_due_bg' => 'rgba(239, 68, 68, 0.15)',
                'badge_due_tx' => '#F87171',
                'document' => $c->document ?? $c->tax_id ?? '',
                'balance_due' => (float) ($c->due_balance ?? 0),
                'custom_fields' => $c->custom_fields ?? (object) [],
            ];
        });

        return response()->json([
            'success' => true,
            'count' => $data->count(),
            'theme' => [
                'container_bg'     => '#1E293B',    // High-contrast slate surface
                'dropdown_surface' => '#1E293B',
                'popup_background' => '#1E293B',
                'surface'          => '#1E293B',
                'card'             => '#1E293B',
                'border_color'     => '#334155',    // Slate divider
                'title_color'      => '#F8FAFC',    // High-contrast white
                'sub_color'        => '#94A3B8',    // Slate-400
                'text_color'       => '#F8FAFC',
                'badge_due_bg'     => 'rgba(239, 68, 68, 0.15)',
                'badge_due_tx'     => '#F87171',
            ],
            'data' => $data,
            'customers' => $data,
        ]);
    }
}
