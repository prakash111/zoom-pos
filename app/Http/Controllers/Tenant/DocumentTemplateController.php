<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\TenantDocumentTemplate;
use App\Models\User;
use App\Services\Auth\PermissionChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DocumentTemplateController extends Controller
{
    use ResolvesTenantSyncContext;

    /**
     * Normalize type parameter to 'invoice' or 'quotation'.
     */
    protected function normalizeType(string $type): string
    {
        $lower = strtolower(trim($type));
        if (str_starts_with($lower, 'quot') || str_starts_with($lower, 'quote')) {
            return 'quotation';
        }
        return 'invoice';
    }

    /**
     * Verify caller has permission to view / manage templates.
     */
    protected function authorizeTemplateAccess(?User $user, string $type, bool $editing = false): void
    {
        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        if (in_array($user->role, ['admin', 'owner', 'superadmin'], true)) {
            return;
        }

        $plural = $this->normalizeType($type) === 'quotation' ? 'quotations' : 'invoices';
        $allowed = PermissionChecker::can($user, 'templates.invoices', 'manage')
            || (! $editing && PermissionChecker::can($user, 'settings', 'view'));

        if (! $allowed) {
            abort(403, "You do not have permission to manage {$plural} templates.");
        }
    }

    /**
     * Web view for template customizer (/settings/templates/{type}).
     */
    public function edit(Request $request, string $type)
    {
        $user = auth('web')->user() ?? auth('sanctum')->user() ?? $request->user();
        $this->authorizeTemplateAccess($user, $type);

        $company = $this->resolveCompany($request);
        $normType = $this->normalizeType($type);
        $template = TenantDocumentTemplate::getForCompany($company->id, $normType);

        return view('tenant.settings.templates', [
            'company' => $company,
            'template' => $template,
            'type' => $normType,
            'user' => $user,
        ]);
    }

    /**
     * Web & Form submission update handler.
     */
    public function update(Request $request, string $type)
    {
        $user = auth('web')->user() ?? auth('sanctum')->user() ?? $request->user();
        $this->authorizeTemplateAccess($user, $type, true);

        $company = $this->resolveCompany($request);
        $normType = $this->normalizeType($type);

        $validated = $request->validate([
            'theme_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logo_placement' => 'nullable|string|in:left,center,right,hidden',
            'header_title' => 'nullable|string|max:120',
            'terms_conditions' => 'nullable|string|max:4000',
            'show_qr_code' => 'nullable|boolean',
            'show_tax_breakup' => 'nullable|boolean',
            'footer_notes' => 'nullable|string|max:1000',
            'send_as_attachment' => 'nullable|boolean',
            'send_text_with_link' => 'nullable|boolean',
            'message_body_template' => 'nullable|string|max:2000',
        ]);
        $current = TenantDocumentTemplate::getForCompany($company->id, $normType);
        $attachPdf = $request->has('send_as_attachment')
            ? $request->boolean('send_as_attachment')
            : (bool) $current->send_as_attachment;

        $template = TenantDocumentTemplate::updateOrCreate(
            ['company_id' => $company->id, 'template_type' => $normType],
            [
                'tenant_id' => $company->id,
                'theme_color' => $validated['theme_color'] ?? $current->theme_color,
                'logo_placement' => $validated['logo_placement'] ?? $current->logo_placement,
                'header_title' => $validated['header_title'] ?? $current->header_title,
                'terms_conditions' => array_key_exists('terms_conditions', $validated) ? $validated['terms_conditions'] : $current->terms_conditions,
                'show_qr_code' => $request->has('show_qr_code') ? $request->boolean('show_qr_code') : $current->show_qr_code,
                'show_tax_breakup' => $request->has('show_tax_breakup') ? $request->boolean('show_tax_breakup') : $current->show_tax_breakup,
                'footer_notes' => array_key_exists('footer_notes', $validated) ? $validated['footer_notes'] : $current->footer_notes,
                'send_as_attachment' => $attachPdf,
                'send_text_with_link' => ! $attachPdf,
                'message_body_template' => array_key_exists('message_body_template', $validated) ? $validated['message_body_template'] : $current->message_body_template,
            ]
        );

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => ucfirst($normType).' template settings saved successfully.',
                'template' => $template,
            ]);
        }

        return redirect()
            ->route('settings.templates.edit', ['type' => $normType === 'quotation' ? 'quotations' : 'invoices'])
            ->with('status', ucfirst($normType).' template updated successfully.');
    }

    /**
     * Live preview iframe content for the customizer view.
     */
    public function previewHtml(Request $request, string $type)
    {
        $user = auth('web')->user() ?? auth('sanctum')->user() ?? $request->user();
        $this->authorizeTemplateAccess($user, $type);

        $company = $this->resolveCompany($request);
        $normType = $this->normalizeType($type);

        // Fetch saved or transient template from request params for immediate live updates
        $template = TenantDocumentTemplate::getForCompany($company->id, $normType);
        if ($request->has('theme_color')) {
            $template->theme_color = $request->input('theme_color');
        }
        if ($request->has('header_title')) {
            $template->header_title = $request->input('header_title');
        }
        if ($request->has('terms_conditions')) {
            $template->terms_conditions = $request->input('terms_conditions');
        }
        if ($request->has('footer_notes')) {
            $template->footer_notes = $request->input('footer_notes');
        }
        if ($request->has('logo_placement')) {
            $template->logo_placement = $request->input('logo_placement');
        }
        if ($request->has('show_qr_code')) {
            $template->show_qr_code = $request->boolean('show_qr_code');
        }
        if ($request->has('show_tax_breakup')) {
            $template->show_tax_breakup = $request->boolean('show_tax_breakup');
        }

        // Mock document object with realistic sample line items
        $mockDoc = new Sale([
            'id' => 1001,
            'company_id' => $company->id,
            'sale_number' => $normType === 'quotation' ? 'QT-2026-0889' : 'INV-2026-4421',
            'customer_name' => 'Sarah Connor',
            'subtotal' => 220.00,
            'discount' => 10.00,
            'tax_amount' => 18.90,
            'total' => 228.90,
            'paid_amount' => $normType === 'quotation' ? 0.00 : 228.90,
            'due_amount' => $normType === 'quotation' ? 228.90 : 0.00,
            'payment_status' => $normType === 'quotation' ? 'pending' : 'paid',
            'payment_method' => 'card',
            'created_at' => Carbon::now(),
            'due_date' => Carbon::now()->addDays(15),
            'notes' => 'Customer requested delivery during regular business hours.',
        ]);

        $mockCustomer = new Customer([
            'name' => 'Sarah Connor',
            'phone' => '+1 (555) 234-5678',
            'email' => 'sarah.connor@example.com',
            'address' => '742 Evergreen Terrace',
            'city' => 'Springfield',
        ]);

        $lines = [
            ['name' => 'Ergonomic Mesh Chair (Black)', 'quantity' => 1, 'unit_price' => 150.00, 'line_total' => 150.00],
            ['name' => 'Wireless Keyboard & Mouse Combo', 'quantity' => 1, 'unit_price' => 45.00, 'line_total' => 45.00],
            ['name' => 'Desk Anti-Glare Light Bar', 'quantity' => 1, 'unit_price' => 25.00, 'line_total' => 25.00],
        ];

        return view('tenant.documents.templates.document', [
            'company' => $company,
            'document' => $mockDoc,
            'customer' => $mockCustomer,
            'lines' => $lines,
            'paperFormat' => $request->input('format', 'a4'),
            'type' => $normType === 'quotation' ? 'quotation' : 'sale',
            'template' => $template,
        ]);
    }

    /**
     * API endpoint: GET /api/v1/tenant/templates/{type}
     */
    public function apiShow(Request $request, string $type): JsonResponse
    {
        $user = auth('sanctum')->user() ?? auth('web')->user() ?? $request->user();
        $this->authorizeTemplateAccess($user, $type);

        $company = $this->resolveCompany($request);
        $normType = $this->normalizeType($type);
        $template = TenantDocumentTemplate::getForCompany($company->id, $normType);

        return response()->json([
            'success' => true,
            'type' => $normType,
            'template' => $template,
            'placeholders' => [
                '{customer_name}' => 'Customer name (e.g. Sarah Connor)',
                '{invoice_number}' => 'Document identifier (e.g. INV-2026-001)',
                '{quotation_number}' => 'Quotation number (e.g. QT-2026-001)',
                '{amount}' => 'Total order amount formatted with currency (e.g. $228.90)',
                '{due_date}' => 'Due / Expiration date (e.g. 05 Oct 2026)',
                '{document_link}' => 'Secure public link to digital invoice / quote',
            ],
        ]);
    }

    /**
     * API endpoint: POST/PUT /api/v1/tenant/templates/{type}
     */
    public function apiUpdate(Request $request, string $type): JsonResponse
    {
        $user = auth('sanctum')->user() ?? auth('web')->user() ?? $request->user();
        $this->authorizeTemplateAccess($user, $type, true);

        $company = $this->resolveCompany($request);
        $normType = $this->normalizeType($type);

        $validated = $request->validate([
            'theme_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logo_placement' => 'nullable|string|in:left,center,right,hidden',
            'header_title' => 'nullable|string|max:120',
            'terms_conditions' => 'nullable|string|max:4000',
            'show_qr_code' => 'nullable|boolean',
            'show_tax_breakup' => 'nullable|boolean',
            'footer_notes' => 'nullable|string|max:1000',
            'send_as_attachment' => 'nullable|boolean',
            'send_text_with_link' => 'nullable|boolean',
            'message_body_template' => 'nullable|string|max:2000',
        ]);
        $current = TenantDocumentTemplate::getForCompany($company->id, $normType);
        $attachPdf = $request->has('send_as_attachment')
            ? $request->boolean('send_as_attachment')
            : (bool) $current->send_as_attachment;

        $template = TenantDocumentTemplate::updateOrCreate(
            ['company_id' => $company->id, 'template_type' => $normType],
            [
                'tenant_id' => $company->id,
                'theme_color' => $validated['theme_color'] ?? $current->theme_color,
                'logo_placement' => $validated['logo_placement'] ?? $current->logo_placement,
                'header_title' => $validated['header_title'] ?? $current->header_title,
                'terms_conditions' => array_key_exists('terms_conditions', $validated) ? $validated['terms_conditions'] : $current->terms_conditions,
                'show_qr_code' => $request->has('show_qr_code') ? $request->boolean('show_qr_code') : $current->show_qr_code,
                'show_tax_breakup' => $request->has('show_tax_breakup') ? $request->boolean('show_tax_breakup') : $current->show_tax_breakup,
                'footer_notes' => array_key_exists('footer_notes', $validated) ? $validated['footer_notes'] : $current->footer_notes,
                'send_as_attachment' => $attachPdf,
                'send_text_with_link' => ! $attachPdf,
                'message_body_template' => array_key_exists('message_body_template', $validated) ? $validated['message_body_template'] : $current->message_body_template,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => ucfirst($normType).' template settings saved successfully.',
            'template' => $template,
        ]);
    }
}
