<?php

namespace App\Livewire\SuperAdmin\Inquiries;

use App\Models\AuditLog;
use App\Models\ContactInquiry;
use App\Services\ContactFormService;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.superadmin', ['title' => 'Web Inquiries & Custom Form Builder'])]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'tab')]
    public string $tab = 'inquiries'; // 'inquiries', 'builder', 'preview'

    // Inquiries Tab State
    public string $search = '';
    public string $statusFilter = 'all'; // 'all', 'new', 'read', 'replied'
    public array $selectedInquiries = [];
    public bool $selectAll = false;

    // View Modal State
    public bool $showDetailModal = false;
    public ?int $viewingInquiryId = null;
    public ?ContactInquiry $viewingInquiry = null;

    // Field Builder State
    public array $fields = [];
    public bool $showFieldModal = false;
    public bool $isEditingField = false;
    public ?string $editingFieldId = null;

    public string $fieldLabel = '';
    public string $fieldName = '';
    public string $fieldType = 'text';
    public string $fieldPlaceholder = '';
    public string $fieldOptions = '';
    public string $fieldWidth = 'half';
    public bool $fieldRequired = false;

    // Form Page Settings State
    public string $pageTitle = '';
    public string $pageSubtitle = '';
    public string $submitButtonText = '';
    public string $successMessage = '';
    public string $recipientEmail = '';
    public bool $formEnabled = true;

    public function mount(): void
    {
        $this->tab = request()->query('tab', $this->tab);
        if (! in_array($this->tab, ['inquiries', 'builder', 'preview'], true)) {
            $this->tab = 'inquiries';
        }

        $this->loadFieldsAndSettings();
    }

    public function loadFieldsAndSettings(): void
    {
        $this->fields = ContactFormService::getFields();
        $settings = ContactFormService::getSettings();

        $this->pageTitle = $settings['page_title'];
        $this->pageSubtitle = $settings['page_subtitle'];
        $this->submitButtonText = $settings['submit_button_text'];
        $this->successMessage = $settings['success_message'];
        $this->recipientEmail = $settings['recipient_email'];
        $this->formEnabled = $settings['enabled'];
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['inquiries', 'builder', 'preview'], true)) {
            $this->tab = $tab;
            $this->resetPage();
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSelectAll(bool $value): void
    {
        if ($value) {
            $this->selectedInquiries = ContactInquiry::query()
                ->search($this->search)
                ->status($this->statusFilter)
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->all();
        } else {
            $this->selectedInquiries = [];
        }
    }

    // --- INQUIRY ACTIONS ---
    public function viewInquiry(int $id): void
    {
        $inquiry = ContactInquiry::findOrFail($id);
        $this->viewingInquiryId = $inquiry->id;
        $this->viewingInquiry = $inquiry;

        if ($inquiry->isNew()) {
            $inquiry->markAsRead();
            $this->dispatch('inquiry-status-changed');
        }

        $this->showDetailModal = true;
    }

    public function closeDetailModal(): void
    {
        $this->showDetailModal = false;
        $this->viewingInquiryId = null;
        $this->viewingInquiry = null;
    }

    public function updateStatus(int $id, string $status): void
    {
        if (in_array($status, [ContactInquiry::STATUS_NEW, ContactInquiry::STATUS_READ, ContactInquiry::STATUS_REPLIED], true)) {
            $inquiry = ContactInquiry::findOrFail($id);
            $inquiry->update(['status' => $status]);

            if ($this->viewingInquiry && $this->viewingInquiry->id === $id) {
                $this->viewingInquiry->status = $status;
            }

            $this->dispatch('toast', ['type' => 'success', 'message' => "Inquiry status updated to {$status}."]);
        }
    }

    public function deleteInquiry(int $id): void
    {
        $inquiry = ContactInquiry::findOrFail($id);
        $name = $inquiry->name;
        $inquiry->delete();

        if ($this->viewingInquiryId === $id) {
            $this->closeDetailModal();
        }

        AuditLog::record('inquiry.deleted', null, auth('platform_web')->id(), ['inquiry_id' => $id, 'name' => $name]);
        $this->dispatch('toast', ['type' => 'success', 'message' => "Inquiry from {$name} deleted successfully."]);
    }

    public function bulkMarkAsRead(): void
    {
        if (empty($this->selectedInquiries)) {
            return;
        }

        ContactInquiry::whereIn('id', $this->selectedInquiries)->update(['status' => ContactInquiry::STATUS_READ]);
        $this->selectedInquiries = [];
        $this->selectAll = false;
        $this->dispatch('toast', ['type' => 'success', 'message' => 'Selected inquiries marked as read.']);
    }

    public function bulkMarkAsReplied(): void
    {
        if (empty($this->selectedInquiries)) {
            return;
        }

        ContactInquiry::whereIn('id', $this->selectedInquiries)->update(['status' => ContactInquiry::STATUS_REPLIED]);
        $this->selectedInquiries = [];
        $this->selectAll = false;
        $this->dispatch('toast', ['type' => 'success', 'message' => 'Selected inquiries marked as replied.']);
    }

    public function bulkDelete(): void
    {
        if (empty($this->selectedInquiries)) {
            return;
        }

        $count = count($this->selectedInquiries);
        ContactInquiry::whereIn('id', $this->selectedInquiries)->delete();
        $this->selectedInquiries = [];
        $this->selectAll = false;

        AuditLog::record('inquiries.bulk_deleted', null, auth('platform_web')->id(), ['count' => $count]);
        $this->dispatch('toast', ['type' => 'success', 'message' => "{$count} inquiries deleted successfully."]);
    }

    // --- FORM BUILDER ACTIONS ---
    public function openAddFieldModal(): void
    {
        $this->isEditingField = false;
        $this->editingFieldId = null;
        $this->fieldLabel = '';
        $this->fieldName = '';
        $this->fieldType = 'text';
        $this->fieldPlaceholder = '';
        $this->fieldOptions = '';
        $this->fieldWidth = 'half';
        $this->fieldRequired = false;
        $this->showFieldModal = true;
    }

    public function editField(string $id): void
    {
        $field = collect($this->fields)->firstWhere('id', $id);
        if (! $field) {
            return;
        }

        $this->isEditingField = true;
        $this->editingFieldId = $id;
        $this->fieldLabel = $field['label'] ?? '';
        $this->fieldName = $field['name'] ?? '';
        $this->fieldType = $field['type'] ?? 'text';
        $this->fieldPlaceholder = $field['placeholder'] ?? '';
        $this->fieldOptions = is_array($field['options'] ?? '') ? implode(', ', $field['options']) : ($field['options'] ?? '');
        $this->fieldWidth = $field['width'] ?? 'half';
        $this->fieldRequired = (bool) ($field['required'] ?? false);
        $this->showFieldModal = true;
    }

    public function closeFieldModal(): void
    {
        $this->showFieldModal = false;
        $this->editingFieldId = null;
    }

    public function updatedFieldLabel(string $value): void
    {
        if (! $this->isEditingField && blank($this->fieldName)) {
            $this->fieldName = Str::slug($value, '_');
        }
    }

    public function saveField(): void
    {
        $this->validate([
            'fieldLabel' => ['required', 'string', 'max:120'],
            'fieldName' => ['required', 'string', 'max:60', 'regex:/^[a-zA-Z0-9_]+$/'],
            'fieldType' => ['required', 'string', 'in:text,email,tel,number,textarea,select,checkbox'],
            'fieldWidth' => ['required', 'string', 'in:half,full'],
            'fieldPlaceholder' => ['nullable', 'string', 'max:255'],
            'fieldOptions' => ['nullable', 'string', 'max:2000'],
        ]);

        $name = Str::slug($this->fieldName, '_');

        if ($this->isEditingField && $this->editingFieldId) {
            $this->fields = collect($this->fields)->map(function ($f) use ($name) {
                if (($f['id'] ?? '') === $this->editingFieldId) {
                    $isSystem = in_array($name, ['name', 'email', 'message'], true);
                    return [
                        'id' => $f['id'],
                        'name' => $name,
                        'label' => trim($this->fieldLabel),
                        'type' => $this->fieldType,
                        'placeholder' => trim($this->fieldPlaceholder),
                        'required' => $isSystem ? true : $this->fieldRequired,
                        'width' => $this->fieldWidth,
                        'options' => trim($this->fieldOptions),
                        'is_system' => $isSystem,
                    ];
                }
                return $f;
            })->all();

            $msg = "Field \"{$this->fieldLabel}\" updated successfully.";
        } else {
            // Check for duplicate name
            if (collect($this->fields)->contains('name', $name)) {
                $name = $name . '_' . (count($this->fields) + 1);
            }

            $isSystem = in_array($name, ['name', 'email', 'message'], true);

            $this->fields[] = [
                'id' => (string) Str::uuid(),
                'name' => $name,
                'label' => trim($this->fieldLabel),
                'type' => $this->fieldType,
                'placeholder' => trim($this->fieldPlaceholder),
                'required' => $isSystem ? true : $this->fieldRequired,
                'width' => $this->fieldWidth,
                'options' => trim($this->fieldOptions),
                'is_system' => $isSystem,
            ];

            $msg = "New custom field \"{$this->fieldLabel}\" added successfully.";
        }

        ContactFormService::saveFields($this->fields);
        $this->closeFieldModal();
        $this->dispatch('toast', ['type' => 'success', 'message' => $msg]);
    }

    public function deleteField(string $id): void
    {
        $field = collect($this->fields)->firstWhere('id', $id);
        if (! $field) {
            return;
        }

        if (! empty($field['is_system'])) {
            $this->dispatch('toast', ['type' => 'error', 'message' => 'System fields (Name, Email, Message) cannot be deleted.']);
            return;
        }

        $this->fields = collect($this->fields)->reject(fn ($f) => ($f['id'] ?? '') === $id)->values()->all();
        ContactFormService::saveFields($this->fields);

        $this->dispatch('toast', ['type' => 'success', 'message' => "Field \"{$field['label']}\" removed."]);
    }

    public function moveUp(int $index): void
    {
        if ($index > 0 && isset($this->fields[$index])) {
            $prev = $this->fields[$index - 1];
            $this->fields[$index - 1] = $this->fields[$index];
            $this->fields[$index] = $prev;

            ContactFormService::saveFields($this->fields);
        }
    }

    public function moveDown(int $index): void
    {
        if ($index < count($this->fields) - 1 && isset($this->fields[$index])) {
            $next = $this->fields[$index + 1];
            $this->fields[$index + 1] = $this->fields[$index];
            $this->fields[$index] = $next;

            ContactFormService::saveFields($this->fields);
        }
    }

    public function resetDefaultFields(): void
    {
        $this->fields = ContactFormService::getDefaultFields();
        ContactFormService::saveFields($this->fields);

        $this->dispatch('toast', ['type' => 'success', 'message' => 'Contact form fields reset to standard defaults.']);
    }

    // --- FORM SETTINGS ACTIONS ---
    public function saveFormSettings(): void
    {
        $this->validate([
            'pageTitle' => ['required', 'string', 'max:255'],
            'pageSubtitle' => ['nullable', 'string', 'max:500'],
            'submitButtonText' => ['required', 'string', 'max:100'],
            'successMessage' => ['required', 'string', 'max:500'],
            'recipientEmail' => ['nullable', 'email', 'max:255'],
            'formEnabled' => ['boolean'],
        ]);

        ContactFormService::saveSettings([
            'page_title' => $this->pageTitle,
            'page_subtitle' => $this->pageSubtitle,
            'submit_button_text' => $this->submitButtonText,
            'success_message' => $this->successMessage,
            'recipient_email' => $this->recipientEmail,
            'enabled' => $this->formEnabled,
        ]);

        AuditLog::record('contact_settings.updated', null, auth('platform_web')->id());
        $this->dispatch('toast', ['type' => 'success', 'message' => 'Contact form settings saved successfully.']);
    }

    public function render()
    {
        $stats = [
            'total' => ContactInquiry::count(),
            'new' => ContactInquiry::where('status', ContactInquiry::STATUS_NEW)->count(),
            'read' => ContactInquiry::where('status', ContactInquiry::STATUS_READ)->count(),
            'replied' => ContactInquiry::where('status', ContactInquiry::STATUS_REPLIED)->count(),
            'custom_fields_count' => count($this->fields),
        ];

        $inquiries = ContactInquiry::query()
            ->search($this->search)
            ->status($this->statusFilter)
            ->latest('id')
            ->paginate(15);

        return view('livewire.superadmin.inquiries.index', [
            'inquiries' => $inquiries,
            'stats' => $stats,
        ]);
    }
}
