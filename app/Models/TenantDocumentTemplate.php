<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantDocumentTemplate extends Model
{
    protected $table = 'tenant_document_templates';

    protected $fillable = [
        'company_id',
        'tenant_id',
        'template_type',
        'theme_color',
        'logo_placement',
        'header_title',
        'terms_conditions',
        'show_qr_code',
        'show_tax_breakup',
        'footer_notes',
        'send_as_attachment',
        'send_text_with_link',
        'message_body_template',
    ];

    protected $casts = [
        'show_qr_code' => 'boolean',
        'show_tax_breakup' => 'boolean',
        'send_as_attachment' => 'boolean',
        'send_text_with_link' => 'boolean',
    ];

    public static function defaultTemplate(string $type): array
    {
        if ($type === 'quotation') {
            return [
                'template_type' => 'quotation',
                'theme_color' => '#0284c7',
                'logo_placement' => 'left',
                'header_title' => 'Commercial Quotation',
                'terms_conditions' => "1. Quotation valid for 15 calendar days from the date of issue.\n2. Payment terms: 50% advance, balance on delivery.\n3. Taxes applicable as per prevailing statutory rates.",
                'show_qr_code' => true,
                'show_tax_breakup' => true,
                'footer_notes' => 'Thank you for giving us the opportunity to quote. We look forward to working with you!',
                'send_as_attachment' => true,
                'send_text_with_link' => false,
                'message_body_template' => 'Hello {customer_name}, here is your quotation proposal #{invoice_number} for {amount} (valid until {due_date}). Review online: {document_link}',
            ];
        }

        return [
            'template_type' => 'invoice',
            'theme_color' => '#10b981',
            'logo_placement' => 'left',
            'header_title' => 'Tax Invoice',
            'terms_conditions' => "1. Goods once sold will not be taken back or exchanged.\n2. Invoices are due upon receipt unless otherwise specified.\n3. For electronic payment verification, scan the dynamic QR code.",
            'show_qr_code' => true,
            'show_tax_breakup' => true,
            'footer_notes' => 'Thank you for your business! Please visit us again soon.',
            'send_as_attachment' => true,
            'send_text_with_link' => false,
            'message_body_template' => 'Hello {customer_name}, your order #{invoice_number} total is {amount}. You can view and download your verified digital invoice here: {document_link}',
        ];
    }

    public static function getForCompany($companyId, string $type): self
    {
        $template = self::where('company_id', $companyId)
            ->where('template_type', $type)
            ->first();

        if (! $template) {
            $defaults = self::defaultTemplate($type);
            $defaults['company_id'] = (string) $companyId;
            $defaults['tenant_id'] = (string) $companyId;
            return new self($defaults);
        }

        return $template;
    }

    public function renderMessage(array $variables = []): string
    {
        $body = $this->message_body_template;
        if (empty($body)) {
            $defaults = self::defaultTemplate($this->template_type ?: 'invoice');
            $body = $defaults['message_body_template'];
        }

        $customerName = $variables['customer_name'] ?? 'Valued Customer';
        $invoiceNumber = $variables['invoice_number'] ?? ($variables['order_id'] ?? 'DOC-1001');
        $amount = $variables['amount'] ?? ($variables['total'] ?? '$0.00');
        $dueDate = $variables['due_date'] ?? date('d M Y', strtotime('+15 days'));
        $documentLink = $variables['document_link'] ?? ($variables['link'] ?? '');

        $search = [
            '{customer_name}',
            '{invoice_number}',
            '{quotation_number}',
            '{order_id}',
            '{amount}',
            '{due_date}',
            '{document_link}',
        ];

        $replace = [
            $customerName,
            $invoiceNumber,
            $invoiceNumber,
            $invoiceNumber,
            $amount,
            $dueDate,
            $documentLink,
        ];

        return str_replace($search, $replace, $body);
    }
}
