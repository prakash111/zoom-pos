<?php

namespace App\Livewire\Tenant\Languages;

use App\Models\Company;
use App\Models\Language;
use App\Services\Localization\LocalizationService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.tenant', ['title' => 'Languages & Translations'])]
class Index extends Component
{
    public string $storeDefaultLanguage = 'en';

    public string $selectedLocale = 'en';

    public string $searchQuery = '';

    // Translation dictionaries
    public array $baseTranslations = [];

    public array $customOverrides = [];

    public array $editingOverrides = [];

    // Add custom phrase modal
    public bool $showAddModal = false;

    public string $customKey = '';

    public string $customValue = '';

    public string $successMessage = '';

    public string $errorMessage = '';

    public function mount(LocalizationService $localization): void
    {
        $company = $this->getCompany();
        $this->storeDefaultLanguage = $company?->default_locale ?: ($company?->language ?: 'en');
        $this->selectedLocale = $this->storeDefaultLanguage;

        $this->loadTranslations($localization);
    }

    public function selectLocale(string $locale, LocalizationService $localization): void
    {
        $this->selectedLocale = $locale;
        $this->loadTranslations($localization);
    }

    public function loadTranslations(LocalizationService $localization): void
    {
        $this->reset(['successMessage', 'errorMessage']);
        $company = $this->getCompany();

        $this->baseTranslations = $localization->getLanguageFileContent($this->selectedLocale);
        $this->customOverrides = $company
            ? $localization->getTenantTranslations($company->id, $this->selectedLocale)
            : [];

        $this->editingOverrides = $this->customOverrides;
    }

    public function updateOverride(string $key, string $value): void
    {
        $this->editingOverrides[$key] = $value;
    }

    public function clearOverride(string $key, LocalizationService $localization): void
    {
        $company = $this->getCompany();
        if (! $company) {
            return;
        }

        $localization->deleteTenantTranslation($company->id, $this->selectedLocale, $key);
        $this->loadTranslations($localization);
        $this->successMessage = "Restored default translation for '{$key}'.";
    }

    public function saveAllOverrides(LocalizationService $localization): void
    {
        $this->reset(['successMessage', 'errorMessage']);
        $company = $this->getCompany();
        if (! $company) {
            return;
        }

        try {
            foreach ($this->editingOverrides as $key => $val) {
                // If value differs from base or is customized, save it
                if (isset($this->baseTranslations[$key]) && trim($val) === trim($this->baseTranslations[$key])) {
                    // Same as base, can remove override
                    $localization->deleteTenantTranslation($company->id, $this->selectedLocale, $key);
                } else {
                    $localization->saveTenantTranslation($company->id, $this->selectedLocale, $key, trim($val));
                }
            }

            $this->loadTranslations($localization);
            $this->successMessage = "🎉 Successfully saved custom store translations for [{$this->selectedLocale}]!";
        } catch (\Throwable $e) {
            $this->errorMessage = 'Failed to save translations: '.$e->getMessage();
        }
    }

    public function saveStoreDefaultLanguage(LocalizationService $localization): void
    {
        $company = $this->getCompany();
        if (! $company) {
            return;
        }

        $company->update([
            'default_locale' => $this->storeDefaultLanguage,
            'language' => $this->storeDefaultLanguage,
        ]);
        $localization->setLocale($this->storeDefaultLanguage);

        $this->successMessage = '✨ Store default language updated to '.strtoupper($this->storeDefaultLanguage).'.';
        session()->flash('status', $this->successMessage);
    }

    public function openAddModal(): void
    {
        $this->customKey = '';
        $this->customValue = '';
        $this->showAddModal = true;
    }

    public function addCustomPhrase(LocalizationService $localization): void
    {
        $this->validate([
            'customKey' => ['required', 'string', 'min:1', 'max:255'],
            'customValue' => ['required', 'string', 'min:1'],
        ]);

        $company = $this->getCompany();
        if (! $company) {
            return;
        }

        try {
            $localization->saveTenantTranslation($company->id, $this->selectedLocale, $this->customKey, $this->customValue);
            $this->showAddModal = false;
            $this->loadTranslations($localization);

            $this->successMessage = "Added custom phrase for '{$this->customKey}'.";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    protected function getCompany(): ?Company
    {
        $id = app()->bound('tenant.company_id')
            ? app('tenant.company_id')
            : auth('web')->user()?->company_id;

        return $id ? Company::withoutGlobalScopes()->find($id) : null;
    }

    public function render(LocalizationService $localization)
    {
        $languages = $localization->getActiveLanguages();
        $currentLanguage = Language::where('code', $this->selectedLocale)->first();

        // Filter translations
        $query = strtolower(trim($this->searchQuery));
        $filteredKeys = [];

        // All keys from base + custom overrides
        $allKeys = array_unique(array_merge(array_keys($this->baseTranslations), array_keys($this->editingOverrides)));

        foreach ($allKeys as $key) {
            $baseVal = $this->baseTranslations[$key] ?? $key;
            $overrideVal = $this->editingOverrides[$key] ?? '';

            if (
                $query === '' ||
                str_contains(strtolower($key), $query) ||
                str_contains(strtolower($baseVal), $query) ||
                str_contains(strtolower($overrideVal), $query)
            ) {
                $filteredKeys[] = $key;
            }
        }

        return view('livewire.tenant.languages.index', [
            'languages' => $languages,
            'currentLanguage' => $currentLanguage,
            'filteredKeys' => $filteredKeys,
            'company' => $this->getCompany(),
        ]);
    }
}
