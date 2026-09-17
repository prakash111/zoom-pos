<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Mail\InvoiceMailable;
use App\Models\Sale;
use App\Models\Tenant;
use App\Services\Dispatch\DocumentDispatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentDispatchController extends Controller
{
    use ResolvesTenantSyncContext;

    public function getDispatchOptions(Request $request, string $type, string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($this->resolveCompany($request)->id);
        $document = $this->resolveDocument($tenant, $type, $id);
        $settings = (array) ($tenant->api_settings ?? []);
        $customer = $document->customer;

        return response()->json([
            'success' => true,
            'document_code' => $document->sale_number ?? (string) $document->id,
            'customer' => [
                'phone' => $customer?->phone,
                'email' => $customer?->email,
            ],
            'channels' => [
                'whatsapp' => [
                    'available' => true,
                    'default' => true,
                    'provider' => $this->enabled($settings, 'whatsapp_api_enabled') ? 'Custom Gateway' : 'System Service',
                    'target' => $customer?->phone ?: 'Enter phone number',
                ],
                'email' => [
                    'available' => true,
                    'default' => ! empty($customer?->email),
                    'provider' => $this->enabled($settings, 'smtp_enabled') ? 'Custom SMTP' : 'System Mailer',
                    'target' => $customer?->email ?: 'Enter email address',
                ],
            ],
        ]);
    }

    public function dispatchDocument(Request $request, DocumentDispatchService $dispatchService): JsonResponse
    {
        $validated = $request->validate([
            'document_type' => ['required', 'string', 'in:invoice,sale,quotation,receipt'],
            'document_id' => ['required'],
            'send_whatsapp' => ['sometimes', 'boolean'],
            'send_email' => ['sometimes', 'boolean'],
            'phone' => ['nullable', 'string'],
            'email' => ['nullable', 'email'],
        ]);

        $tenant = Tenant::findOrFail($this->resolveCompany($request)->id);
        $document = $this->resolveDocument($tenant, $validated['document_type'], $validated['document_id']);
        $results = [];
        $code = $document->sale_number ?? (string) $document->id;

        if ($request->boolean('send_whatsapp') && ! empty($validated['phone'])) {
            $results['whatsapp'] = $dispatchService->dispatchWhatsApp(
                $tenant,
                $validated['phone'],
                "Hello! Your document {$code} from {$tenant->name} is ready.",
                null,
            );
        }

        if ($request->boolean('send_email') && ! empty($validated['email'])) {
            $results['email'] = $dispatchService->dispatchEmail(
                $tenant,
                $validated['email'],
                "Document {$code} from {$tenant->name}",
                new InvoiceMailable($document, $tenant),
            );
        }

        $successful = collect($results)->contains(fn (array $result) => (bool) ($result['success'] ?? false));

        return response()->json([
            'success' => $successful,
            'message' => $successful ? 'Dispatched successfully.' : 'No selected channel could be dispatched.',
            'results' => $results,
        ], $successful ? 200 : 422);
    }

    protected function resolveDocument(Tenant $tenant, string $type, string|int $id): Sale
    {
        return Sale::withoutGlobalScope('company')
            ->with(['customer', 'company'])
            ->where('company_id', $tenant->id)
            ->where(function ($query) use ($id) {
                $query->where('id', $id)
                    ->orWhere('sale_number', (string) $id)
                    ->orWhere('external_id', (string) $id);
            })
            ->firstOrFail();
    }

    protected function enabled(array $settings, string $key): bool
    {
        return filter_var($settings[$key] ?? false, FILTER_VALIDATE_BOOL);
    }
}
