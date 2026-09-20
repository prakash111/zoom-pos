<?php

namespace App\Livewire\Tenant\Faqs;

use App\Models\Company;
use App\Models\Faq;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.tenant', ['title' => 'Store FAQs & Help Center'])]
class Index extends Component
{
    use WithPagination;

    public string $search = '';
    public string $categoryFilter = 'all';

    // Modal state
    public bool $showModal = false;
    public ?int $editingFaqId = null;

    public string $question = '';
    public string $answer = '';
    public string $category = 'General';
    public int $sort_order = 0;
    public bool $is_active = true;

    protected function rules(): array
    {
        return [
            'question' => ['required', 'string', 'max:500'],
            'answer' => ['required', 'string', 'max:5000'],
            'category' => ['required', 'string', 'max:100'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }

    public function openCreateModal(): void
    {
        $this->reset(['editingFaqId', 'question', 'answer', 'sort_order']);
        $this->category = 'General';
        $this->sort_order = Faq::where('company_id', $this->getCompanyId())->count() + 1;
        $this->is_active = true;
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $faq = Faq::where('company_id', $this->getCompanyId())->findOrFail($id);
        $this->editingFaqId = $faq->id;
        $this->question = $faq->question;
        $this->answer = $faq->answer;
        $this->category = $faq->category ?: 'General';
        $this->sort_order = (int) $faq->sort_order;
        $this->is_active = (bool) $faq->is_active;
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate();

        $companyId = $this->getCompanyId();

        if ($this->editingFaqId) {
            $faq = Faq::where('company_id', $companyId)->findOrFail($this->editingFaqId);
            $faq->update([
                'question' => trim($this->question),
                'answer' => trim($this->answer),
                'category' => trim($this->category),
                'sort_order' => $this->sort_order,
                'is_active' => $this->is_active,
            ]);
            session()->flash('success', __('FAQ updated successfully.'));
        } else {
            Faq::create([
                'company_id' => $companyId,
                'question' => trim($this->question),
                'answer' => trim($this->answer),
                'category' => trim($this->category),
                'sort_order' => $this->sort_order,
                'is_active' => $this->is_active,
            ]);
            session()->flash('success', __('FAQ created successfully and is now active on your storefront.'));
        }

        $this->showModal = false;
        $this->reset(['editingFaqId', 'question', 'answer']);
    }

    public function toggleActive(int $id): void
    {
        $faq = Faq::where('company_id', $this->getCompanyId())->findOrFail($id);
        $faq->update(['is_active' => ! $faq->is_active]);
        session()->flash('success', __('FAQ status toggled.'));
    }

    public function delete(int $id): void
    {
        $faq = Faq::where('company_id', $this->getCompanyId())->findOrFail($id);
        $faq->delete();
        session()->flash('success', __('FAQ deleted.'));
    }

    public function seedDefaults(): void
    {
        $companyId = $this->getCompanyId();
        $existing = Faq::where('company_id', $companyId)->count();
        if ($existing > 0) {
            session()->flash('info', __('Default FAQs already initialized.'));
            return;
        }

        $defaults = [
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

        foreach ($defaults as $d) {
            Faq::create([
                'company_id' => $companyId,
                'question' => $d['question'],
                'answer' => $d['answer'],
                'category' => $d['category'],
                'sort_order' => $d['sort_order'],
                'is_active' => true,
            ]);
        }

        session()->flash('success', __('Default storefront FAQs initialized successfully!'));
    }

    protected function getCompanyId(): string
    {
        $id = app()->bound('tenant.company_id') ? app('tenant.company_id') : auth()->user()?->company_id;
        return (string) ($id ?? Company::first()?->id ?? '');
    }

    public function render()
    {
        $companyId = $this->getCompanyId();

        $query = Faq::where('company_id', $companyId)
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('question', 'like', "%{$this->search}%")
                        ->orWhere('answer', 'like', "%{$this->search}%")
                        ->orWhere('category', 'like', "%{$this->search}%");
                });
            })
            ->when($this->categoryFilter !== 'all', function ($q) {
                $q->where('category', $this->categoryFilter);
            })
            ->ordered();

        $categories = Faq::where('company_id', $companyId)
            ->distinct()
            ->pluck('category')
            ->filter()
            ->values();

        return view('livewire.tenant.faqs.index', [
            'faqs' => $query->paginate(12),
            'categories' => $categories,
            'totalFaqs' => Faq::where('company_id', $companyId)->count(),
            'activeFaqs' => Faq::where('company_id', $companyId)->where('is_active', true)->count(),
        ]);
    }
}
