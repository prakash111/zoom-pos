<?php

namespace App\Livewire\Tenant\Storefront;

use App\Models\Category;
use App\Models\Company;
use App\Models\TenantCustomPage;
use App\Models\TenantStoreMenu;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.tenant', ['title' => 'Navigation Menus & CMS Pages'])]
class MenuBuilderComponent extends Component
{
    public string $activeLocation = TenantStoreMenu::LOCATION_HEADER;

    public array $selectedPages = [];

    public array $selectedCategories = [];

    public string $customTitle = '';

    public string $customUrl = '';

    public bool $customTargetBlank = false;

    // Inline edit properties for menu items
    public ?int $editingId = null;

    public string $editingTitle = '';

    public string $editingUrl = '';

    public string $editingTarget = '_self';

    // CMS Page management properties
    public bool $showPageModal = false;

    public ?int $editingPageId = null;

    public string $pageTitle = '';

    public string $pageSlug = '';

    public string $pageContent = '';

    public string $pageMetaTitle = '';

    public string $pageMetaDescription = '';

    public bool $pageIsPublished = true;

    protected function getCompanyId(): string
    {
        $id = app()->bound('tenant.company_id') ? app('tenant.company_id') : auth()->user()?->company_id;

        return (string) ($id ?? Company::first()?->id ?? '');
    }

    public function mount(): void
    {
        $companyId = $this->getCompanyId();
        if ($companyId) {
            TenantCustomPage::seedDefaultsForCompany($companyId);
            TenantStoreMenu::seedDefaultsForCompany($companyId);
        }
    }

    public function updatedActiveLocation(): void
    {
        $this->cancelEdit();
    }

    public function addSelectedPages(): void
    {
        $companyId = $this->getCompanyId();
        if (empty($this->selectedPages)) {
            $this->dispatchToast('Please select at least one CMS page.', 'warning');

            return;
        }

        $pages = TenantCustomPage::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereIn('id', $this->selectedPages)
            ->get();

        $maxOrder = (int) (TenantStoreMenu::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('location', $this->activeLocation)
            ->max('sort_order') ?? 0);

        foreach ($pages as $index => $page) {
            TenantStoreMenu::create([
                'company_id' => $companyId,
                'tenant_id' => $companyId,
                'location' => $this->activeLocation,
                'title' => $page->title,
                'type' => TenantStoreMenu::TYPE_CMS_PAGE,
                'target_url' => '/page/'.$page->slug,
                'page_id' => $page->id,
                'target' => '_self',
                'sort_order' => $maxOrder + $index + 1,
                'is_visible' => true,
            ]);
        }

        $this->reset('selectedPages');
        $this->dispatchToast('CMS pages added to navigation!', 'success');
    }

    public function addSelectedCategories(): void
    {
        $companyId = $this->getCompanyId();
        if (empty($this->selectedCategories)) {
            $this->dispatchToast('Please select at least one category.', 'warning');

            return;
        }

        $categories = Category::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereIn('id', $this->selectedCategories)
            ->get();

        $maxOrder = (int) (TenantStoreMenu::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('location', $this->activeLocation)
            ->max('sort_order') ?? 0);

        foreach ($categories as $index => $cat) {
            TenantStoreMenu::create([
                'company_id' => $companyId,
                'tenant_id' => $companyId,
                'location' => $this->activeLocation,
                'title' => $cat->name,
                'type' => TenantStoreMenu::TYPE_CATEGORY,
                'target_url' => '#products-section',
                'category_id' => $cat->id,
                'target' => '_self',
                'sort_order' => $maxOrder + $index + 1,
                'is_visible' => true,
            ]);
        }

        $this->reset('selectedCategories');
        $this->dispatchToast('Categories added to navigation!', 'success');
    }

    public function addAnchorLink(string $title, string $url): void
    {
        $companyId = $this->getCompanyId();
        $maxOrder = (int) (TenantStoreMenu::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('location', $this->activeLocation)
            ->max('sort_order') ?? 0);

        TenantStoreMenu::create([
            'company_id' => $companyId,
            'tenant_id' => $companyId,
            'location' => $this->activeLocation,
            'title' => $title,
            'type' => TenantStoreMenu::TYPE_ANCHOR,
            'target_url' => $url,
            'target' => '_self',
            'sort_order' => $maxOrder + 1,
            'is_visible' => true,
        ]);

        $this->dispatchToast('Section anchor added!', 'success');
    }

    public function addCustomLink(): void
    {
        $this->validate([
            'customTitle' => 'required|string|max:100',
            'customUrl' => 'required|string|max:255',
        ]);

        $companyId = $this->getCompanyId();
        $maxOrder = (int) (TenantStoreMenu::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('location', $this->activeLocation)
            ->max('sort_order') ?? 0);

        TenantStoreMenu::create([
            'company_id' => $companyId,
            'tenant_id' => $companyId,
            'location' => $this->activeLocation,
            'title' => $this->customTitle,
            'type' => TenantStoreMenu::TYPE_CUSTOM_URL,
            'target_url' => $this->customUrl,
            'target' => $this->customTargetBlank ? '_blank' : '_self',
            'sort_order' => $maxOrder + 1,
            'is_visible' => true,
        ]);

        $this->reset(['customTitle', 'customUrl', 'customTargetBlank']);
        $this->dispatchToast('Custom link added!', 'success');
    }

    public function updateMenuOrder(array $orderedIds): void
    {
        $companyId = $this->getCompanyId();
        foreach ($orderedIds as $index => $id) {
            TenantStoreMenu::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('id', $id)
                ->update(['sort_order' => $index + 1]);
        }
        $this->dispatchToast('Menu order updated successfully!', 'success');
    }

    public function toggleStatus(int $id): void
    {
        $companyId = $this->getCompanyId();
        $item = TenantStoreMenu::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->findOrFail($id);

        $item->update(['is_visible' => ! $item->is_visible]);
        $statusText = $item->is_visible ? 'Item is now visible.' : 'Item is now hidden.';
        $this->dispatchToast($statusText, 'info');
    }

    public function startEdit(int $id): void
    {
        $companyId = $this->getCompanyId();
        $item = TenantStoreMenu::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->findOrFail($id);

        $this->editingId = $item->id;
        $this->editingTitle = $item->title;
        $this->editingUrl = $item->target_url ?? $item->resolved_url ?? '';
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

        $companyId = $this->getCompanyId();
        $item = TenantStoreMenu::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->findOrFail($this->editingId);

        $item->update([
            'title' => $this->editingTitle,
            'target_url' => $this->editingUrl,
            'target' => $this->editingTarget,
        ]);

        $this->cancelEdit();
        $this->dispatchToast('Menu item updated successfully!', 'success');
    }

    public function cancelEdit(): void
    {
        $this->reset(['editingId', 'editingTitle', 'editingUrl', 'editingTarget']);
    }

    public function deleteItem(int $id): void
    {
        $companyId = $this->getCompanyId();
        TenantStoreMenu::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('id', $id)
            ->delete();

        $this->dispatchToast('Menu item removed.', 'info');
    }

    // =========================================================================
    // CMS PAGES MANAGEMENT
    // =========================================================================

    public function openCreatePageModal(): void
    {
        $this->reset(['editingPageId', 'pageTitle', 'pageSlug', 'pageContent', 'pageMetaTitle', 'pageMetaDescription']);
        $this->pageIsPublished = true;
        $this->showPageModal = true;
    }

    public function openEditPageModal(int $id): void
    {
        $companyId = $this->getCompanyId();
        $page = TenantCustomPage::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->findOrFail($id);

        $this->editingPageId = $page->id;
        $this->pageTitle = $page->title;
        $this->pageSlug = $page->slug;
        $this->pageContent = $page->content ?? '';
        $this->pageMetaTitle = $page->meta_title ?? '';
        $this->pageMetaDescription = $page->meta_description ?? '';
        $this->pageIsPublished = (bool) $page->is_published;
        $this->showPageModal = true;
    }

    public function closePageModal(): void
    {
        $this->showPageModal = false;
        $this->reset(['editingPageId', 'pageTitle', 'pageSlug', 'pageContent', 'pageMetaTitle', 'pageMetaDescription']);
    }

    public function savePage(): void
    {
        $this->validate([
            'pageTitle' => 'required|string|max:150',
            'pageSlug' => 'nullable|string|max:150',
            'pageContent' => 'nullable|string',
            'pageMetaTitle' => 'nullable|string|max:255',
            'pageMetaDescription' => 'nullable|string',
        ]);

        $companyId = $this->getCompanyId();
        $slug = ! empty($this->pageSlug)
            ? Str::slug($this->pageSlug)
            : Str::slug($this->pageTitle);

        if ($this->editingPageId) {
            $page = TenantCustomPage::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->findOrFail($this->editingPageId);

            // Check slug uniqueness
            $existing = TenantCustomPage::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('slug', $slug)
                ->where('id', '!=', $page->id)
                ->exists();

            if ($existing) {
                $slug = $slug.'-'.time();
            }

            $page->update([
                'title' => $this->pageTitle,
                'slug' => $slug,
                'content' => $this->pageContent,
                'meta_title' => $this->pageMetaTitle,
                'meta_description' => $this->pageMetaDescription,
                'is_published' => $this->pageIsPublished,
            ]);

            // Update any menu items pointing to this page
            TenantStoreMenu::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('page_id', $page->id)
                ->update([
                    'title' => $this->pageTitle,
                    'target_url' => '/page/'.$slug,
                ]);

            $this->dispatchToast('CMS page updated successfully!', 'success');
        } else {
            // New page slug check
            $originalSlug = $slug;
            $counter = 1;
            while (TenantCustomPage::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('slug', $slug)
                ->exists()) {
                $slug = $originalSlug.'-'.$counter;
                $counter++;
            }

            TenantCustomPage::create([
                'company_id' => $companyId,
                'tenant_id' => $companyId,
                'title' => $this->pageTitle,
                'slug' => $slug,
                'content' => $this->pageContent,
                'meta_title' => $this->pageMetaTitle,
                'meta_description' => $this->pageMetaDescription,
                'is_published' => $this->pageIsPublished,
            ]);

            $this->dispatchToast('Custom CMS page created successfully!', 'success');
        }

        $this->closePageModal();
    }

    public function deletePage(int $id): void
    {
        $companyId = $this->getCompanyId();
        $page = TenantCustomPage::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->findOrFail($id);

        // Nullify reference in menus
        TenantStoreMenu::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('page_id', $page->id)
            ->update(['page_id' => null]);

        $page->delete();
        $this->dispatchToast('CMS page deleted.', 'info');
    }

    protected function dispatchToast(string $message, string $type = 'info'): void
    {
        $this->dispatch('toast', ['message' => $message, 'type' => $type]);
        $this->dispatch('notify', ['message' => $message, 'type' => $type]);
    }

    public function render()
    {
        $companyId = $this->getCompanyId();

        $availablePages = TenantCustomPage::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->orderBy('title')
            ->get();

        $availableCategories = Category::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get();

        $menuItems = TenantStoreMenu::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('location', $this->activeLocation)
            ->with(['page:id,title,slug', 'category:id,name'])
            ->ordered()
            ->get();

        return view('livewire.tenant.storefront.menu-builder', [
            'availablePages' => $availablePages,
            'availableCategories' => $availableCategories,
            'menuItems' => $menuItems,
            'locations' => TenantStoreMenu::LOCATIONS,
        ]);
    }
}
