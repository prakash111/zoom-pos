<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Mail\InvoiceMailable;
use App\Models\KitchenTicket;
use App\Models\PharmacyPrescription;
use App\Models\RepairTicket;
use App\Models\Sale;
use App\Models\SalonAppointment;
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
        $doc = $this->resolveDocumentInfo($tenant, $type, $id);
        $settings = (array) ($tenant->api_settings ?? []);

        return response()->json([
            'success' => true,
            'document_code' => $doc['code'],
            'customer' => [
                'name' => $doc['customerName'],
                'phone' => $doc['customerPhone'],
                'email' => $doc['customerEmail'],
            ],
            'context' => $doc['context'],
            'channels' => [
                'whatsapp' => [
                    'available' => true,
                    'default' => true,
                    'provider' => $this->enabled($settings, 'whatsapp_api_enabled') ? 'Custom Gateway' : 'System Service',
                    'target' => $doc['customerPhone'] ?: 'Customer Phone / Kitchen Desk',
                ],
                'email' => [
                    'available' => true,
                    'default' => ! empty($doc['customerEmail']),
                    'provider' => $this->enabled($settings, 'smtp_enabled') ? 'Custom SMTP' : 'System Mailer',
                    'target' => $doc['customerEmail'] ?: 'Enter email address',
                ],
            ],
        ]);
    }

    public function dispatchDocument(Request $request, DocumentDispatchService $dispatchService): JsonResponse
    {
        $validated = $request->validate([
            'document_type' => ['required', 'string'],
            'document_id' => ['required'],
            'send_whatsapp' => ['sometimes', 'boolean'],
            'send_email' => ['sometimes', 'boolean'],
            'channels' => ['sometimes', 'array'],
            'phone' => ['nullable', 'string'],
            'email' => ['nullable', 'string'],
        ]);

        $channels = (array) ($validated['channels'] ?? []);
        $sendWhatsApp = $request->boolean('send_whatsapp') || in_array('whatsapp', $channels, true);
        $sendEmail = $request->boolean('send_email') || in_array('email', $channels, true);

        if (! $sendWhatsApp && ! $sendEmail && empty($channels)) {
            $sendWhatsApp = true;
            $sendEmail = ! empty($validated['email']);
        }

        $tenant = Tenant::findOrFail($this->resolveCompany($request)->id);
        $doc = $this->resolveDocumentInfo($tenant, $validated['document_type'], $validated['document_id']);

        $targetPhone = ! empty($validated['phone']) ? $validated['phone'] : $doc['customerPhone'];
        $targetEmail = ! empty($validated['email']) ? $validated['email'] : $doc['customerEmail'];

        $results = [];

        if ($sendWhatsApp) {
            $phoneToUse = $targetPhone ?: '0000000000';
            $results['whatsapp'] = $dispatchService->dispatchWhatsApp(
                $tenant,
                $phoneToUse,
                $doc['message'],
                null,
            );
        }

        if ($sendEmail) {
            $emailToUse = $targetEmail ?: "customer@{$tenant->domain}";
            $mailable = $doc['sale'] instanceof Sale
                ? new InvoiceMailable($doc['sale'], $tenant)
                : "<div style='font-family:sans-serif;padding:20px;background:#f8fafc;color:#0f172a;'><h2 style='color:#10b981;'>{$doc['code']}</h2><p>{$doc['message']}</p></div>";

            $results['email'] = $dispatchService->dispatchEmail(
                $tenant,
                $emailToUse,
                "Document {$doc['code']} from {$tenant->name}",
                $mailable,
            );
        }

        $whatsappUrl = $results['whatsapp']['whatsapp_url'] ?? $results['whatsapp']['url'] ?? null;

        return response()->json([
            'success' => true,
            'message' => 'Dispatched successfully via selected channels.',
            'document_code' => $doc['code'],
            'customer' => [
                'name' => $doc['customerName'],
                'phone' => $targetPhone,
                'email' => $targetEmail,
            ],
            'results' => $results,
            'whatsapp_url' => $whatsappUrl,
        ], 200);
    }

    protected function resolveDocumentInfo(Tenant $tenant, string $type, string|int $id): array
    {
        $normalizedType = strtolower(trim($type));
        $sale = null;
        $code = null;
        $customerName = null;
        $customerPhone = null;
        $customerEmail = null;
        $context = null;
        $timestamp = now()->toIso8601String();
        $taxId = $tenant->tax_id ?? $tenant->tax_number ?? $tenant->gst_number ?? null;

        // 1. Repair / Service Tickets
        if (in_array($normalizedType, ['repair', 'ticket', 'job_sheet', 'intake', 'diagnostic_report', 'repair_invoice', 'intake_job_sheet'], true)) {
            $ticket = RepairTicket::withoutGlobalScope('company')
                ->where('company_id', $tenant->id)
                ->where(function ($query) use ($id) {
                    $query->where('id', $id)
                        ->orWhere('ticket_number', (string) $id);
                })
                ->first();

            if ($ticket) {
                $raw = $ticket->ticket_number ?: (string) $ticket->id;
                $code = $this->formatDocumentCode($raw, 'REP');
                $customerName = $ticket->customer_name ?: ($ticket->customer?->name ?? 'Valued Customer');
                $customerPhone = $ticket->customer_phone ?: ($ticket->customer?->phone ?? null);
                $customerEmail = $ticket->customer_email ?: ($ticket->customer?->email ?? null);
                $deviceDesc = trim(($ticket->brand ?? '') . ' ' . ($ticket->model ?? ''));
                $context = implode(' • ', array_filter([$customerName, $deviceDesc, $ticket->created_at?->format('d M Y, h:i A'), $taxId ? "Tax ID: {$taxId}" : null]));
                $message = "Hello {$customerName}! Your repair service sheet {$code} for {$deviceDesc} at {$tenant->name} is ready. Status: " . ucfirst($ticket->status ?? 'Intake');
                return compact('code', 'customerName', 'customerPhone', 'customerEmail', 'context', 'message', 'sale', 'timestamp', 'taxId');
            }
        }

        // 2. Kitchen / Restaurant Tickets
        if (in_array($normalizedType, ['kot', 'kitchen', 'restaurant', 'split_bill', 'dine_in_invoice'], true)) {
            $kot = KitchenTicket::withoutGlobalScope('company')
                ->where('company_id', $tenant->id)
                ->where(function ($query) use ($id) {
                    $query->where('id', $id)
                        ->orWhere('kot_number', (string) $id);
                })
                ->first();

            if ($kot) {
                $raw = $kot->kot_number ?: (string) $kot->id;
                $code = $this->formatDocumentCode($raw, 'KOT');
                $tableDesc = $kot->table_name ? "Table {$kot->table_name}" : 'Dine-In';
                $customerName = $tableDesc;
                $context = implode(' • ', array_filter([$tableDesc, $kot->created_at?->format('d M Y, h:i A')]));
                $message = "KOT Order {$code} for {$tableDesc} at {$tenant->name} has been intimating kitchen.";
                return compact('code', 'customerName', 'customerPhone', 'customerEmail', 'context', 'message', 'sale', 'timestamp', 'taxId');
            }
        }

        // 3. Pharmacy / Prescriptions
        if (in_array($normalizedType, ['prescription', 'prescription_slip', 'rx', 'bill_of_supply', 'patient_invoice', 'pharmacy'], true)) {
            $rx = PharmacyPrescription::withoutGlobalScope('company')
                ->where('company_id', $tenant->id)
                ->where(function ($query) use ($id) {
                    $query->where('id', $id)
                        ->orWhere('prescription_number', (string) $id)
                        ->orWhere('prescription_code', (string) $id)
                        ->orWhere('rx_number', (string) $id);
                })
                ->first();

            if ($rx) {
                $rawCode = $rx->prescription_number ?: ($rx->prescription_code ?: ($rx->rx_number ?: (string) $rx->id));
                $code = $this->formatDocumentCode($rawCode, 'RX');
                $customerName = $rx->patient_name ?: 'Patient';
                $customerPhone = $rx->patient_phone ?: ($rx->customer?->phone ?? null);
                $customerEmail = $rx->patient_email ?: ($rx->customer?->email ?? null);
                $docDesc = $rx->doctor_name ? "Dr. {$rx->doctor_name}" : null;
                $context = implode(' • ', array_filter([$customerName, $docDesc, $rx->created_at?->format('d M Y, h:i A'), $taxId ? "Tax ID: {$taxId}" : null]));
                $message = "Hello {$customerName}! Your prescription record {$code} from {$tenant->name} is ready.";
                return compact('code', 'customerName', 'customerPhone', 'customerEmail', 'context', 'message', 'sale', 'timestamp', 'taxId');
            }
        }

        // 4. Salon Appointments
        if (in_array($normalizedType, ['salon', 'appointment', 'treatment_estimation', 'package'], true)) {
            $apt = SalonAppointment::withoutGlobalScope('company')
                ->where('company_id', $tenant->id)
                ->where(function ($query) use ($id) {
                    $query->where('id', $id)
                        ->orWhere('appointment_number', (string) $id);
                })
                ->first();

            if ($apt) {
                $rawCode = $apt->appointment_number ?: (string) $apt->id;
                $code = $this->formatDocumentCode($rawCode, 'APT');
                $customerName = $apt->customer_name ?: 'Client';
                $customerPhone = $apt->customer_phone ?: null;
                $customerEmail = $apt->customer_email ?: null;
                $context = implode(' • ', array_filter([$customerName, $apt->starts_at?->format('d M Y, h:i A'), $taxId ? "Tax ID: {$taxId}" : null]));
                $message = "Hello {$customerName}! Your appointment confirmation {$code} from {$tenant->name} is booked.";
                return compact('code', 'customerName', 'customerPhone', 'customerEmail', 'context', 'message', 'sale', 'timestamp', 'taxId');
            }
        }

        // 5. General Sale / Quotation / Due Invoice / POS Receipt
        $sale = Sale::withoutGlobalScope('company')
            ->with(['customer', 'company'])
            ->where('company_id', $tenant->id)
            ->where(function ($query) use ($id) {
                $query->where('id', $id)
                    ->orWhere('sale_number', (string) $id)
                    ->orWhere('external_id', (string) $id);
            })
            ->first();

        if ($sale) {
            $rawCode = $sale->sale_number ?: (string) $sale->id;
            $defaultPrefix = ($sale->operation_type === 'quotation' || $normalizedType === 'quotation') ? 'QUO' : 'INV';
            $code = $this->formatDocumentCode($rawCode, $defaultPrefix);
            $customerName = $sale->customer_name ?: ($sale->customer?->name ?? 'Valued Customer');
            $customerPhone = $sale->customer?->phone ?: ($sale->customer_phone ?? null);
            $customerEmail = $sale->customer?->email ?: ($sale->customer_email ?? null);
            $taxId = $sale->tax_id ?: $taxId;
            $context = implode(' • ', array_filter([$customerName, $sale->created_at?->format('d M Y, h:i A'), $taxId ? "Tax ID: {$taxId}" : null]));
            $message = "Hello {$customerName}! Your document {$code} from {$tenant->name} is ready. Total: {$tenant->currency_symbol}{$sale->total}.";
            return compact('code', 'customerName', 'customerPhone', 'customerEmail', 'context', 'message', 'sale', 'timestamp', 'taxId');
        }

        // Fallback placeholder if record not found in specific table
        $code = str_starts_with((string) $id, '#') ? (string) $id : "#{$id}";
        $customerName = 'Valued Customer';
        $context = implode(' • ', array_filter([$customerName, now()->format('d M Y, h:i A'), $taxId ? "Tax ID: {$taxId}" : null]));
        $message = "Hello! Your document {$code} from {$tenant->name} is ready.";
        return compact('code', 'customerName', 'customerPhone', 'customerEmail', 'context', 'message', 'sale', 'timestamp', 'taxId');
    }

    protected function formatDocumentCode(string $rawCode, string $prefix): string
    {
        $rawCode = trim($rawCode);
        if (str_starts_with($rawCode, '#')) {
            return $rawCode;
        }
        $prefix = strtoupper(trim($prefix, '-'));
        if (str_starts_with(strtoupper($rawCode), "{$prefix}-") || str_starts_with(strtoupper($rawCode), "{$prefix}")) {
            return "#{$rawCode}";
        }
        return "#{$prefix}-{$rawCode}";
    }

    protected function enabled(array $settings, string $key): bool
    {
        return filter_var($settings[$key] ?? false, FILTER_VALIDATE_BOOL);
    }
}
