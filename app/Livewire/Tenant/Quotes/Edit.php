<?php

namespace App\Livewire\Tenant\Quotes;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.tenant', ['title' => 'Edit Quotation'])]
class Edit extends Component
{
    public Sale $quote;

    public ?int $customerId = null;

    public ?string $userId = null;

    public string $discountType = 'fixed'; // fixed | percent

    public float $discountValue = 0;

    public float $taxPercent = 10.0;

    public bool $isTaxExempt = false;

    public string $quoteNumber = '';

    public string $status = 'draft';

    public string $paymentMethod = 'cash';

    public string $agreedPaymentMethod = 'cash';

    public $availablePaymentMethods;

    public string $notes = '';

    public string $quoteNotes = '';

    public ?string $dueDate = null;

    public string $paymentTerms = 'Due on Receipt';

    // Quick Customer Creation
    public bool $showQuickCustomerModal = false;

    public string $newCustomerName = '';

    public string $newCustomerPhone = '';

    public string $newCustomerEmail = '';

    public string $newCustomerCity = '';

    public string $newCustomerDocument = '';

    /** @var array<int, array{product_id: ?int, name: string, description: string, quantity: float, price: float}> */
    public array $items = [];

    public function mount(Sale $quote): void
    {
        $this->quote = $quote;
        $company = $quote->company ?? auth('web')->user()?->company;
        $companyId = app()->bound('tenant.company_id')
            ? app('tenant.company_id')
            : ($quote->company_id ?? $company?->id);

        if ($companyId) {
            PaymentMethod::getForCompany($companyId);
        }

        $this->availablePaymentMethods = PaymentMethod::where('is_active', true)
            ->orderBy('name')
            ->get();

        $this->quoteNumber = (string) $quote->sale_number;
        $this->customerId = $quote->customer_id;
        $this->userId = $quote->user_id ?: (string) auth('web')->id();
        $this->status = $quote->status ?: 'draft';
        $this->agreedPaymentMethod = (string) ($quote->agreed_payment_method ?: ($quote->payment_method ?: ($this->availablePaymentMethods->first()?->code ?? 'cash')));
        $this->paymentMethod = $this->agreedPaymentMethod;
        $this->paymentTerms = (string) ($quote->payment_terms ?: 'Due on Receipt');
        $defaultTerms = (string) ($company?->quote_terms ?? tenant_setting('quote_default_terms', ''));
        $this->notes = filled($quote->notes) ? (string) $quote->notes : $defaultTerms;
        $this->quoteNotes = $this->notes;
        $this->dueDate = $quote->due_date ? $quote->due_date->format('Y-m-d') : null;
        $this->discountValue = (float) $quote->discount;
        $this->discountType = 'fixed';

        $defaultTax = 10.0;
        if (! empty($company?->tax_settings['default_tax_rate'])) {
            $defaultTax = (float) $company->tax_settings['default_tax_rate'];
        }
        $this->taxPercent = $defaultTax;

        $items = [];
        foreach ($quote->items ?? [] as $item) {
            $items[] = [
                'product_id' => $item['product_id'] ?? null,
                'name' => $item['name'] ?? '',
                'description' => $item['description'] ?? '',
                'quantity' => (float) ($item['quantity'] ?? 1),
                'price' => (float) ($item['price'] ?? 0),
            ];
        }

        if (empty($items)) {
            $items[] = ['product_id' => null, 'name' => '', 'description' => '', 'quantity' => 1, 'price' => 0];
        }

        $this->items = $items;
    }

    public function addItem(): void
    {
        $this->items[] = ['product_id' => null, 'name' => '', 'description' => '', 'quantity' => 1, 'price' => 0];
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function updatedItems($value, $key): void
    {
        if (str_ends_with($key, '.product_id')) {
            $index = (int) explode('.', $key)[0];
            $product = Product::find($this->items[$index]['product_id']);
            if ($product) {
                $this->items[$index]['name'] = $product->name;
                $this->items[$index]['price'] = (float) $product->sale_price;
            }
        }
    }

    public function getSubtotalProperty(): float
    {
        return collect($this->items)
            ->filter(fn ($item) => ! empty($item['product_id']) || ! empty($item['name']))
            ->sum(fn ($item) => (float) $item['quantity'] * (float) $item['price']);
    }

    public function getCalculatedDiscountProperty(): float
    {
        if ($this->discountType === 'percent') {
            return round(($this->subtotal * min(100, max(0, $this->discountValue))) / 100, 2);
        }

        return min($this->subtotal, max(0, $this->discountValue));
    }

    public function getTaxProperty(): float
    {
        if ($this->isTaxExempt) {
            return 0.0;
        }

        $taxableAmount = max(0, $this->subtotal - $this->calculatedDiscount);

        return round(($taxableAmount * max(0, $this->taxPercent)) / 100, 2);
    }

    public function getTaxSummaryTableProperty(): array
    {
        if ($this->isTaxExempt || (float) $this->tax <= 0) {
            return [];
        }

        $taxableAmount = max(0, $this->subtotal - $this->calculatedDiscount);

        return [
            [
                'tax_name' => "Tax ({$this->taxPercent}%)",
                'rate' => (float) $this->taxPercent,
                'is_inclusive' => false,
                'taxable_amount' => round($taxableAmount, 2),
                'tax_amount' => round($this->tax, 2),
                'components' => [
                    [
                        'name' => 'Tax',
                        'rate' => (float) $this->taxPercent,
                        'amount' => round($this->tax, 2),
                    ],
                ],
            ],
        ];
    }

    public function getTotalProperty(): float
    {
        return max(0, $this->subtotal - $this->calculatedDiscount + $this->tax);
    }

    public function createQuickCustomer(): void
    {
        $this->validate([
            'newCustomerName' => ['required', 'string', 'max:255'],
            'newCustomerEmail' => ['nullable', 'email'],
            'newCustomerPhone' => ['nullable', 'string', 'max:50'],
            'newCustomerDocument' => ['nullable', 'string', 'max:50'],
        ]);

        $companyId = app()->bound('tenant.company_id')
            ? app('tenant.company_id')
            : auth('web')->user()?->company_id;

        $customer = Customer::create([
            'company_id' => $companyId,
            'name' => $this->newCustomerName,
            'phone' => $this->newCustomerPhone ?: null,
            'email' => $this->newCustomerEmail ?: null,
            'city' => $this->newCustomerCity ?: null,
            'document' => $this->newCustomerDocument ?: null,
            'loyalty_points' => 0,
        ]);

        $this->customerId = $customer->id;
        $this->showQuickCustomerModal = false;
        $this->reset(['newCustomerName', 'newCustomerPhone', 'newCustomerEmail', 'newCustomerCity', 'newCustomerDocument']);
    }

    public function updatedAgreedPaymentMethod($value): void
    {
        $this->paymentMethod = (string) $value;
    }

    public function updatedPaymentMethod($value): void
    {
        $this->agreedPaymentMethod = (string) $value;
    }

    public function updatedQuoteNotes($value): void
    {
        $this->notes = (string) $value;
    }

    public function updatedNotes($value): void
    {
        $this->quoteNotes = (string) $value;
    }

    public function save()
    {
        $companyId = app()->bound('tenant.company_id')
            ? app('tenant.company_id')
            : (auth('web')->user()?->company_id ?? auth('tenant_api')->user()?->company_id);

        $cleanItems = collect($this->items)->filter(fn ($item) => ! empty($item['product_id']) || ! empty($item['name']))->values()->all();
        if (! empty($cleanItems)) {
            $this->items = $cleanItems;
        }

        $this->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'discountValue' => ['numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'quoteNotes' => ['nullable', 'string', 'max:10000'],
            'dueDate' => ['nullable', 'date'],
            'paymentTerms' => ['nullable', 'string', 'max:50'],
        ]);

        $customer = $this->customerId ? Customer::find($this->customerId) : null;
        $paymentMethodToSave = $this->agreedPaymentMethod ?: ($this->paymentMethod ?: 'cash');
        $notesToSave = filled($this->quoteNotes) ? $this->quoteNotes : ($this->notes ?: null);

        $this->quote->update([
            'customer_id' => $customer?->id,
            'customer_name' => $customer?->name ?? ($this->quote->customer_name ?: 'Client Proposal'),
            'user_id' => $this->userId ?: auth('web')->id(),
            'total' => $this->total,
            'discount' => $this->calculatedDiscount,
            'tax_amount' => $this->tax,
            'tax_name' => 'Tax',
            'tax_rate' => (float) $this->taxPercent,
            'tax_breakdown' => $this->taxSummaryTable,
            'status' => $this->status,
            'payment_method' => $paymentMethodToSave,
            'agreed_payment_method' => $paymentMethodToSave,
            'notes' => $notesToSave,
            'due_date' => $this->dueDate ?: null,
            'payment_terms' => $this->paymentTerms ?: null,
            'items' => $this->items,
        ]);

        AuditLog::record('quotation.updated', $this->quote->company_id, auth('web')->id(), [
            'quote_id' => $this->quote->id,
            'quote_number' => $this->quote->sale_number,
        ]);

        session()->flash('status', "Quotation {$this->quote->sale_number} updated successfully.");

        $this->redirectRoute('tenant.quotes.show', $this->quote, navigate: true);
    }

    public function render()
    {
        $companyId = app()->bound('tenant.company_id') ? app('tenant.company_id') : ($this->quote->company_id ?? auth('web')->user()?->company_id);

        $methods = PaymentMethod::where('is_active', true)->orderBy('name')->get();
        if ($methods->isEmpty() && $companyId) {
            $methods = PaymentMethod::getForCompany($companyId);
        }

        return view('livewire.tenant.quotes.edit', [
            'customers' => Customer::where('company_id', $companyId)->orderBy('name')->get(),
            'products' => Product::where('company_id', $companyId)->where('active', true)->orderBy('name')->get(),
            'users' => User::where('company_id', $companyId)->orderBy('name')->get(),
            'availablePaymentMethods' => $methods,
        ]);
    }
}
