<?php

namespace App\Livewire\Tenant\Quotes;

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.tenant', ['title' => 'Quotes & Proposals'])]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = 'all';

    public array $selectedQuotes = [];

    public bool $selectAll = false;

    public function updatedSelectAll(bool $value): void
    {
        if ($value) {
            $this->selectedQuotes = Sale::query()
                ->where('operation_type', 'quotation')
                ->when($this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
                ->when($this->search, function ($q) {
                    $term = '%'.$this->search.'%';
                    $q->where(function ($sq) use ($term) {
                        $sq->where('sale_number', 'like', $term)
                            ->orWhere('customer_name', 'like', $term);
                    });
                })
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->toArray();
        } else {
            $this->selectedQuotes = [];
        }
    }

    public function deleteQuote(int $id): void
    {
        $quote = Sale::where('operation_type', 'quotation')->find($id);
        if ($quote) {
            $quoteNumber = $quote->sale_number;
            $companyId = $quote->company_id;
            $quote->delete();

            AuditLog::record('quotation.deleted', $companyId, auth('web')->id(), ['quote_number' => $quoteNumber]);
            session()->flash('status', "Quotation {$quoteNumber} deleted.");
        }
    }

    public function bulkDelete(): void
    {
        if (empty($this->selectedQuotes)) {
            return;
        }

        $count = Sale::where('operation_type', 'quotation')
            ->whereIn('id', $this->selectedQuotes)
            ->delete();

        $this->selectedQuotes = [];
        $this->selectAll = false;
        session()->flash('status', "{$count} quotations deleted.");
    }

    public function convertToSale(int $id)
    {
        $user = auth('web')->user();
        abort_unless($user && $user->hasPermission('quotes', 'convert_to_sale'), 403, 'Unauthorized: quotes.convert_to_sale permission required.');

        $quote = Sale::where('operation_type', 'quotation')->find($id);
        if (! $quote || $quote->status === 'converted') {
            return;
        }

        foreach ($quote->items ?? [] as $item) {
            if (! empty($item['product_id'])) {
                $product = Product::find($item['product_id']);
                if ($product && $product->track_stock) {
                    $product->decrementStock((float) ($item['quantity'] ?? 1), "Converted from Quote #{$quote->sale_number}");
                }
            }
        }

        $saleNumber = 'INV-'.strtoupper(Str::random(6));

        $sale = Sale::create([
            'company_id' => $quote->company_id,
            'user_id' => $quote->user_id ?? auth('web')->id(),
            'customer_id' => $quote->customer_id,
            'customer_name' => $quote->customer_name,
            'sale_number' => $saleNumber,
            'operation_type' => 'sale',
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal' => $quote->subtotal ?? $quote->total,
            'discount' => $quote->discount ?? 0,
            'tax' => $quote->tax ?? 0,
            'total' => $quote->total,
            'paid_amount' => $quote->total,
            'due_amount' => 0,
            'items' => $quote->items,
            'notes' => $quote->notes,
        ]);

        $quote->update(['status' => 'converted']);

        AuditLog::record('quotation.converted', $quote->company_id, auth('web')->id(), [
            'quote_number' => $quote->sale_number,
            'invoice_number' => $sale->sale_number,
        ]);

        session()->flash('status', "Quotation converted to Sale #{$sale->sale_number}!");

        $this->redirectRoute('tenant.sales.show', $sale, navigate: true);
    }

    public function render()
    {
        $quotes = Sale::query()
            ->with(['customer'])
            ->where('operation_type', 'quotation')
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->search, function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($sq) use ($term) {
                    $sq->where('sale_number', 'like', $term)
                        ->orWhere('customer_name', 'like', $term);
                });
            })
            ->latest()
            ->paginate(15);

        return view('livewire.tenant.quotes.index', [
            'quotes' => $quotes,
        ]);
    }
}
