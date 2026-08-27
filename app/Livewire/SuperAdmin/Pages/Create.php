<?php

namespace App\Livewire\SuperAdmin\Pages;

use App\Models\AuditLog;
use App\Models\Page;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.superadmin', ['title' => 'New Page'])]
class Create extends Component
{
    public string $title = '';

    public string $slug = '';

    public string $content = '';

    public string $metaDescription = '';

    public bool $isActive = true;

    public bool $showInFooter = true;

    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/', Rule::unique('pages', 'slug')],
            'content' => ['nullable', 'string'],
            'metaDescription' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function save(): void
    {
        $data = $this->validate();

        $page = Page::create([
            'title' => $data['title'],
            'slug' => $data['slug'] ?: Page::uniqueSlugFrom($data['title']),
            'content' => $data['content'] ?? '',
            'meta_description' => $data['metaDescription'] ?: null,
            'is_active' => $this->isActive,
            'show_in_footer' => $this->showInFooter,
            'created_by' => auth('platform_web')->id(),
        ]);

        AuditLog::record('page.created', null, auth('platform_web')->id(), ['page_id' => $page->id, 'title' => $page->title]);

        session()->flash('success', 'Page content saved successfully.');
        session()->flash('status', "Page \"{$page->title}\" created.");

        $this->redirect(route('superadmin.pages.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.superadmin.pages.create');
    }
}
