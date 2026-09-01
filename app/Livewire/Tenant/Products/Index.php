<?php

namespace App\Livewire\Tenant\Products;

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\AiImageGeneratorService;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts.tenant', ['title' => 'Products'])]
class Index extends Component
{
    use WithFileUploads, WithPagination;

    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $code = '';

    public string $barcode = '';

    public $imageFile = null;

    public string $imageUrl = '';

    public ?int $categoryId = null;

    public ?int $brandId = null;

    public string $unit = '';

    public float $costPrice = 0;

    public float $salePrice = 0;

    public float $currentStock = 0;

    public float $minimumStock = 0;

    public bool $active = true;

    public bool $taxable = true;

    public ?int $adjustingId = null;

    public float $adjustmentQty = 0;

    public string $adjustmentReason = '';

    /** @var array<int, array{name: string, price: float}> Portion sizes / styles (e.g. Small/Medium/Large). */
    public array $variants = [];

    /** @var array<int, array{name: string, price: float}> Add-ons & extras (multi-select at POS). */
    public array $modifiers = [];

    /** @var array<int, array{name: string, price: float}> Spice levels (single-select at POS). */
    public array $spiceLevels = [];

    public string $newVariantName = '';

    public float $newVariantPrice = 0;

    public string $newModifierName = '';

    public float $newModifierPrice = 0;

    public string $newSpiceLevelName = '';

    public float $newSpiceLevelPrice = 0;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    #[On('products-imported')]
    public function productsImported(): void
    {
        $this->resetPage();
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('products', 'code')->where(fn ($query) => $query->where('company_id', auth('web')->user()?->company_id))->ignore($this->editingId)],
            'barcode' => ['nullable', 'string', 'max:100', Rule::unique('products', 'barcode')->where(fn ($query) => $query->where('company_id', auth('web')->user()?->company_id))->ignore($this->editingId)],
            'imageFile' => ['nullable', 'image', 'max:5120'],
            'imageUrl' => ['nullable', 'string', 'max:500'],
            'categoryId' => ['nullable', 'exists:categories,id'],
            'brandId' => ['nullable', 'exists:brands,id'],
            'costPrice' => ['required', 'numeric', 'min:0'],
            'salePrice' => ['required', 'numeric', 'min:0'],
            'currentStock' => ['required', 'numeric'],
            'minimumStock' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function newProduct(): void
    {
        $this->reset([
            'editingId', 'name', 'code', 'barcode', 'imageFile', 'imageUrl', 'categoryId', 'brandId', 'unit',
            'costPrice', 'salePrice', 'currentStock', 'minimumStock', 'variants', 'modifiers', 'spiceLevels',
        ]);
        $this->active = true;
        $this->taxable = true;
        $this->showForm = true;
    }

    public function addVariant(): void
    {
        $this->validate(['newVariantName' => ['required', 'string', 'max:100']]);
        $this->variants[] = ['name' => $this->newVariantName, 'price' => round($this->newVariantPrice, 2)];
        $this->reset(['newVariantName', 'newVariantPrice']);
    }

    public function removeVariant(int $index): void
    {
        unset($this->variants[$index]);
        $this->variants = array_values($this->variants);
    }

    public function addModifier(): void
    {
        $this->validate(['newModifierName' => ['required', 'string', 'max:100']]);
        $this->modifiers[] = ['name' => $this->newModifierName, 'price' => round($this->newModifierPrice, 2)];
        $this->reset(['newModifierName', 'newModifierPrice']);
    }

    public function removeModifier(int $index): void
    {
        unset($this->modifiers[$index]);
        $this->modifiers = array_values($this->modifiers);
    }

    public function addSpiceLevel(): void
    {
        $this->validate(['newSpiceLevelName' => ['required', 'string', 'max:100']]);
        $this->spiceLevels[] = ['name' => $this->newSpiceLevelName, 'price' => round($this->newSpiceLevelPrice, 2)];
        $this->reset(['newSpiceLevelName', 'newSpiceLevelPrice']);
    }

    public function removeSpiceLevel(int $index): void
    {
        unset($this->spiceLevels[$index]);
        $this->spiceLevels = array_values($this->spiceLevels);
    }

    public function generateItemCode(): void
    {
        $companyId = auth('web')->user()?->company_id;
        $prefix = 'ITM';
        $next = 1;

        do {
            $candidate = $prefix.'-'.str_pad((string) $next++, 6, '0', STR_PAD_LEFT);
        } while (Product::withoutGlobalScopes()->where('company_id', $companyId)->where('code', $candidate)->exists());

        $this->code = $candidate;
        $this->resetValidation('code');
    }

    public function generateIdentifiers(): void
    {
        $category = $this->categoryId ? Category::find($this->categoryId)?->name : null;
        $prefix = Str::of($category ?: 'PRD')->ascii()->upper()->replaceMatches('/[^A-Z0-9]/', '')->substr(0, 3)->padRight(3, 'X');
        $companyId = auth('web')->user()?->company_id;

        if (blank($this->code)) {
            do {
                $candidate = $prefix.'-'.random_int(100000, 999999);
            } while (Product::withoutGlobalScopes()->where('company_id', $companyId)->where('code', $candidate)->exists());
            $this->code = $candidate;
        }

        if (blank($this->barcode)) {
            do {
                $base = '890'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT);
                $sum = 0;
                foreach (str_split($base) as $index => $digit) {
                    $sum += ((int) $digit) * ($index % 2 === 0 ? 1 : 3);
                }
                $candidate = $base.((10 - ($sum % 10)) % 10);
            } while (Product::withoutGlobalScopes()->where('company_id', $companyId)->where('barcode', $candidate)->exists());
            $this->barcode = $candidate;
        }

        $this->resetValidation(['code', 'barcode']);
    }

    #[On('barcode-scanned')]
    public function barcodeScanned(string $code): void
    {
        if ($this->showForm) {
            $this->barcode = trim($code);
            $this->resetValidation('barcode');
        }
    }

    public function generateAiPhoto(AiImageGeneratorService $generator): void
    {
        $this->validateOnly('name');
        $category = $this->categoryId ? Category::find($this->categoryId)?->name : null;

        try {
            $this->imageUrl = $generator->generateProductImage($this->name, $category) ?: '';
            if ($this->imageUrl === '') {
                $this->addError('imageUrl', __('The AI provider returned no image.'));
            }
        } catch (\Throwable $e) {
            report($e);
            $this->addError('imageUrl', $e->getMessage());
        }
    }

    public function edit(int $id): void
    {
        $product = Product::findOrFail($id);
        $this->editingId = $product->id;
        $this->name = $product->name;
        $this->code = (string) $product->code;
        $this->barcode = (string) $product->barcode;
        $this->imageFile = null;
        $this->imageUrl = (string) ($product->image_url ?? '');
        $this->categoryId = $product->category_id;
        $this->brandId = $product->brand_id;
        $this->unit = (string) $product->unit;
        $this->costPrice = (float) $product->cost_price;
        $this->salePrice = (float) $product->sale_price;
        $this->currentStock = (float) $product->current_stock;
        $this->minimumStock = (float) $product->minimum_stock;
        $this->active = $product->active;
        $this->taxable = $product->taxable;
        $this->variants = $product->variants ?? [];
        $this->modifiers = $product->modifiers ?? [];
        $this->spiceLevels = $product->spice_levels ?? [];
        $this->showForm = true;
    }

    public function save(): void
    {
        if (blank($this->code) || blank($this->barcode)) {
            $this->generateIdentifiers();
        }
        $data = $this->validate();

        if ($this->imageFile) {
            $path = $this->imageFile->store('products', 'public');
            $this->imageUrl = '/storage/'.$path;
        }

        $product = Product::updateOrCreate(['id' => $this->editingId], [
            'name' => $data['name'],
            'code' => $data['code'] ?: null,
            'barcode' => $data['barcode'] ?: null,
            'image_url' => $this->imageUrl ?: null,
            'category_id' => $data['categoryId'],
            'category_name' => $data['categoryId'] ? Category::find($data['categoryId'])?->name : null,
            'brand_id' => $data['brandId'],
            'brand_name' => $data['brandId'] ? Brand::find($data['brandId'])?->name : null,
            'unit' => $this->unit ?: null,
            'cost_price' => $data['costPrice'],
            'sale_price' => $data['salePrice'],
            'current_stock' => $data['currentStock'],
            'minimum_stock' => $data['minimumStock'],
            'active' => $this->active,
            'taxable' => $this->taxable,
            'variants' => $this->variants ?: null,
            'modifiers' => $this->modifiers ?: null,
            'spice_levels' => $this->spiceLevels ?: null,
        ]);

        AuditLog::record($this->editingId ? 'product.updated' : 'product.created', $product->company_id, auth('web')->id(), ['product_id' => $product->id]);

        $this->showForm = false;
        $this->reset(['imageFile', 'imageUrl']);
        session()->flash('status', 'Product saved.');
    }

    public function delete(int $id): void
    {
        $product = Product::findOrFail($id);
        AuditLog::record('product.deleted', $product->company_id, auth('web')->id(), ['product_id' => $id, 'name' => $product->name]);
        $product->delete();
        session()->flash('status', 'Product deleted.');
    }

    public function startAdjust(int $id): void
    {
        $this->adjustingId = $id;
        $this->adjustmentQty = 0;
        $this->adjustmentReason = '';
    }

    public function applyAdjustment(): void
    {
        $this->validate([
            'adjustmentQty' => ['required', 'numeric', 'not_in:0'],
            'adjustmentReason' => ['nullable', 'string', 'max:255'],
        ]);

        $product = Product::findOrFail($this->adjustingId);
        $before = (float) $product->current_stock;
        $product->update(['current_stock' => $before + $this->adjustmentQty]);

        AuditLog::record('product.stock_adjusted', $product->company_id, auth('web')->id(), [
            'product_id' => $product->id, 'before' => $before, 'delta' => $this->adjustmentQty,
            'after' => $product->current_stock, 'reason' => $this->adjustmentReason,
        ]);

        $this->adjustingId = null;
        session()->flash('status', 'Stock adjusted.');
    }

    public function render()
    {
        $products = Product::query()
            ->with(['category', 'brand'])
            ->when($this->search, function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($q) use ($term) {
                    $q->where('name', 'like', $term)->orWhere('code', 'like', $term)->orWhere('barcode', 'like', $term);
                });
            })
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.tenant.products.index', [
            'products' => $products,
            'categories' => Category::orderBy('name')->get(),
            'brands' => Brand::orderBy('name')->get(),
            'isRestaurantMode' => (bool) auth('web')->user()?->company?->isRestaurantMode(),
        ]);
    }
}
