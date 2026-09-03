<?php

namespace App\Livewire\Tenant\Financials;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\CustomNotificationChannel;
use App\Models\OrderPayment;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Services\Delivery\MessageQueueService;
use App\Services\Delivery\WebhookDispatchService;
use App\Services\Financial\CustomerLedgerService;
use App\Services\Invoice\InvoiceDeliveryService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.tenant', ['title' => 'Accounts Receivable'])]
class Receivables extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = ''; // '' (all), 'pending', 'partially_paid', 'paid', 'overdue'

    public ?int $customerFilter = null;

    public string $activeTab = 'invoices'; // 'invoices' | 'customers'

    // Manual Payment Modal State
    public bool $showPaymentModal = false;

    public ?int $selectedSaleId = null;

    public ?Sale $selectedSale = null;

    public float $paymentAmount = 0.0;

    public string $paymentMethod = 'cash';

    public string $paymentDate = '';

    public string $referenceNumber = '';

    public string $paymentNotes = '';

    public bool $showReminderModal = false;

    public ?int $reminderSaleId = null;

    public string $reminderDueDate = '';

    public string $reminderAt = '';

    public function mount(): void
    {
        $this->paymentDate = now()->format('Y-m-d');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingCustomerFilter(): void
    {
        $this->resetPage();
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    public function openPaymentModal(int $saleId): void
    {
        $sale = Sale::with('customer', 'payments')->findOrFail($saleId);
        $this->selectedSaleId = $sale->id;
        $this->selectedSale = $sale;
        $this->paymentAmount = (float) $sale->due_amount;
        $this->paymentMethod = 'cash';
        $this->paymentDate = now()->format('Y-m-d');
        $this->referenceNumber = '';
        $this->paymentNotes = '';
        $this->showPaymentModal = true;
    }

    public function closePaymentModal(): void
    {
        $this->showPaymentModal = false;
        $this->selectedSaleId = null;
        $this->selectedSale = null;
    }

    public function recordPayment(): void
    {
        $this->validate([
            'paymentAmount' => ['required', 'numeric', 'min:0.01'],
            'paymentMethod' => ['required', 'string'],
            'paymentDate' => ['required', 'date'],
            'referenceNumber' => ['nullable', 'string', 'max:100'],
            'paymentNotes' => ['nullable', 'string', 'max:500'],
        ]);

        if (! $this->selectedSale) {
            return;
        }

        $companyId = app()->bound('tenant.company_id')
            ? app('tenant.company_id')
            : auth('web')->user()?->company_id;

        DB::transaction(function () use ($companyId) {
            $sale = Sale::lockForUpdate()->find($this->selectedSaleId);
            if (! $sale) {
                return;
            }

            $payVal = min((float) $this->paymentAmount, (float) $sale->due_amount > 0 ? (float) $sale->due_amount : (float) $this->paymentAmount);
            $newPaid = round((float) $sale->paid_amount + $payVal, 2);
            $newDue = max(0, round((float) $sale->total - $newPaid, 2));
            $newStatus = $newDue <= 0.001 ? 'paid' : 'partially_paid';

            $orderPayment = OrderPayment::create([
                'company_id' => $companyId,
                'sale_id' => $sale->id,
                'payment_method' => $this->paymentMethod,
                'amount' => $payVal,
                'tendered' => $payVal,
                'change_returned' => 0,
                'reference_number' => $this->referenceNumber ?: null,
                'notes' => $this->paymentNotes ?: ('Manual receivable collection on '.$this->paymentDate),
                'created_at' => Carbon::parse($this->paymentDate.' '.now()->format('H:i:s')),
            ]);

            $sale->update([
                'paid_amount' => $newPaid,
                'due_amount' => $newDue,
                'payment_status' => $newStatus,
                'due_reminder_dismissed_at' => $newDue <= 0.001 ? now() : $sale->due_reminder_dismissed_at,
            ]);

            app(CustomerLedgerService::class)->recordPayment($sale, $orderPayment);

            AuditLog::record('financials.receivable_collected', $companyId, auth('web')->id(), [
                'sale_id' => $sale->id,
                'sale_number' => $sale->sale_number,
                'amount' => $payVal,
                'remaining_due' => $newDue,
                'payment_method' => $this->paymentMethod,
            ]);
        });

        session()->flash('status', 'Payment of $'.number_format($this->paymentAmount, 2)." logged successfully for Sale #{$this->selectedSale->sale_number}.");
        $this->closePaymentModal();
    }

    public function openReminderModal(int $saleId): void
    {
        $sale = Sale::where('due_amount', '>', 0)->findOrFail($saleId);
        $timezone = auth('web')->user()?->company?->resolveTimezone() ?? 'UTC';
        $this->reminderSaleId = $sale->id;
        $this->reminderDueDate = $sale->due_date?->toDateString() ?? now($timezone)->addDays(7)->toDateString();
        $this->reminderAt = $sale->due_reminder_at
            ? $sale->due_reminder_at->copy()->timezone($timezone)->format('Y-m-d\TH:i')
            : Carbon::parse($this->reminderDueDate, $timezone)->setTime(9, 0)->format('Y-m-d\TH:i');
        $this->showReminderModal = true;
    }

    public function scheduleReminder(): void
    {
        $this->validate([
            'reminderDueDate' => ['required', 'date'],
            'reminderAt' => ['required', 'date'],
        ]);

        $sale = Sale::where('due_amount', '>', 0)->findOrFail($this->reminderSaleId);
        $timezone = auth('web')->user()?->company?->resolveTimezone() ?? 'UTC';
        $sale->update([
            'due_date' => $this->reminderDueDate,
            'due_reminder_at' => Carbon::parse($this->reminderAt, $timezone)->utc(),
            'due_reminder_sent_at' => null,
            'due_reminder_dismissed_at' => null,
        ]);

        AuditLog::record('receivable.reminder_scheduled', $sale->company_id, auth('web')->id(), [
            'sale_id' => $sale->id,
            'reminder_at' => $sale->due_reminder_at?->toIso8601String(),
        ]);

        $this->showReminderModal = false;
        $this->reminderSaleId = null;
        session()->flash('status', "Push reminder scheduled for {$sale->sale_number}.");
    }

    /**
     * "Send Reminder" action per due invoice row — WhatsApp/email if the
     * tenant has credentials configured, otherwise a browser event opens
     * the wa.me/mailto fallback link, or dispatches to custom channels.
     */
    public function sendReminder(int $saleId, string $channel, ?int $channelId = null): void
    {
        $sale = Sale::with('customer')->findOrFail($saleId);
        $delivery = app(InvoiceDeliveryService::class);

        if ($channel === 'whatsapp') {
            $phone = $sale->customer?->phone;
            if (! $phone) {
                session()->flash('status', 'This customer has no phone number on file.');

                return;
            }

            $result = app(MessageQueueService::class)->sendOrQueueWhatsApp($sale, $phone, $delivery->buildDueReminderMessage($sale));
            if ($result['status'] === 'manual_link') {
                $this->dispatch('open-external-url', url: $result['url']);
            } else {
                session()->flash('status', 'Reminder sent via WhatsApp.');
            }

            return;
        }

        if ($channel === 'email') {
            $email = $sale->customer?->email;
            $smtp = $delivery->getSmtpConfig();
            if ($email && ! empty($smtp['host'])) {
                try {
                    $delivery->sendDueReminderEmail($sale, $email);
                    session()->flash('status', 'Reminder sent via email.');
                } catch (\Throwable $e) {
                    session()->flash('status', 'Failed to send reminder: '.$e->getMessage());
                }
            } else {
                $subject = rawurlencode("Payment Reminder: Invoice #{$sale->sale_number}");
                $body = rawurlencode($delivery->buildDueReminderMessage($sale));
                $this->dispatch('open-external-url', url: "mailto:{$email}?subject={$subject}&body={$body}");
            }

            return;
        }

        // custom notification channels
        $variables = [
            'customer_name' => $sale->customer?->name ?? $sale->customer_name ?? '',
            'invoice_no' => $sale->sale_number,
            'due_amount' => (float) $sale->due_amount,
            'due_date' => $sale->due_date?->toDateString() ?? '',
            'receipt_link' => route('sales.public', $sale->sale_number),
        ];

        if ($channelId) {
            $customChannel = CustomNotificationChannel::where('company_id', $sale->company_id)->find($channelId);
            if ($customChannel && $customChannel->handlesEvent('due_reminder')) {
                app(WebhookDispatchService::class)->dispatch($customChannel, $variables);
                session()->flash('status', "Reminder dispatched to {$customChannel->name}.");
            } else {
                session()->flash('error', 'That notification channel is unavailable.');
            }

            return;
        }

        app(WebhookDispatchService::class)->dispatchEvent($sale->company_id, 'due_reminder', $variables);
        session()->flash('status', 'Reminder dispatched to custom notification channels.');
    }

    public function render()
    {
        $companyId = app()->bound('tenant.company_id')
            ? app('tenant.company_id')
            : auth('web')->user()?->company_id;

        // KPI Calculations
        $baseReceivables = Sale::query()
            ->where('company_id', $companyId)
            ->where('status', '!=', 'cancelled');

        $totalOutstanding = (float) (clone $baseReceivables)
            ->where('due_amount', '>', 0)
            ->sum('due_amount');

        $totalOverdue = (float) (clone $baseReceivables)
            ->where('due_amount', '>', 0)
            ->where('due_date', '<', now()->toDateString())
            ->sum('due_amount');

        $collectedThisMonth = (float) OrderPayment::query()
            ->where('company_id', $companyId)
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('amount');

        $pendingInvoicesCount = (clone $baseReceivables)
            ->where('due_amount', '>', 0)
            ->count();

        // Invoices Query
        $invoicesQuery = Sale::query()
            ->with('customer', 'payments')
            ->where('company_id', $companyId)
            ->where('status', '!=', 'cancelled')
            ->when($this->search, function ($q) {
                $t = '%'.trim($this->search).'%';
                $q->where(fn ($sq) => $sq->where('sale_number', 'like', $t)->orWhere('customer_name', 'like', $t));
            })
            ->when($this->customerFilter, fn ($q) => $q->where('customer_id', $this->customerFilter))
            ->when($this->statusFilter, function ($q) {
                if ($this->statusFilter === 'overdue') {
                    $q->where('due_amount', '>', 0)->where('due_date', '<', now()->toDateString());
                } elseif ($this->statusFilter === 'pending') {
                    $q->where('due_amount', '>', 0)->where('paid_amount', '<=', 0);
                } elseif ($this->statusFilter === 'partially_paid') {
                    $q->where('due_amount', '>', 0)->where('paid_amount', '>', 0);
                } elseif ($this->statusFilter === 'paid') {
                    $q->where('due_amount', '<=', 0);
                }
            });

        $invoices = $invoicesQuery->orderByDesc('created_at')->paginate(15);

        // Customers Balances Query
        $customersWithBalances = Customer::query()
            ->where('company_id', $companyId)
            ->withCount(['sales as pending_sales_count' => fn ($q) => $q->where('due_amount', '>', 0)])
            ->withSum(['sales as total_due_balance' => fn ($q) => $q->where('status', '!=', 'cancelled')], 'due_amount')
            ->when($this->search, function ($q) {
                $t = '%'.trim($this->search).'%';
                $q->where(fn ($sq) => $sq->where('name', 'like', $t)->orWhere('phone', 'like', $t)->orWhere('email', 'like', $t));
            })
            ->orderByDesc('total_due_balance')
            ->get();

        $allCustomers = Customer::where('company_id', $companyId)->orderBy('name')->get();
        $paymentMethods = PaymentMethod::getForCompany($companyId);

        $reminderChannels = CustomNotificationChannel::where('company_id', $companyId)
            ->where('is_active', true)
            ->get()
            ->filter(fn (CustomNotificationChannel $c) => $c->handlesEvent('due_reminder'))
            ->values();

        return view('livewire.tenant.financials.receivables', [
            'invoices' => $invoices,
            'customersWithBalances' => $customersWithBalances,
            'allCustomers' => $allCustomers,
            'paymentMethods' => $paymentMethods,
            'totalOutstanding' => $totalOutstanding,
            'totalOverdue' => $totalOverdue,
            'collectedThisMonth' => $collectedThisMonth,
            'pendingInvoicesCount' => $pendingInvoicesCount,
            'reminderChannels' => $reminderChannels,
        ]);
    }
}
