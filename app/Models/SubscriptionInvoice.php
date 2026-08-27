<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasLegacyStringId;
use Illuminate\Database\Eloquent\Model;

class SubscriptionInvoice extends Model
{
    use BelongsToCompany, HasLegacyStringId;

    protected $fillable = [
        'company_id', 'subscription_id', 'invoice_number', 'plan_name',
        'billing_cycle', 'currency', 'subtotal', 'tax_rate', 'tax_amount',
        'tax_type', 'tax_breakdown', 'total', 'payment_method', 'payment_reference',
        'status', 'invoice_date', 'due_date', 'paid_at', 'seller_details',
        'buyer_details', 'pdf_path', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'tax_breakdown' => 'array',
            'seller_details' => 'array',
            'buyer_details' => 'array',
            'invoice_date' => 'date',
            'due_date' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    public function idPrefix(): string
    {
        return 'sinv_';
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class, 'plan_name', 'name');
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function getFormattedTotal(): string
    {
        return number_format((float) $this->total, 2);
    }

    public function getFormattedSubtotal(): string
    {
        return number_format((float) $this->subtotal, 2);
    }

    public function getFormattedTax(): string
    {
        return number_format((float) $this->tax_amount, 2);
    }
}
