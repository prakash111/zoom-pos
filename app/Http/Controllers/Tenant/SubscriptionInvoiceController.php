<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\PlatformBranding;
use App\Models\SubscriptionInvoice;

class SubscriptionInvoiceController extends Controller
{
    /**
     * Printable/PDF-renderable Tax Invoice for a store subscription.
     */
    public function pdf(SubscriptionInvoice $invoice)
    {
        $companyId = app()->bound('tenant.company_id')
            ? app('tenant.company_id')
            : auth('web')->user()?->company_id;

        if ($companyId && $invoice->company_id !== $companyId) {
            abort(403, 'Unauthorized access to this tax invoice.');
        }

        $company = $invoice->company ?: Company::find($invoice->company_id);
        $branding = PlatformBranding::current();

        return view('documents.subscription-tax-invoice', [
            'invoice' => $invoice,
            'company' => $company,
            'branding' => $branding,
        ]);
    }
}
