<?php

namespace App\Livewire\Superadmin;

use App\Models\MenuItem;
use App\Models\Page;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.superadmin', ['title' => 'Navigation Menu Builder'])]
class MenuBuilderComponent extends Component
{
    public string $activeLocation = 'header';

    public array $selectedPages = [];

    public string $customTitle = '';

    public string $customUrl = '';

    public bool $customTargetBlank = false;

    // Inline edit properties
    public ?int $editingId = null;

    public string $editingTitle = '';

    public string $editingUrl = '';

    public string $editingTarget = '_self';

    public function updatedActiveLocation(): void
    {
        $this->cancelEdit();
    }

    public function addSelectedPages(): void
    {
        if (empty($this->selectedPages)) {
            $this->dispatchToast('Please select at least one CMS page.', 'warning');

            return;
        }

        $pages = Page::whereIn('id', $this->selectedPages)->get();
        $maxOrder = MenuItem::where('location', $this->activeLocation)->max('order_index') ?? 0;

        foreach ($pages as $index => $page) {
            MenuItem::create([
                'location' => $this->activeLocation,
                'title' => $page->title,
                'type' => 'page',
                'url' => '/page/'.$page->slug,
                'page_id' => $page->id,
                'target' => '_self',
                'order_index' => $maxOrder + $index + 1,
                'is_active' => true,
            ]);
        }

        $this->reset('selectedPages');
        MenuItem::clearMenuCache($this->activeLocation);
        $this->dispatchToast('CMS pages added to navigation!', 'success');
    }

    public function addAnchorLink(string $title, string $url): void
    {
        $maxOrder = MenuItem::where('location', $this->activeLocation)->max('order_index') ?? 0;
        MenuItem::create([
            'location' => $this->activeLocation,
            'title' => $title,
            'type' => 'anchor',
            'url' => $url,
            'target' => '_self',
            'order_index' => $maxOrder + 1,
            'is_active' => true,
        ]);

        MenuItem::clearMenuCache($this->activeLocation);
        $this->dispatchToast('Anchor link added!', 'success');
    }

    public function addCustomLink(): void
    {
        $this->validate([
            'customTitle' => 'required|string|max:100',
            'customUrl' => 'required|string|max:255',
        ]);

        $maxOrder = MenuItem::where('location', $this->activeLocation)->max('order_index') ?? 0;
        MenuItem::create([
            'location' => $this->activeLocation,
            'title' => $this->customTitle,
            'type' => 'custom',
            'url' => $this->customUrl,
            'target' => $this->customTargetBlank ? '_blank' : '_self',
            'order_index' => $maxOrder + 1,
            'is_active' => true,
        ]);

        $this->reset(['customTitle', 'customUrl', 'customTargetBlank']);
        MenuItem::clearMenuCache($this->activeLocation);
        $this->dispatchToast('Custom link added!', 'success');
    }

    public function updateMenuOrder(array $orderedIds): void
    {
        foreach ($orderedIds as $index => $id) {
            MenuItem::where('id', $id)->update(['order_index' => $index]);
        }
        MenuItem::clearMenuCache($this->activeLocation);
        $this->dispatchToast('Menu order updated successfully!', 'success');
    }

    public function toggleStatus(int $id): void
    {
        $item = MenuItem::findOrFail($id);
        $item->update(['is_active' => ! $item->is_active]);
        MenuItem::clearMenuCache($this->activeLocation);
        $statusText = $item->is_active ? 'Item is now visible.' : 'Item is now hidden.';
        $this->dispatchToast($statusText, 'info');
    }

    public function startEdit(int $id): void
    {
        $item = MenuItem::findOrFail($id);
        $this->editingId = $item->id;
        $this->editingTitle = $item->title;
        $this->editingUrl = $item->url;
        $this->editingTarget = $item->target ?? '_self';
    }

    public function saveEdit(): void
    {
        if (! $this->editingId) {
            return;
        }

        $this->validate([
            'editingTitle' => 'required|string|max:100',
            'editingUrl' => 'required|string|max:255',
            'editingTarget' => 'required|in:_self,_blank',
        ]);

        $item = MenuItem::findOrFail($this->editingId);
        $item->update([
            'title' => $this->editingTitle,
            'url' => $this->editingUrl,
            'target' => $this->editingTarget,
        ]);

        MenuItem::clearMenuCache($this->activeLocation);
        $this->cancelEdit();
        $this->dispatchToast('Menu item updated successfully!', 'success');
    }

    public function cancelEdit(): void
    {
        $this->reset(['editingId', 'editingTitle', 'editingUrl', 'editingTarget']);
    }

    public function deleteItem(int $id): void
    {
        MenuItem::destroy($id);
        MenuItem::clearMenuCache($this->activeLocation);
        $this->dispatchToast('Menu item removed.', 'info');
    }

    protected function dispatchToast(string $message, string $type = 'info'): void
    {
        $this->dispatch('toast', ['message' => $message, 'type' => $type]);
        $this->dispatch('notify', ['message' => $message, 'type' => $type]);
    }

    public function render()
    {
        return view('superadmin.menus.index', [
            'availablePages' => Page::where('is_active', true)->orderBy('title')->get(),
            'menuItems' => MenuItem::where('location', $this->activeLocation)->orderBy('order_index')->get(),
        ]);
    }
}
