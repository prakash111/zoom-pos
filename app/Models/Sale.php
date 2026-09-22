<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\BelongsToStore;
use App\Models\Concerns\TracksSyncState;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use BelongsToCompany;
    use BelongsToStore;
    use TracksSyncState;

    protected $fillable = [
        'company_id', 'store_id', 'external_id', 'sale_number', 'tracking_code', 'customer_id', 'lead_id', 'customer_name', 'user_id', 'cash_register_id',
        'total', 'net_amount', 'discount', 'payment_method', 'agreed_payment_method', 'installments', 'status', 'is_demo', 'items', 'operation_type', 'gst_invoice',
        'service_type', 'dining_table_id', 'table_name', 'guest_count', 'pickup_time',
        'delivery_address', 'driver_name', 'driver_phone', 'dispatch_status', 'kot_status',
        'notes', 'paid_amount', 'due_amount', 'due_date', 'due_reminder_at',
        'due_reminder_sent_at', 'due_reminder_dismissed_at', 'payment_status',
        'payment_terms', 'terms', 'commission_rate', 'commission_type', 'commission_amount',
        'merchant_fee_percentage', 'merchant_fee_amount',
        'tax_amount', 'tax_name', 'tax_rate', 'tax_breakdown',
        'module_type', 'reference_ticket_id', 'doctor_name', 'stylist_ids',
        'einvoice_status', 'einvoice_irn', 'einvoice_qr', 'einvoice_signed_payload',
    ];

    protected function casts(): array
    {
        return [
            'is_demo' => 'boolean',
            'lead_id' => 'integer',
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
            'due_reminder_at' => 'datetime',
            'due_reminder_sent_at' => 'datetime',
            'due_reminder_dismissed_at' => 'datetime',
            'commission_rate' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'reference_ticket_id' => 'integer',
            'stylist_ids' => 'array',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id');
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

    public function tenant()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function saleItems()
    {
        return $this->hasMany(SaleItem::class, 'sale_id');
    }

    public function items()
    {
        return $this->hasMany(SaleItem::class, 'sale_id');
    }

    public function getTenantIdAttribute(): ?string
    {
        return (string) ($this->company_id ?? '');
    }

    public function cashRegister()
    {
        return $this->belongsTo(CashRegister::class, 'cash_register_id');
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

    public function newEloquentBuilder($query): SaleBuilder
    {
        return new SaleBuilder($query);
    }

    public function getInvoiceNumberAttribute(): ?string
    {
        return $this->sale_number ?: (string) $this->id;
    }

    public function getBalanceDueAttribute(): float
    {
        return (float) ($this->due_amount ?? $this->total ?? 0);
    }

    public function getCustomerPhoneAttribute(): ?string
    {
        return $this->customer?->phone;
    }

    public function getQuotationNumberAttribute(): ?string
    {
        return $this->sale_number ?: (string) $this->id;
    }

    public function couponUsage(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(CouponUsage::class, 'sale_id');
    }

    protected static function booted(): void
    {
        static::creating(function (Sale $sale) {
            if (empty($sale->tracking_code)) {
                $sale->tracking_code = 'TRK-' . strtoupper(\Illuminate\Support\Str::random(8));
            }
        });
    }

    /**
     * Map sale status to normalized tracking stages:
     * Placed -> Confirmed -> Preparing -> Out for Delivery -> Delivered (or Cancelled)
     */
    public function getTrackingStatus(): string
    {
        $status = strtolower($this->status ?? 'pending');
        $dispatch = strtolower($this->dispatch_status ?? '');

        if (in_array($status, ['cancelled', 'canceled', 'void', 'refunded'], true)) {
            return 'Cancelled';
        }

        if (in_array($dispatch, ['delivered', 'completed'], true) || in_array($status, ['delivered', 'completed'], true)) {
            return 'Delivered';
        }

        if (in_array($dispatch, ['out_for_delivery', 'dispatched', 'in_transit', 'on_way'], true)) {
            return 'Out for Delivery';
        }

        if (in_array($dispatch, ['preparing', 'packing', 'processing', 'in_kitchen'], true) || in_array($status, ['processing', 'preparing'], true)) {
            return 'Preparing';
        }

        if (in_array($status, ['confirmed', 'accepted', 'approved'], true) || in_array($dispatch, ['confirmed', 'assigned'], true)) {
            return 'Confirmed';
        }

        return 'Placed';
    }
}
