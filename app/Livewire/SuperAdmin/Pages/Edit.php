<?php

namespace App\Livewire\SuperAdmin\Pages;

use App\Models\AuditLog;
use App\Models\Page;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.superadmin', ['title' => 'Edit Page'])]
class Edit extends Component
{
    public Page $page;

    public string $title = '';

    public string $slug = '';

    public string $content = '';

    public string $metaDescription = '';

    public bool $isActive = true;

    public bool $showInFooter = true;

    public function mount(Page $page): void
    {
        $this->page = $page;
        $this->title = $page->title;
        $this->slug = $page->slug;
        $this->content = (string) $page->content; // Guarantee non-null string
        $this->metaDescription = (string) $page->meta_description;
        $this->isActive = (bool) $page->is_active;
        $this->showInFooter = (bool) $page->show_in_footer;
    }

    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/', Rule::unique('pages', 'slug')->ignore($this->page->id)],
            'content' => ['nullable', 'string'],
            'metaDescription' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function save(): void
    {
        $data = $this->validate();

        $this->page->update([
            'title' => $data['title'],
            'slug' => $data['slug'],
            'content' => $data['content'] ?? '',
            'meta_description' => $data['metaDescription'] ?: null,
            'is_active' => $this->isActive,
            'show_in_footer' => $this->showInFooter,
        ]);

        AuditLog::record('page.updated', null, auth('platform_web')->id(), ['page_id' => $this->page->id, 'title' => $this->page->title]);

        session()->flash('success', 'Page content saved successfully.');
        session()->flash('status', "Page \"{$this->page->title}\" updated.");

        $this->redirect(route('superadmin.pages.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.superadmin.pages.edit');
    }
}
