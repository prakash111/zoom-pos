<?php

namespace App\Livewire\Tenant\Pharmacy;

use App\Models\AuditLog;
use App\Models\PharmacyBatch;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Drug Batches & Expiry Tracker — web parity for
 * PharmacyApiController::batchesIndex/batchesStore/batchesAdjust/batchesReturn
 * and SchemaResponse::pharmacyBatchesView. Stock deltas flow through
 * Product::incrementStock/decrementStock exactly as the API path does.
 */
#[Layout('layouts.tenant', ['title' => 'Drug Batches & Expiry'])]
class Batches extends Component
{
    #[Url]
    public string $filter = 'all'; // all | safe | near_expiry | expired

    #[Url]
    public string $search = '';

    public bool $showForm = false;

    public ?string $productId = null;

    public string $batchNumber = '';

    public string $rackLocation = '';

    public ?string $manufacturingDate = null;

    public ?string $expiryDate = null;

    public $costPrice = null;

    public $sellingPrice = null;

    public int $stockQty = 0;

    public int $alertDaysBeforeExpiry = 90;

    // Adjust / return modal
    public ?int $actingBatchId = null;

    public string $actingMode = ''; // adjust | return

    public int $adjustNewStock = 0;

    public int $returnQty = 0;

    public string $reason = '';

    protected function rules(): array
    {
        return [
            'productId' => ['required', 'integer', 'exists:products,id'],
            'batchNumber' => ['required', 'string', 'max:100'],
            'rackLocation' => ['nullable', 'string', 'max:100'],
            'manufacturingDate' => ['nullable', 'date'],
            'expiryDate' => ['required', 'date'],
            'costPrice' => ['nullable', 'numeric', 'min:0'],
            'sellingPrice' => ['nullable', 'numeric', 'min:0'],
            'stockQty' => ['required', 'integer', 'min:0'],
            'alertDaysBeforeExpiry' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function newBatch(): void
    {
        $this->reset(['productId', 'batchNumber', 'rackLocation', 'manufacturingDate', 'expiryDate', 'costPrice', 'sellingPrice', 'stockQty']);
        $this->alertDaysBeforeExpiry = 90;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate();

        $product = Product::findOrFail($data['productId']);

        DB::transaction(function () use ($data, $product) {
            $batch = PharmacyBatch::create([
                'product_id' => $product->id,
                'batch_number' => trim($data['batchNumber']),
                'rack_location' => $data['rackLocation'] ?: null,
                'manufacturing_date' => $data['manufacturingDate'] ?: null,
                'expiry_date' => $data['expiryDate'],
                'cost_price' => $data['costPrice'] ?? ($product->cost_price ?? 0),
                'selling_price' => $data['sellingPrice'] ?? ($product->sale_price ?? 0),
                'stock_qty' => $data['stockQty'],
                'alert_days_before_expiry' => $data['alertDaysBeforeExpiry'] ?: 90,
                'is_active' => true,
            ]);

            if ($batch->stock_qty > 0) {
                $product->incrementStock($batch->stock_qty, "Batch #{$batch->batch_number} created");
            }

            AuditLog::record('pharmacy.batch_created', $batch->company_id, auth()->id(), [
                'batch_id' => $batch->id,
                'batch_number' => $batch->batch_number,
                'product_id' => $product->id,
                'stock_qty' => $batch->stock_qty,
            ]);
        });

        $this->showForm = false;
        session()->flash('status', __('Drug batch registered.'));
    }

    public function startAdjust(int $batchId): void
    {
        $batch = PharmacyBatch::findOrFail($batchId);
        $this->actingBatchId = $batch->id;
        $this->actingMode = 'adjust';
        $this->adjustNewStock = (int) $batch->stock_qty;
        $this->reason = '';
    }

    public function startReturn(int $batchId): void
    {
        $this->actingBatchId = PharmacyBatch::findOrFail($batchId)->id;
        $this->actingMode = 'return';
        $this->returnQty = 0;
        $this->reason = '';
    }

    public function confirmAction(): void
    {
        $batch = PharmacyBatch::with('product')->findOrFail($this->actingBatchId);

        if ($this->actingMode === 'adjust') {
            $this->validate(['adjustNewStock' => ['required', 'integer', 'min:0']]);
            $old = (int) $batch->stock_qty;
            $new = (int) $this->adjustNewStock;
            $delta = $new - $old;

            DB::transaction(function () use ($batch, $new, $delta) {
                $batch->update(['stock_qty' => $new]);
                if ($delta > 0) {
                    $batch->product?->incrementStock($delta, "Batch #{$batch->batch_number} adjustment: ".($this->reason ?: 'Audit'));
                } elseif ($delta < 0) {
                    $batch->product?->decrementStock(abs($delta), "Batch #{$batch->batch_number} adjustment: ".($this->reason ?: 'Audit'));
                }
            });

            AuditLog::record('pharmacy.batch_adjusted', $batch->company_id, auth()->id(), [
                'batch_id' => $batch->id, 'old_stock' => $old, 'new_stock' => $new, 'reason' => $this->reason ?: null,
            ]);
            session()->flash('status', __('Batch stock adjusted.'));
        } elseif ($this->actingMode === 'return') {
            $this->validate(['returnQty' => ['required', 'integer', 'min:1']]);
            if ($batch->stock_qty < $this->returnQty) {
                $this->addError('returnQty', __('Only :n units available in this batch.', ['n' => $batch->stock_qty]));

                return;
            }

            DB::transaction(function () use ($batch) {
                $batch->decrement('stock_qty', $this->returnQty);
                $batch->product?->decrementStock($this->returnQty, 'Vendor return: '.($this->reason ?: 'Damaged/Expired'));
            });

            AuditLog::record('pharmacy.vendor_return', $batch->company_id, auth()->id(), [
                'batch_id' => $batch->id, 'returned_qty' => $this->returnQty, 'reason' => $this->reason ?: null,
            ]);
            session()->flash('status', __('Vendor return logged.'));
        }

        $this->reset(['actingBatchId', 'actingMode', 'adjustNewStock', 'returnQty', 'reason']);
    }

    public function cancelAction(): void
    {
        $this->reset(['actingBatchId', 'actingMode', 'adjustNewStock', 'returnQty', 'reason']);
    }

    public function render()
    {
        $query = PharmacyBatch::with('product')->orderByRaw('expiry_date is null, expiry_date asc');

        if ($this->search !== '') {
            $term = trim($this->search);
            $query->where(function ($q) use ($term) {
                $q->where('batch_number', 'like', "%{$term}%")
                    ->orWhere('rack_location', 'like', "%{$term}%")
                    ->orWhereHas('product', fn ($pq) => $pq->where('name', 'like', "%{$term}%")->orWhere('generic_name', 'like', "%{$term}%"));
            });
        }

        $batches = $query->get();

        if (in_array($this->filter, ['safe', 'near_expiry', 'expired'], true)) {
            $batches = $batches->filter(fn ($b) => $b->expiry_status === $this->filter)->values();
        }

        return view('livewire.tenant.pharmacy.batches', [
            'batches' => $batches,
            'products' => Product::orderBy('name')->get(['id', 'name', 'generic_name']),
        ]);
    }
}
