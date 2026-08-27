<?php

namespace App\Livewire\Tenant\Catalog;

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\PublishedCatalog;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.tenant', ['title' => 'Online Catalog'])]
class Index extends Component
{
    public string $title = '';

    public array $selectedProductIds = [];

    public int $ttlDays = 7;

    public function publish(): void
    {
        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'selectedProductIds' => ['required', 'array', 'min:1'],
            'ttlDays' => ['required', 'integer', 'in:1,7,30,0'],
        ]);

        $catalog = PublishedCatalog::create([
            'title' => $this->title,
            'product_ids' => array_values($this->selectedProductIds),
            'expires_at' => $this->ttlDays ? now()->addDays($this->ttlDays) : null,
        ]);

        AuditLog::record('catalog.published', $catalog->company_id, auth('web')->id(), ['catalog_id' => $catalog->id]);

        $this->reset(['title', 'selectedProductIds']);
        $this->ttlDays = 7;
        session()->flash('status', 'Catalog published.');
        session()->flash('published_url', route('catalog.show', $catalog->id));
    }

    public function revoke(string $id): void
    {
        PublishedCatalog::findOrFail($id)->delete();
        session()->flash('status', 'Catalog link revoked.');
    }

    public function render()
    {
        return view('livewire.tenant.catalog.index', [
            'products' => Product::where('active', true)->orderBy('name')->get(),
            'catalogs' => PublishedCatalog::orderByDesc('created_at')->get(),
        ]);
    }
}
