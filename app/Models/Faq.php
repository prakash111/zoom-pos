<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Faq extends Model
{
    use BelongsToCompany;
    use HasFactory;

    protected $table = 'faqs';

    protected $fillable = [
        'company_id',
        'question',
        'answer',
        'category',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order', 'asc')->orderBy('id', 'asc');
    }

    /**
     * @return array<int, array{question: string, answer: string, category: string, sort_order: int}>
     */
    public static function getDefaultFaqs(): array
    {
        return [
            [
                'question' => 'How can I track my online order in real time?',
                'answer' => 'Every order placed on our store generates an official tracking code (TRK-...). You can use the Track Order link in the top menu or visit /store/track/{code} to view live status updates from packing to dispatch.',
                'category' => 'Delivery',
                'sort_order' => 1,
            ],
            [
                'question' => 'What payment methods are supported at checkout?',
                'answer' => 'We support Cash on Delivery (COD), Pay at Store Counter, secure card checkout powered by Stripe, and instant UPI / NetBanking via Razorpay depending on our current merchant configuration.',
                'category' => 'Payment',
                'sort_order' => 2,
            ],
            [
                'question' => 'What is your product return and exchange policy?',
                'answer' => 'Unopened and unused items with original labels can be returned or exchanged within 7 days of delivery. Please reach out to our store dispatch desk with your order number for swift handling.',
                'category' => 'Returns',
                'sort_order' => 3,
            ],
            [
                'question' => 'How do home delivery dispatches work?',
                'answer' => 'Orders are packaged and dispatched directly from our store counter. You will receive WhatsApp and SMS alerts with your driver details as soon as your items are on the way.',
                'category' => 'Delivery',
                'sort_order' => 4,
            ],
            [
                'question' => 'Can I cancel an order after placing it?',
                'answer' => 'Orders in "Placed" or "Confirmed" status can be cancelled from your Customer Account Portal or by contacting our team via WhatsApp prior to driver dispatch.',
                'category' => 'Orders',
                'sort_order' => 5,
            ],
        ];
    }

    public static function seedDefaultsForCompany(string $companyId): void
    {
        if (static::withoutGlobalScopes()->where('company_id', $companyId)->exists()) {
            return;
        }

        foreach (static::getDefaultFaqs() as $item) {
            static::withoutGlobalScopes()->create([
                'company_id' => $companyId,
                'question' => $item['question'],
                'answer' => $item['answer'],
                'category' => $item['category'],
                'sort_order' => $item['sort_order'],
                'is_active' => true,
            ]);
        }
    }
}

