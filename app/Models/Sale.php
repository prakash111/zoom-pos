<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\TracksSyncState;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use BelongsToCompany;
    use TracksSyncState;

    protected $fillable = [
        'company_id', 'external_id', 'sale_number', 'customer_id', 'customer_name', 'user_id',
        'total', 'net_amount', 'discount', 'payment_method', 'agreed_payment_method', 'installments', 'status', 'items', 'operation_type', 'gst_invoice',
        'service_type', 'dining_table_id', 'table_name', 'guest_count', 'pickup_time',
        'delivery_address', 'driver_name', 'driver_phone', 'dispatch_status', 'kot_status',
        'notes', 'paid_amount', 'due_amount', 'due_date', 'payment_status',
        'payment_terms', 'terms', 'commission_rate', 'commission_amount',
        'merchant_fee_percentage', 'merchant_fee_amount',
        'tax_amount', 'tax_name', 'tax_rate', 'tax_breakdown',
        'einvoice_status', 'einvoice_irn', 'einvoice_qr', 'einvoice_signed_payload',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'discount' => 'decimal:2',
            'merchant_fee_percentage' => 'decimal:2',
            'merchant_fee_amount' => 'decimal:2',
            'installments' => 'integer',
            'tax_amount' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_breakdown' => 'array',
            'guest_count' => 'integer',
            'items' => 'array',
            'gst_invoice' => 'array',
            'paid_amount' => 'decimal:2',
            'due_amount' => 'decimal:2',
            'due_date' => 'date',
            'commission_rate' => 'decimal:2',
            'commission_amount' => 'decimal:2',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function table()
    {
        return $this->belongsTo(DiningTable::class, 'dining_table_id');
    }

    public function kitchenTickets()
    {
        return $this->hasMany(KitchenTicket::class, 'sale_id');
    }

    public function payments()
    {
        return $this->hasMany(OrderPayment::class, 'sale_id')->orderByDesc('created_at');
    }

    public function getTermsAttribute(): ?string
    {
        return $this->attributes['terms'] ?? ($this->attributes['payment_terms'] ?? null);
    }

    public function setTermsAttribute(?string $value): void
    {
        $this->attributes['terms'] = $value;
    }

    public function getSubtotalAttribute(): float
    {
        $items = is_array($this->items) ? $this->items : (json_decode($this->items ?? '', true) ?: []);
        $calcSub = 0.0;
        foreach ($items as $item) {
            $calcSub += isset($item['taxable_amount'])
                ? (float) $item['taxable_amount']
                : ((float) ($item['quantity'] ?? 1) * (float) ($item['price'] ?? 0));
        }

        if ($calcSub > 0) {
            return round($calcSub, 2);
        }

        $tax = (float) ($this->tax_amount ?? ($this->tax ?? 0));
        $discount = (float) ($this->discount ?? 0);
        $total = (float) ($this->total ?? 0);

        return round(max(0, $total - $tax + $discount), 2);
    }

    public function getDiscountAmountAttribute(): float
    {
        return (float) ($this->discount ?? 0);
    }

    public function getTotalAmountAttribute(): float
    {
        return (float) ($this->total ?? 0);
    }

    /**
     * Get a flattened list of individual tax components (e.g., CGST 9%, SGST 9%, or GST 18%).
     *
     * @return array<int, array{name: string, rate: float, amount: float, taxable_amount?: float}>
     */
    public function getFlattenedTaxComponentsAttribute(): array
    {
        $breakdown = $this->tax_breakdown;
        if (is_string($breakdown)) {
            $breakdown = json_decode($breakdown, true);
        }

        $components = [];

        if (! empty($breakdown) && is_array($breakdown)) {
            foreach ($breakdown as $row) {
                if (! empty($row['components']) && is_array($row['components']) && count($row['components']) > 1) {
                    foreach ($row['components'] as $comp) {
                        $components[] = [
                            'name' => $comp['name'] ?? ($row['tax_name'] ?? 'Tax'),
                            'rate' => (float) ($comp['rate'] ?? 0),
                            'amount' => (float) ($comp['amount'] ?? 0),
                            'taxable_amount' => (float) ($row['taxable_amount'] ?? 0),
                        ];
                    }
                } else {
                    $components[] = [
                        'name' => $row['tax_name'] ?? 'Tax',
                        'rate' => (float) ($row['rate'] ?? 0),
                        'amount' => (float) ($row['tax_amount'] ?? 0),
                        'taxable_amount' => (float) ($row['taxable_amount'] ?? 0),
                    ];
                }
            }
        }

        // Fallback if tax_amount > 0 but tax_breakdown array was not structured
        if (empty($components) && (float) ($this->tax_amount ?? 0) > 0) {
            $components[] = [
                'name' => $this->tax_name ?: 'Tax',
                'rate' => (float) ($this->tax_rate ?? 0),
                'amount' => (float) $this->tax_amount,
                'taxable_amount' => $this->subtotal,
            ];
        }

        return $components;
    }
}
