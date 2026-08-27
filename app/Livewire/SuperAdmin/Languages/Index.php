<?php

namespace App\Livewire\SuperAdmin\Languages;

use App\Models\AuditLog;
use App\Models\Language;
use App\Services\Localization\LocalizationService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.superadmin', ['title' => 'Languages & Translations'])]
class Index extends Component
{
    public string $activeTab = 'editor'; // 'editor' | 'languages'

    public string $selectedLocale = 'en';

    public string $searchQuery = '';

    // Language creation modal
    public bool $showCreateModal = false;

    public string $newCode = '';

    public string $newName = '';

    public string $newNativeName = '';

    public string $newFlag = '🌐';

    public string $newDirection = 'ltr';

    public bool $newIsActive = true;

    // Add key modal
    public bool $showAddKeyModal = false;

    public string $newKey = '';

    public string $newValue = '';

    // File Editor State
    public array $translations = [];

    public array $modifiedKeys = [];

    public string $successMessage = '';

    public string $errorMessage = '';

    public function mount(LocalizationService $localization): void
    {
        abort_unless(auth('platform_web')->user()->hasRole('super_admin'), 403);
        $localization->ensureDefaultLanguages();

        $this->loadTranslations($localization);
    }

    public function selectLocale(string $locale, LocalizationService $localization): void
    {
        $this->selectedLocale = $locale;
        $this->loadTranslations($localization);
    }

    public function loadTranslations(LocalizationService $localization): void
    {
        $this->reset(['successMessage', 'errorMessage', 'modifiedKeys']);
        $this->translations = $localization->getLanguageFileContent($this->selectedLocale);
    }

    public function updatedTranslations(): void
    {
        // Track modified keys
    }

    public function updateKey(string $key, string $value): void
    {
        $this->translations[$key] = $value;
        $this->modifiedKeys[$key] = true;
    }

    public function saveTranslations(LocalizationService $localization): void
    {
        $this->reset(['successMessage', 'errorMessage']);

        try {
            $localization->saveLanguageFileContent($this->selectedLocale, $this->translations);
            $this->modifiedKeys = [];

            AuditLog::record(
                auth('platform_web')->user(),
                'platform.languages.save',
                "Updated translation dictionary for language [{$this->selectedLocale}] (".count($this->translations).' keys).'
            );

            $this->successMessage = '🎉 Successfully saved '.count($this->translations)." translations for [{$this->selectedLocale}]!";
        } catch (\Throwable $e) {
            $this->errorMessage = 'Failed to save translations: '.$e->getMessage();
        }
    }

    public function openAddKeyModal(): void
    {
        $this->newKey = '';
        $this->newValue = '';
        $this->showAddKeyModal = true;
    }

    public function addKey(LocalizationService $localization): void
    {
        $this->validate([
            'newKey' => ['required', 'string', 'min:1', 'max:255'],
            'newValue' => ['nullable', 'string'],
        ]);

        try {
            $localization->addTranslationKey($this->newKey, $this->newValue ?: $this->newKey);
            $this->loadTranslations($localization);
            $this->showAddKeyModal = false;

            $this->successMessage = "✨ Added new translation key '{$this->newKey}' globally across all language dictionaries!";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function deleteKey(string $key, LocalizationService $localization): void
    {
        try {
            $localization->deleteTranslationKey($this->selectedLocale, $key);
            $this->loadTranslations($localization);
            $this->successMessage = "🗑️ Deleted key '{$key}' from [{$this->selectedLocale}].";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function syncMissingFromEnglish(LocalizationService $localization): void
    {
        try {
            $added = $localization->syncMissingKeys($this->selectedLocale, 'en');
            $this->loadTranslations($localization);
            $this->successMessage = "🔄 Synchronized {$added} missing keys from English template into [{$this->selectedLocale}]!";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function toggleLanguageStatus(int $languageId): void
    {
        $lang = Language::findOrFail($languageId);
        $lang->update(['is_active' => ! $lang->is_active]);

        $status = $lang->is_active ? 'enabled' : 'disabled';
        $this->successMessage = "Language '{$lang->name}' is now {$status}.";
    }

    public function createLanguage(LocalizationService $localization): void
    {
        $this->validate([
            'newCode' => ['required', 'string', 'min:2', 'max:10', 'unique:languages,code'],
            'newName' => ['required', 'string', 'min:2', 'max:100'],
            'newNativeName' => ['nullable', 'string', 'max:100'],
            'newFlag' => ['nullable', 'string', 'max:20'],
            'newDirection' => ['required', 'in:ltr,rtl'],
        ]);

        try {
            $lang = $localization->createLanguage([
                'code' => $this->newCode,
                'name' => $this->newName,
                'native_name' => $this->newNativeName,
                'flag' => $this->newFlag,
                'direction' => $this->newDirection,
                'is_active' => $this->newIsActive,
            ]);

            $this->showCreateModal = false;
            $this->selectedLocale = $lang->code;
            $this->loadTranslations($localization);

            $this->successMessage = "🎉 Language '{$lang->name}' ({$lang->code}) created successfully with initial translation dictionary!";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function render(LocalizationService $localization)
    {
        $languages = $localization->getAllLanguages();
        $currentLanguage = Language::where('code', $this->selectedLocale)->first();

        // Filter translations by search query
        $filteredTranslations = [];
        $query = strtolower(trim($this->searchQuery));

        foreach ($this->translations as $k => $v) {
            if ($query === '' || str_contains(strtolower($k), $query) || str_contains(strtolower($v), $query)) {
                $filteredTranslations[$k] = $v;
            }
        }

        return view('livewire.superadmin.languages.index', [
            'languages' => $languages,
            'currentLanguage' => $currentLanguage,
            'filteredTranslations' => $filteredTranslations,
            'totalKeyCount' => count($this->translations),
        ]);
    }
}
