<?php

namespace App\Livewire\SuperAdmin\Pages;

use App\Models\AuditLog;
use App\Models\Page;
use App\Models\PlatformBranding;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.superadmin', ['title' => 'Custom Pages'])]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function delete(int $id): void
    {
        $page = Page::findOrFail($id);

        $branding = PlatformBranding::current();
        if ($branding->landing_page_id === $page->id) {
            $branding->update(['landing_page_id' => null, 'landing_page_enabled' => false]);
        }

        $title = $page->title;
        $page->delete();

        AuditLog::record('page.deleted', null, auth('platform_web')->id(), ['title' => $title]);
        session()->flash('status', "Page \"{$title}\" deleted.");
    }

    public function render()
    {
        $pages = Page::query()
            ->when($this->search, function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(fn ($sq) => $sq->where('title', 'like', $term)->orWhere('slug', 'like', $term));
            })
            ->orderByDesc('updated_at')
            ->paginate(15);

        return view('livewire.superadmin.pages.index', [
            'pages' => $pages,
            'landingPageId' => PlatformBranding::current()->landing_page_id,
        ]);
    }
}
