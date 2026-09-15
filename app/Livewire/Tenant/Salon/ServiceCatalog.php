<?php

namespace App\Livewire\Tenant\Salon;

use App\Models\AuditLog;
use App\Models\Product;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Service Catalog & Rates — web parity for
 * SalonApiController::servicesIndex/servicesStore/servicesUpdate/servicesDestroy
 * and SchemaResponse::serviceCatalogRatesView / serviceCreateView. A salon
 * "service" is an ordinary Product with type=service / category_type=salon.
 */
#[Layout('layouts.tenant', ['title' => 'Service Catalog & Rates'])]
class ServiceCatalog extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public $price = null;

    public int $durationMinutes = 30;

    public string $description = '';

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'durationMinutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function newService(): void
    {
        $this->reset(['editingId', 'name', 'price', 'description']);
        $this->durationMinutes = 30;
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $service = $this->serviceQuery()->findOrFail($id);
        $this->editingId = $service->id;
        $this->name = $service->name;
        $this->price = (float) ($service->sale_price ?: $service->price);
        $this->durationMinutes = (int) ($service->duration_minutes ?: 30);
        $this->description = (string) $service->description;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate();
        $rate = (float) ($data['price'] ?? 0);

        if ($this->editingId) {
            $service = $this->serviceQuery()->findOrFail($this->editingId);
            $service->update([
                'name' => $data['name'],
                'price' => $rate,
                'sale_price' => $rate,
                'duration_minutes' => $data['durationMinutes'],
                'description' => $data['description'] ?: null,
            ]);
            AuditLog::record('salon.service_updated', $service->company_id, auth()->id(), ['product_id' => $service->id]);
        } else {
            $service = Product::create([
                'name' => $data['name'],
                'sku' => 'SRV-'.strtoupper(Str::random(6)),
                'barcode' => 'SRV-'.strtoupper(Str::random(8)),
                'type' => 'service',
                'category_type' => 'salon',
                'price' => $rate,
                'sale_price' => $rate,
                'cost_price' => 0,
                'duration_minutes' => $data['durationMinutes'],
                'description' => $data['description'] ?: null,
                'active' => true,
                'current_stock' => 0,
                'unit' => 'service',
            ]);
            AuditLog::record('salon.service_created', $service->company_id, auth()->id(), [
                'product_id' => $service->id, 'name' => $service->name, 'price' => $rate,
            ]);
        }

        $this->showForm = false;
        session()->flash('status', __('Service saved.'));
    }

    public function delete(int $id): void
    {
        $this->serviceQuery()->findOrFail($id)->update(['active' => false]);
        session()->flash('status', __('Service archived.'));
    }

    protected function serviceQuery()
    {
        return Product::query()->where(function ($q) {
            $q->where('type', 'service')->orWhere('duration_minutes', '>', 0)->orWhere('category_type', 'salon');
        });
    }

    public function render()
    {
        return view('livewire.tenant.salon.service-catalog', [
            'services' => $this->serviceQuery()->where('active', true)->orderBy('name')->get(),
        ]);
    }
}
