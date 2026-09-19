<?php

namespace App\Livewire\Tenant\Quotes;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\TaxRule;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\leadmanagement\Models\LeadActivity;

#[Layout('layouts.tenant', ['title' => 'New Quotation'])]
class Create extends Component
{
    public ?int $customerId = null;

    public ?int $leadId = null;

    public ?string $userId = null;

    public string $discountType = 'fixed'; // fixed | percent

    public float $discountValue = 0;

    public float $taxPercent = 10.0;

    public ?string $selectedTaxRuleId = null;

    public string $taxName = 'Tax';

    public array $availableTaxRules = [];

    public bool $isTaxExempt = false;

    public string $quoteNumber = '';

    public string $status = 'draft'; // draft|sent

    public string $paymentMethod = 'cash'; // cash, card, card_credit, pix, boleto, transfer

    public string $agreedPaymentMethod = 'cash';

    public $availablePaymentMethods;

    public string $notes = '';

    public string $quoteNotes = '';

    public ?string $dueDate = null;

    public string $paymentTerms = 'Due on Receipt'; // Due on Receipt, Net 7, Net 15, Net 30, Net 60, Custom

    // Quick Customer Creation
    public bool $showQuickCustomerModal = false;

    public string $newCustomerName = '';

    public string $newCustomerPhone = '';

    public string $newCustomerEmail = '';

    public string $newCustomerCity = '';

    public string $newCustomerDocument = '';

    /** @var array<int, array{product_id: ?int, name: string, description: string, quantity: float, price: float}> */
    public array $items = [];

    public function mount(): void
    {
        $company = auth('web')->user()?->company;
        $companyId = app()->bound('tenant.company_id')
            ? app('tenant.company_id')
            : $company?->id;

        if ($companyId) {
            PaymentMethod::getForCompany($companyId);
        }

        $this->availablePaymentMethods = PaymentMethod::where('is_active', true)
            ->orderBy('name')
            ->get();

        $prefix = trim((string) ($company?->quotation_prefix ?: 'QUO-'));
        if ($prefix === '' || strlen($prefix) > 8) {
            $prefix = 'QUO-';
        }
        $prefix = str_ends_with($prefix, '-') ? $prefix : $prefix.'-';
        $count = Sale::where('operation_type', 'quotation')->count() + 1;
        $this->quoteNumber = $prefix.sprintf('%03d', $count);

        $this->availableTaxRules = TaxRule::query()
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->where('active', true)
            ->orderByDesc('is_default')
            ->orderBy('priority')
            ->orderBy('tax_name')
            ->get()
            ->map(fn (TaxRule $rule) => [
                'id' => (string) $rule->id,
                'name' => $rule->tax_name,
                'rate' => (float) $rule->rate,
                'is_default' => (bool) $rule->is_default,
                'is_inclusive' => (bool) $rule->is_inclusive,
                'sub_components' => $rule->sub_components ?: [],
            ])
            ->all();

        $defaultRule = collect($this->availableTaxRules)->firstWhere('is_default', true)
            ?? collect($this->availableTaxRules)->first();

        if ($defaultRule) {
            $this->applyTaxRule($defaultRule);
        } else {
            $this->taxPercent = 0.0;
            $this->taxName = 'None';
        }
        $this->dueDate = now()->addDays(15)->format('Y-m-d');
        $this->userId = (string) auth('web')->id();

        $defaultTerms = (string) ($company?->quote_terms ?? tenant_setting('quote_default_terms', ''));
        $this->notes = $defaultTerms;
        $this->quoteNotes = $defaultTerms;

        $this->agreedPaymentMethod = $this->availablePaymentMethods->first()?->code ?? 'cash';
        $this->paymentMethod = $this->agreedPaymentMethod;

        if ($leadIdQuery = request()->query('lead_id')) {
            abort_unless($company?->hasModule('leadmanagement'), 403, 'Lead Management is not activated for this store.');
            $lead = Lead::find($leadIdQuery);
            if ($lead) {
                $this->leadId = $lead->id;
                if ($lead->customer_id) {
                    $this->customerId = $lead->customer_id;
                }
                if ($lead->assigned_to) {
                    $this->userId = (string) $lead->assigned_to;
                }
                if ($lead->requirement_summary || $lead->notes) {
                    $this->notes = $lead->notes ?: $lead->requirement_summary;
                    $this->quoteNotes = $this->notes;
                }
                $amount = (float) ($lead->expected_value ?: $lead->estimated_value ?: 0);
                $this->items = [
                    [
                        'product_id' => null,
                        'name' => $lead->title ?: ($lead->name.' - Scope of Work'),
                        'description' => $lead->requirement_summary ?: ($lead->notes ?: ''),
                        'quantity' => 1,
                        'price' => $amount,
                    ],
                ];
            } else {
                $this->addItem();
            }
        } else {
            $this->addItem();
        }
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

    public function updatedSelectedTaxRuleId($ruleId): void
    {
        $rule = collect($this->availableTaxRules)
            ->first(fn (array $candidate) => (string) $candidate['id'] === (string) $ruleId);

        if ($rule) {
            $this->applyTaxRule($rule);

            return;
        }

        $this->selectedTaxRuleId = null;
        $this->taxPercent = 0.0;
        $this->taxName = 'None';
    }

    private function applyTaxRule(array $rule): void
    {
        $this->selectedTaxRuleId = (string) $rule['id'];
        $this->taxPercent = (float) $rule['rate'];
        $this->taxName = (string) $rule['name'];
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

        $selectedRule = collect($this->availableTaxRules)
            ->first(fn (array $rule) => (string) $rule['id'] === (string) $this->selectedTaxRuleId);
        $components = collect($selectedRule['sub_components'] ?? [])
            ->map(fn (array $component) => [
                'name' => $component['name'] ?? $this->taxName,
                'rate' => (float) ($component['rate'] ?? 0),
                'amount' => round(($taxableAmount * (float) ($component['rate'] ?? 0)) / 100, 2),
            ])
            ->values()
            ->all();

        if (empty($components)) {
            $components[] = [
                'name' => $this->taxName,
                'rate' => (float) $this->taxPercent,
                'amount' => round($this->tax, 2),
            ];
        }

        return [
            [
                'tax_name' => $this->taxName,
                'rate' => (float) $this->taxPercent,
                'is_inclusive' => (bool) ($selectedRule['is_inclusive'] ?? false),
                'taxable_amount' => round($taxableAmount, 2),
                'tax_amount' => round($this->tax, 2),
                'components' => $components,
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

        if ($this->leadId) {
            abort_unless(Company::find($companyId)?->hasModule('leadmanagement'), 403, 'Lead Management is not activated for this store.');
        }

        $quote = Sale::create([
            'company_id' => $companyId,
            'sale_number' => $this->quoteNumber,
            'customer_id' => $customer?->id,
            'lead_id' => $this->leadId,
            'customer_name' => $customer?->name ?? 'Client Proposal',
            'user_id' => $this->userId ?: auth('web')->id(),
            'total' => $this->total,
            'discount' => $this->calculatedDiscount,
            'tax_amount' => $this->tax,
            'tax_name' => $this->taxName,
            'tax_rate' => (float) $this->taxPercent,
            'tax_breakdown' => $this->taxSummaryTable,
            'status' => $this->status,
            'payment_method' => $paymentMethodToSave,
            'agreed_payment_method' => $paymentMethodToSave,
            'operation_type' => 'quotation',
            'notes' => $notesToSave,
            'due_date' => $this->dueDate ?: null,
            'payment_terms' => $this->paymentTerms ?: null,
            'items' => $this->items,
        ]);

        if ($this->leadId) {
            $lead = Lead::find($this->leadId);
            if ($lead) {
                $lead->update(['stage' => 'proposal_sent']);
                LeadActivity::create([
                    'company_id' => $companyId,
                    'lead_id' => $lead->id,
                    'type' => 'note',
                    'title' => 'Quotation Created',
                    'description' => "Quotation #{$quote->sale_number} sent to prospect.",
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
            }
        }

        AuditLog::record('quotation.created', $quote->company_id, auth('web')->id(), ['quote_id' => $quote->id, 'quote_number' => $quote->sale_number, 'lead_id' => $this->leadId]);

        session()->flash('status', "Quotation {$quote->sale_number} created successfully.");

        $this->redirectRoute('tenant.quotes.show', $quote, navigate: true);
    }

    public function render()
    {
        $companyId = app()->bound('tenant.company_id') ? app('tenant.company_id') : auth('web')->user()?->company_id;

        $methods = PaymentMethod::where('is_active', true)->orderBy('name')->get();
        if ($methods->isEmpty() && $companyId) {
            $methods = PaymentMethod::getForCompany($companyId);
        }

        return view('livewire.tenant.quotes.create', [
            'customers' => Customer::where('company_id', $companyId)->orderBy('name')->get(),
            'products' => Product::where('company_id', $companyId)->where('active', true)->orderBy('name')->get(),
            'users' => User::where('company_id', $companyId)->orderBy('name')->get(),
            'availablePaymentMethods' => $methods,
        ]);
    }
}
