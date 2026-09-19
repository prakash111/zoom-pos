<?php

namespace App\Livewire\Tenant\Catalog;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\PublishedCatalog;
use App\Models\Quotation;
use App\Models\Sale;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.tenant', ['title' => 'eCommerce Storefront & Website'])]
class Index extends Component
{
    use WithFileUploads;

    public string $title = '';
    public string $description = '';
    public string $whatsappNumber = '';
    public array $selectedProductIds = [];
    public int $ttlDays = 0; // Default to Permanent store website
    public bool $enableQuotations = true;
    public bool $enableInvoices = true;
    public string $productSearch = '';
    public ?int $filterCategory = null;

    // Fast inline product upload modal state
    public bool $showProductModal = false;
    public string $newProductName = '';
    public string $newProductDescription = '';
    public float $newProductPrice = 0;
    public float $newProductCost = 0;
    public float $newProductStock = 10;
    public ?int $newProductCategory = null;
    public string $newProductCode = '';
    public string $newProductBarcode = '';

    public function mount(): void
    {
        $company = auth('web')->user()?->company;
        $this->title = $company?->display_name ?? $company?->name ?? 'Online Store';
        $this->description = 'Welcome to our online business store! Browse our live product catalog, request quotations, and checkout directly via WhatsApp with zero hassle.';
        $this->whatsappNumber = (string) ($company?->phone ?? '');
        $this->ttlDays = 0; // Permanent by default for complete eCommerce website
    }

    public function selectAllProducts(): void
    {
        $this->selectedProductIds = Product::where('active', true)->pluck('id')->map(fn ($id) => (int) $id)->toArray();
    }

    public function clearSelectedProducts(): void
    {
        $this->selectedProductIds = [];
    }

    public function openNewProductModal(): void
    {
        $this->reset([
            'newProductName', 'newProductDescription', 'newProductPrice',
            'newProductCost', 'newProductStock', 'newProductCategory',
            'newProductCode', 'newProductBarcode',
        ]);
        $this->newProductStock = 10;
        $this->showProductModal = true;
    }

    public function closeNewProductModal(): void
    {
        $this->showProductModal = false;
    }

    public function saveNewProduct(): void
    {
        $this->validate([
            'newProductName' => ['required', 'string', 'max:255'],
            'newProductDescription' => ['nullable', 'string', 'max:5000'],
            'newProductPrice' => ['required', 'numeric', 'min:0'],
            'newProductCost' => ['nullable', 'numeric', 'min:0'],
            'newProductStock' => ['required', 'numeric', 'min:0'],
            'newProductCategory' => ['nullable', 'exists:categories,id'],
        ]);

        $companyId = auth('web')->user()?->company_id;
        $code = $this->newProductCode ?: ('PRD-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6)));
        $barcode = $this->newProductBarcode ?: ('890' . str_pad((string) random_int(100000000, 999999999), 9, '0', STR_PAD_LEFT));

        $category = $this->newProductCategory ? Category::find($this->newProductCategory) : null;

        $product = Product::create([
            'company_id' => $companyId,
            'name' => $this->newProductName,
            'description' => $this->newProductDescription ?: null,
            'code' => $code,
            'barcode' => $barcode,
            'sale_price' => $this->newProductPrice,
            'cost_price' => $this->newProductCost ?: 0,
            'current_stock' => $this->newProductStock,
            'minimum_stock' => 2,
            'category_id' => $this->newProductCategory,
            'category_name' => $category?->name,
            'active' => true,
            'taxable' => true,
        ]);

        AuditLog::record('product.created', $companyId, auth('web')->id(), ['product_id' => $product->id]);

        // Automatically select the new product for the eCommerce storefront
        if (! in_array($product->id, $this->selectedProductIds, true)) {
            $this->selectedProductIds[] = $product->id;
        }

        $this->showProductModal = false;
        session()->flash('status', "Product '{$product->name}' created and added to eCommerce store!");
    }

    public function publish(): void
    {
        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'selectedProductIds' => ['required', 'array', 'min:1'],
            'ttlDays' => ['required', 'integer', 'in:0,1,7,30'],
            'whatsappNumber' => ['nullable', 'string', 'max:50'],
        ]);

        $catalog = PublishedCatalog::create([
            'title' => $this->title,
            'description' => $this->description ?: null,
            'meta' => [
                'whatsapp_number' => $this->whatsappNumber ?: null,
                'enable_quotations' => $this->enableQuotations,
                'enable_invoices' => $this->enableInvoices,
            ],
            'product_ids' => array_values(array_map('intval', $this->selectedProductIds)),
            'expires_at' => $this->ttlDays ? now()->addDays($this->ttlDays) : null,
        ]);

        AuditLog::record('catalog.published', $catalog->company_id, auth('web')->id(), [
            'catalog_id' => $catalog->id,
            'products_count' => count($this->selectedProductIds),
        ]);

        session()->flash('status', 'eCommerce Business Website published successfully!');
        session()->flash('published_url', route('catalog.show', $catalog->id));
    }

    public function revoke(string $id): void
    {
        PublishedCatalog::findOrFail($id)->delete();
        session()->flash('status', 'eCommerce Storefront link revoked.');
    }

    public function render()
    {
        $productsQuery = Product::where('active', true);

        if ($this->filterCategory) {
            $productsQuery->where('category_id', $this->filterCategory);
        }

        if (filled($this->productSearch)) {
            $term = '%' . trim($this->productSearch) . '%';
            $productsQuery->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                  ->orWhere('code', 'like', $term)
                  ->orWhere('barcode', 'like', $term)
                  ->orWhere('description', 'like', $term);
            });
        }

        $allProducts = $productsQuery->orderBy('name')->get();
        $categories = Category::orderBy('name')->get();

        // Sample quotes and invoices count to show connected business data
        $quotesCount = Quotation::count();
        $invoicesCount = Sale::where('status', 'completed')->count();

        return view('livewire.tenant.catalog.index', [
            'products' => $allProducts,
            'categories' => $categories,
            'catalogs' => PublishedCatalog::orderByDesc('created_at')->get(),
            'totalActiveProducts' => Product::where('active', true)->count(),
            'quotesCount' => $quotesCount,
            'invoicesCount' => $invoicesCount,
        ]);
    }
}
