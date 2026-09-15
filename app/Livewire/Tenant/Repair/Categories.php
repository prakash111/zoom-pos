<?php

namespace App\Livewire\Tenant\Repair;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\RepairDeviceCategory;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Device Categories — web parity for RepairApiController::categoriesIndex/Store/
 * Update/Destroy and SchemaResponse::repairCategoriesView. A repair category is
 * a core Category row with type=device and an intake metadata bag.
 */
#[Layout('layouts.tenant', ['title' => 'Device Categories'])]
class Categories extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $identifierType = 'Serial / IMEI';

    public string $brands = '';

    public string $checklistItems = '';

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'identifierType' => ['nullable', 'string', 'max:100'],
            'brands' => ['nullable', 'string', 'max:2000'],
            'checklistItems' => ['nullable', 'string', 'max:4000'],
        ];
    }

    public function seedDefaults(): void
    {
        $company = auth()->user()->company;
        foreach (RepairDeviceCategory::defaultPresets() as $preset) {
            Category::firstOrCreate(
                ['company_id' => $company->id, 'name' => $preset['name']],
                [
                    'tenant_id' => $company->id,
                    'type' => 'device',
                    'icon' => $preset['icon'] ?? 'devices',
                    'metadata' => [
                        'identifier_type' => $preset['identifier_type'] ?? 'Serial / IMEI',
                        'brands' => $preset['brands'] ?? [],
                        'checklist_items' => $preset['checklist_items'] ?? [],
                        'common_issues' => $preset['common_issues'] ?? [],
                    ],
                    'active' => true,
                    'is_demo' => false,
                ],
            );
        }
        session()->flash('status', __('Default device categories added.'));
    }

    public function newCategory(): void
    {
        $this->reset(['editingId', 'name', 'brands', 'checklistItems']);
        $this->identifierType = 'Serial / IMEI';
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $c = $this->query()->findOrFail($id);
        $this->editingId = $c->id;
        $this->name = $c->name;
        $meta = (array) ($c->metadata ?? []);
        $this->identifierType = (string) ($meta['identifier_type'] ?? 'Serial / IMEI');
        $this->brands = implode(', ', (array) ($meta['brands'] ?? []));
        $this->checklistItems = implode("\n", (array) ($meta['checklist_items'] ?? []));
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate();
        $toList = fn (string $raw, string $sep) => array_values(array_filter(array_map('trim', preg_split($sep, $raw) ?: [])));

        $metadata = [
            'identifier_type' => $data['identifierType'] ?: 'Serial / IMEI',
            'brands' => $toList($data['brands'], '/,/'),
            'checklist_items' => $toList($data['checklistItems'], '/\R/'),
        ];

        if ($this->editingId) {
            $c = $this->query()->findOrFail($this->editingId);
            $c->update(['name' => $data['name'], 'metadata' => array_merge((array) $c->metadata, $metadata)]);
        } else {
            $c = Category::create([
                'name' => $data['name'],
                'type' => 'device',
                'metadata' => $metadata,
                'active' => true,
                'is_demo' => false,
            ]);
        }

        AuditLog::record('repair.category_saved', $c->company_id, auth()->id(), ['category_id' => $c->id, 'name' => $c->name]);
        $this->showForm = false;
        session()->flash('status', __('Device category saved.'));
    }

    public function delete(int $id): void
    {
        $this->query()->findOrFail($id)->update(['active' => false]);
        session()->flash('status', __('Category archived.'));
    }

    protected function query()
    {
        return Category::query()->where(fn ($q) => $q->where('type', 'device')->orWhereNull('type'));
    }

    public function render()
    {
        return view('livewire.tenant.repair.categories', [
            'categories' => $this->query()->where('active', true)->orderBy('name')->get(),
        ]);
    }
}
