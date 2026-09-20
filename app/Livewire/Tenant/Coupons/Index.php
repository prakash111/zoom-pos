<?php

namespace App\Livewire\Tenant\Coupons;

use App\Models\Company;
use App\Models\Coupon;
use App\Models\CouponUsage;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.tenant', ['title' => 'Coupon Codes & Discounts'])]
class Index extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = 'all';

    // Create / Edit Modal state
    public bool $showModal = false;
    public ?int $editingCouponId = null;

    public string $code = '';
    public string $discount_type = 'percentage';
    public float $discount_value = 10.0;
    public float $min_order_amount = 0.0;
    public ?float $max_discount_amount = null;
    public ?int $usage_limit_total = null;
    public int $usage_limit_per_customer = 1;
    public ?string $starts_at = null;
    public ?string $expires_at = null;
    public bool $is_active = true;
    public string $description = '';

    protected function getCompanyId(): string
    {
        $id = app()->bound('tenant.company_id') ? app('tenant.company_id') : auth()->user()?->company_id;
        return (string) ($id ?? Company::first()?->id ?? '');
    }

    protected function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50'],
            'discount_type' => ['required', 'in:percentage,fixed_amount'],
            'discount_value' => ['required', 'numeric', 'min:0.01'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit_total' => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_customer' => ['required', 'integer', 'min:1'],
            'starts_at' => ['nullable'],
            'expires_at' => ['nullable', function ($attribute, $value, $fail) {
                if (filled($value) && filled($this->starts_at)) {
                    if (Carbon::parse($value)->lt(Carbon::parse($this->starts_at))) {
                        $fail(__('The expiration date must be on or after the start date.'));
                    }
                }
            }],
            'is_active' => ['boolean'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function openCreateModal(): void
    {
        $this->reset(['editingCouponId', 'code', 'discount_value', 'min_order_amount', 'max_discount_amount', 'usage_limit_total', 'starts_at', 'expires_at', 'description']);
        $this->discount_type = 'percentage';
        $this->discount_value = 10.0;
        $this->usage_limit_per_customer = 1;
        $this->is_active = true;
        $this->showModal = true;
    }

    public function openEditModal(int $couponId): void
    {
        $companyId = $this->getCompanyId();
        $coupon = Coupon::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->findOrFail($couponId);

        $this->editingCouponId = $coupon->id;
        $this->code = $coupon->code;
        $this->discount_type = $coupon->discount_type;
        $this->discount_value = (float) $coupon->discount_value;
        $this->min_order_amount = (float) $coupon->min_order_amount;
        $this->max_discount_amount = $coupon->max_discount_amount ? (float) $coupon->max_discount_amount : null;
        $this->usage_limit_total = $coupon->usage_limit_total;
        $this->usage_limit_per_customer = $coupon->usage_limit_per_customer;
        $this->starts_at = $coupon->starts_at ? $coupon->starts_at->format('Y-m-d\TH:i') : null;
        $this->expires_at = $coupon->expires_at ? $coupon->expires_at->format('Y-m-d\TH:i') : null;
        $this->is_active = (bool) $coupon->is_active;
        $this->description = $coupon->description ?? '';

        $this->showModal = true;
    }

    public function saveCoupon(): void
    {
        $this->validate();

        $companyId = $this->getCompanyId();
        $cleanCode = strtoupper(trim($this->code));

        $exists = Coupon::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('code', $cleanCode)
            ->when($this->editingCouponId, fn ($q) => $q->where('id', '!=', $this->editingCouponId))
            ->exists();

        if ($exists) {
            $this->addError('code', __('A coupon with code ":code" already exists.', ['code' => $cleanCode]));
            return;
        }

        $data = [
            'company_id' => $companyId,
            'code' => $cleanCode,
            'discount_type' => $this->discount_type,
            'discount_value' => (float) $this->discount_value,
            'min_order_amount' => (float) ($this->min_order_amount ?: 0),
            'max_discount_amount' => filled($this->max_discount_amount) ? (float) $this->max_discount_amount : null,
            'usage_limit_total' => filled($this->usage_limit_total) ? (int) $this->usage_limit_total : null,
            'usage_limit_per_customer' => (int) ($this->usage_limit_per_customer ?: 1),
            'starts_at' => filled($this->starts_at) ? Carbon::parse($this->starts_at) : null,
            'expires_at' => filled($this->expires_at) ? Carbon::parse($this->expires_at) : null,
            'is_active' => (bool) $this->is_active,
            'description' => $this->description ?: null,
        ];

        if ($this->editingCouponId) {
            Coupon::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('id', $this->editingCouponId)
                ->update($data);
            session()->flash('status', __('Coupon updated successfully.'));
        } else {
            Coupon::create($data);
            session()->flash('status', __('New coupon created successfully.'));
        }

        $this->showModal = false;
        $this->reset(['editingCouponId', 'code', 'description']);
    }

    public function toggleStatus(int $couponId): void
    {
        $companyId = $this->getCompanyId();
        $coupon = Coupon::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->findOrFail($couponId);

        $coupon->update(['is_active' => ! $coupon->is_active]);
        session()->flash('status', __('Coupon status toggled.'));
    }

    public function deleteCoupon(int $couponId): void
    {
        $companyId = $this->getCompanyId();
        Coupon::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('id', $couponId)
            ->delete();

        session()->flash('status', __('Coupon deleted successfully.'));
    }

    public function render()
    {
        $companyId = $this->getCompanyId();

        $baseQuery = Coupon::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('code', 'like', '%'.$this->search.'%')
                        ->orWhere('description', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->statusFilter === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->statusFilter === 'inactive', fn ($q) => $q->where('is_active', false));

        $coupons = (clone $baseQuery)->orderByDesc('id')->paginate(15);

        $stats = [
            'total_active' => Coupon::withoutGlobalScopes()->where('company_id', $companyId)->where('is_active', true)->count(),
            'total_used' => CouponUsage::withoutGlobalScopes()->where('company_id', $companyId)->count(),
            'total_discount_given' => CouponUsage::withoutGlobalScopes()->where('company_id', $companyId)->sum('discount_amount'),
        ];

        return view('livewire.tenant.coupons.index', [
            'coupons' => $coupons,
            'stats' => $stats,
        ]);
    }
}
