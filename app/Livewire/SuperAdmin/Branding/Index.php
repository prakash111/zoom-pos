<?php

namespace App\Livewire\SuperAdmin\Branding;

use App\Models\AuditLog;
use App\Models\DynamicSetting;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\PlatformBranding;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.superadmin', ['title' => 'Landing Page & Branding Studio'])]
class Index extends Component
{
    use WithFileUploads;

    public string $platformName = '';
    public string $logoUrl = '';
    public $logoImage = null;
    public string $faviconUrl = '';

    public bool $showAuthBanner = false;
    public string $authBannerImageUrl = '';
    public $authBannerImage = null;
    public bool $enableRegistrationDomainSetup = true;

    public string $primaryColor = '#4f46e5';
    public string $superadminSidebarColor = '#4338ca';
    public string $landingPrimaryColor = '#10b981';
    public string $landingAccentColor = '#d7f24e';

    public string $supportEmail = '';
    public string $supportPhone = '';
    public bool $otpRegistrationEnabled = false;

    public bool $landingPageEnabled = true;
    public ?int $landingPageId = null;
    public string $landingTheme = 'theme_fast';
    public string $activeStudioTab = 'hero';
    public string $activeTab = 'landing'; // 'landing', 'menus', 'identity'
    public string $homepageMode = 'modular'; // 'modular' or 'static_page'

    // Menu Management State
    public string $menuLocation = 'header'; // 'header', 'footer_col_1', 'footer_col_2'
    public string $newMenuTitle = '';
    public string $newMenuUrl = '';
    public ?int $newMenuPageId = null;
    public bool $newMenuTargetBlank = false;

    public ?int $editingMenuItemId = null;
    public string $editingMenuItemTitle = '';
    public string $editingMenuItemUrl = '';
    public string $editingMenuItemTarget = '_self';
    public bool $editingMenuItemActive = true;

    // Section Visibility Toggles
    public bool $sectionHero = true;
    public bool $sectionTrustBar = true;
    public bool $sectionFeatures = true;
    public bool $sectionSolutions = true;
    public bool $sectionDownloads = true;
    public bool $sectionStats = true;
    public bool $sectionAbout = true;
    public bool $sectionTestimonials = true;
    public bool $sectionPricing = true;
    public bool $sectionFaq = true;
    public bool $sectionContact = true;
    public bool $sectionCta = true;

    // Section Ordering
    public array $landingSectionOrder = [];

    // App Download Links
    public string $landingPlaystoreUrl = '';
    public bool $landingPlaystoreEnabled = false;
    public string $landingWindowsUrl = '';
    public bool $landingWindowsEnabled = false;

    // Hero Section Customization
    public string $landingHeroBadge = '';
    public string $landingHeroTitle = '';
    public string $landingHeroSubtitle = '';
    public string $landingHeroCtaPrimaryText = '';
    public string $landingHeroCtaPrimaryUrl = '';
    public string $landingHeroCtaSecondaryText = '';
    public string $landingHeroCtaSecondaryUrl = '';
    public string $landingHeroBannerImageUrl = '';
    public $landingHeroBannerImage = null;
    public string $landingHeroDashboardTitle = '';
    public string $landingHeroDashboardStatus = '';
    public string $landingHeroTotalLabel = '';
    public string $landingHeroPaymentLabel = '';
    public string $landingHeroTotalAmount = '';
    public array $landingHeroHighlights = [];
    public array $landingHeroProducts = [];

    // Per-Section Meta
    public array $sectionMeta = [];

    // Section Lists / Repeaters
    public array $landingHardwareItems = [];
    public array $landingFeatures = [];
    public array $landingSolutions = [];
    public array $landingStats = [];
    public array $landingTestimonials = [];
    public array $landingFaqs = [];

    // Pricing & CTA specifics
    public string $pricingDiscountBadge = '';
    public string $pricingNote = '';
    public string $ctaPrimaryText = '';
    public string $ctaPrimaryUrl = '';
    public string $ctaSecondaryText = '';
    public string $ctaSecondaryUrl = '';

    // Advanced overrides
    public string $landingCustomHtml = '';
    public string $landingContentJson = '';

    public function mount(): void
    {
        $branding = PlatformBranding::current();

        $this->platformName = (string) ($branding->platform_name ?: 'Smart Inventory & Sales');
        $this->logoUrl = (string) ($branding->logo_url ?: DynamicSetting::get('platform_logo_url', ''));
        $this->faviconUrl = (string) $branding->favicon_url;
        $this->showAuthBanner = (bool) DynamicSetting::get('show_auth_banner', false);
        $this->authBannerImageUrl = (string) DynamicSetting::get('auth_banner_image_url', '');
        $this->enableRegistrationDomainSetup = (bool) DynamicSetting::get('enable_registration_domain_setup', true);

        $this->primaryColor = $branding->primary_color ?? '#4f46e5';
        $this->superadminSidebarColor = $branding->superadmin_sidebar_color ?? '#4338ca';
        $this->landingPrimaryColor = $branding->landing_primary_color ?? '#10b981';
        $this->landingAccentColor = $branding->landing_accent_color ?? '#d7f24e';
        $this->supportEmail = (string) $branding->support_email;
        $this->supportPhone = (string) $branding->support_phone;
        $this->otpRegistrationEnabled = (bool) $branding->otp_registration_enabled;
        $this->landingPageEnabled = (bool) $branding->landing_page_enabled;
        $this->landingPageId = $branding->landing_page_id;
        $this->homepageMode = (string) data_get($branding->landing_content, 'homepage_mode', ($branding->landing_page_id ? 'static_page' : 'modular'));

        $this->landingTheme = (string) setting('landing_page_theme', 'theme_fast');

        $cfg = $branding->landing_sections_config ?? [];
        $this->sectionHero = (bool) ($cfg['hero'] ?? true);
        $this->sectionTrustBar = (bool) ($cfg['trust_bar'] ?? true);
        $this->sectionFeatures = (bool) ($cfg['features'] ?? true);
        $this->sectionSolutions = (bool) ($cfg['solutions'] ?? true);
        $this->sectionDownloads = (bool) ($cfg['downloads'] ?? $branding->hasAnyDownloadLink());
        $this->sectionStats = (bool) ($cfg['stats'] ?? true);
        $this->sectionAbout = (bool) ($cfg['about'] ?? true);
        $this->sectionTestimonials = (bool) ($cfg['testimonials'] ?? true);
        $this->sectionPricing = (bool) ($cfg['pricing'] ?? true);
        $this->sectionFaq = (bool) ($cfg['faq'] ?? true);
        $this->sectionContact = (bool) ($cfg['contact'] ?? true);
        $this->sectionCta = (bool) ($cfg['cta'] ?? true);

        $this->landingSectionOrder = $branding->landingSectionOrder();

        $this->landingPlaystoreUrl = (string) ($branding->landing_playstore_url ?? '');
        $this->landingPlaystoreEnabled = (bool) $branding->landing_playstore_enabled;
        $this->landingWindowsUrl = (string) ($branding->landing_windows_url ?? '');
        $this->landingWindowsEnabled = (bool) $branding->landing_windows_enabled;

        // Hero fields
        $this->landingHeroBadge = (string) ($branding->landing_hero_badge ?? '');
        $this->landingHeroTitle = (string) ($branding->landing_hero_title ?? '');
        $this->landingHeroSubtitle = (string) ($branding->landing_hero_subtitle ?? '');
        $this->landingHeroCtaPrimaryText = (string) ($branding->landing_hero_cta_primary_text ?? '');
        $this->landingHeroCtaPrimaryUrl = (string) ($branding->landing_hero_cta_primary_url ?? '');
        $this->landingHeroCtaSecondaryText = (string) ($branding->landing_hero_cta_secondary_text ?? '');
        $this->landingHeroCtaSecondaryUrl = (string) ($branding->landing_hero_cta_secondary_url ?? '');
        $this->landingHeroBannerImageUrl = (string) ($branding->landing_hero_banner_image_url ?? '');

        $this->landingHeroDashboardTitle = $branding->landingText('hero.dashboard_title', 'Smart POS & Inventory');
        $this->landingHeroDashboardStatus = $branding->landingText('hero.dashboard_status', 'Live');
        $this->landingHeroTotalLabel = $branding->landingText('hero.total_label', 'Total');
        $this->landingHeroPaymentLabel = $branding->landingText('hero.payment_label', 'Split Cash / Card');
        $this->landingHeroTotalAmount = $branding->landingText('hero.total_amount', '$41.60');

        $this->landingHeroHighlights = $branding->landingList('hero.highlights', [
            'Barcode & Touch POS',
            'Live Stock Alerts',
            'Restaurant Floor KOT',
            'Offline First Sync',
        ]);

        $this->landingHeroProducts = $branding->landingList('hero.products', [
            ['name' => '☕ Artisan Coffee Roast 1kg', 'price' => '$14.50', 'status' => 'In Stock', 'tone' => 'emerald'],
            ['name' => '🫒 Gourmet Truffle Oil 500ml', 'price' => '$18.20', 'status' => 'In Stock', 'tone' => 'emerald'],
            ['name' => '🌾 Organic Almond Flour 1kg', 'price' => '$8.90', 'status' => 'Low Stock', 'tone' => 'amber'],
        ]);

        // Normalize hero products to associative arrays if indexed
        $this->landingHeroProducts = array_map(function ($item) {
            if (is_array($item)) {
                return [
                    'name' => $item['name'] ?? ($item[0] ?? ''),
                    'price' => $item['price'] ?? ($item[1] ?? ''),
                    'status' => $item['status'] ?? ($item[2] ?? 'In Stock'),
                    'tone' => $item['tone'] ?? ($item[3] ?? 'emerald'),
                ];
            }
            return ['name' => (string) $item, 'price' => '', 'status' => 'In Stock', 'tone' => 'emerald'];
        }, $this->landingHeroProducts);

        // Section Meta for all sections
        $meta = $branding->landing_section_meta ?? [];
        $sections = ['hero', 'trust_bar', 'features', 'solutions', 'downloads', 'stats', 'about', 'testimonials', 'pricing', 'faq', 'contact', 'cta'];
        foreach ($sections as $sec) {
            $this->sectionMeta[$sec] = [
                'title' => (string) ($meta[$sec]['title'] ?? ''),
                'subtitle' => (string) ($meta[$sec]['subtitle'] ?? ''),
                'badge' => (string) ($meta[$sec]['badge'] ?? data_get($branding->landing_content ?? [], "{$sec}.badge", '')),
                'body' => (string) ($meta[$sec]['body'] ?? data_get($branding->landing_content ?? [], "{$sec}.body", '')),
                'background' => (string) ($meta[$sec]['background'] ?? ''),
                'accent' => (string) ($meta[$sec]['accent'] ?? ''),
            ];
        }

        // Hardware cards
        $this->landingHardwareItems = $branding->landingHardware();

        // Features
        $this->landingFeatures = $branding->landingFeatures();

        // Solutions
        $this->landingSolutions = $branding->landingSolutionsList();

        // Stats
        $this->landingStats = $branding->landingStatsList();

        // Testimonials
        $this->landingTestimonials = $branding->landingTestimonials();

        // FAQs
        $this->landingFaqs = $branding->landingFaqs();

        // Pricing & CTA copy
        $this->pricingDiscountBadge = $branding->landingText('pricing.discount_badge', 'Save 20%');
        $this->pricingNote = $branding->landingText('pricing.note', '');
        $this->ctaPrimaryText = $branding->landingText('cta.primary_text', '');
        $this->ctaPrimaryUrl = $branding->landingText('cta.primary_url', '');
        $this->ctaSecondaryText = $branding->landingText('cta.secondary_text', '');
        $this->ctaSecondaryUrl = $branding->landingText('cta.secondary_url', '');

        // HTML & JSON overrides
        $this->landingCustomHtml = (string) data_get($branding->landing_content ?? [], 'html', '');
        $this->landingContentJson = json_encode($branding->landing_content ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '';
    }

    public function updatedLandingPageId($value): void
    {
        if ($value) {
            $this->homepageMode = 'static_page';
        }
    }

    public function setHomepageMode(string $mode): void
    {
        if (in_array($mode, ['modular', 'static_page'], true)) {
            $this->homepageMode = $mode;
        }
    }

    public function setMenuLocation(string $loc): void
    {
        if (in_array($loc, ['header', 'footer_col_1', 'footer_col_2'], true)) {
            $this->menuLocation = $loc;
            $this->cancelEditMenuItem();
        }
    }

    public function addAnchorMenuLink(string $title, string $anchor): void
    {
        $anchor = trim($anchor);
        if (! str_starts_with($anchor, '#')) {
            $anchor = '#' . ltrim($anchor, '/#');
        }

        $maxOrder = (int) MenuItem::where('location', $this->menuLocation)->max('order_index');

        MenuItem::create([
            'location' => $this->menuLocation,
            'title' => trim($title),
            'type' => 'anchor',
            'url' => $anchor,
            'page_id' => null,
            'target' => '_self',
            'order_index' => $maxOrder + 1,
            'is_active' => true,
        ]);

        MenuItem::clearMenuCache($this->menuLocation);
        $this->dispatch('notify', message: __('Added section anchor link to menu.'));
    }

    public function addPageMenuLink(?int $pageId = null): void
    {
        $pId = $pageId ?: $this->newMenuPageId;
        if (! $pId) {
            return;
        }

        $page = Page::find($pId);
        if (! $page) {
            return;
        }

        $maxOrder = (int) MenuItem::where('location', $this->menuLocation)->max('order_index');

        MenuItem::create([
            'location' => $this->menuLocation,
            'title' => $page->title,
            'type' => 'page',
            'url' => '/page/' . $page->slug,
            'page_id' => $page->id,
            'target' => '_self',
            'order_index' => $maxOrder + 1,
            'is_active' => true,
        ]);

        $this->newMenuPageId = null;
        MenuItem::clearMenuCache($this->menuLocation);
        $this->dispatch('notify', message: __('Added CMS page link to menu.'));
    }

    public function addCustomMenuLink(): void
    {
        $this->validate([
            'newMenuTitle' => 'required|string|max:100',
            'newMenuUrl' => 'required|string|max:255',
        ]);

        $maxOrder = (int) MenuItem::where('location', $this->menuLocation)->max('order_index');

        MenuItem::create([
            'location' => $this->menuLocation,
            'title' => trim($this->newMenuTitle),
            'type' => 'custom',
            'url' => trim($this->newMenuUrl),
            'page_id' => null,
            'target' => $this->newMenuTargetBlank ? '_blank' : '_self',
            'order_index' => $maxOrder + 1,
            'is_active' => true,
        ]);

        $this->newMenuTitle = '';
        $this->newMenuUrl = '';
        $this->newMenuTargetBlank = false;
        MenuItem::clearMenuCache($this->menuLocation);
        $this->dispatch('notify', message: __('Added custom menu item.'));
    }

    public function moveMenuItemUp(int $id): void
    {
        $items = MenuItem::where('location', $this->menuLocation)->orderBy('order_index')->get();
        $index = $items->search(fn ($i) => $i->id === $id);
        if ($index === false || $index <= 0) {
            return;
        }

        $current = $items[$index];
        $prev = $items[$index - 1];

        $prevOrder = $prev->order_index;
        $currOrder = $current->order_index;

        if ($prevOrder >= $currOrder) {
            $prevOrder = $index - 1;
            $currOrder = $index;
        }

        $current->update(['order_index' => $prevOrder]);
        $prev->update(['order_index' => $currOrder]);

        MenuItem::clearMenuCache($this->menuLocation);
    }

    public function moveMenuItemDown(int $id): void
    {
        $items = MenuItem::where('location', $this->menuLocation)->orderBy('order_index')->get();
        $index = $items->search(fn ($i) => $i->id === $id);
        if ($index === false || $index >= count($items) - 1) {
            return;
        }

        $current = $items[$index];
        $next = $items[$index + 1];

        $nextOrder = $next->order_index;
        $currOrder = $current->order_index;

        if ($nextOrder <= $currOrder) {
            $nextOrder = $index + 1;
            $currOrder = $index;
        }

        $current->update(['order_index' => $nextOrder]);
        $next->update(['order_index' => $currOrder]);

        MenuItem::clearMenuCache($this->menuLocation);
    }

    public function toggleMenuItemActive(int $id): void
    {
        $item = MenuItem::findOrFail($id);
        $item->update(['is_active' => ! $item->is_active]);
        MenuItem::clearMenuCache($this->menuLocation);
    }

    public function deleteMenuItem(int $id): void
    {
        $item = MenuItem::findOrFail($id);
        $location = $item->location;
        $item->delete();
        MenuItem::clearMenuCache($location);
        if ($this->editingMenuItemId === $id) {
            $this->cancelEditMenuItem();
        }
        $this->dispatch('notify', message: __('Menu item deleted.'));
    }

    public function editMenuItem(int $id): void
    {
        $item = MenuItem::findOrFail($id);
        $this->editingMenuItemId = $item->id;
        $this->editingMenuItemTitle = $item->title;
        $this->editingMenuItemUrl = $item->url;
        $this->editingMenuItemTarget = $item->target ?: '_self';
        $this->editingMenuItemActive = (bool) $item->is_active;
    }

    public function saveMenuItem(): void
    {
        if (! $this->editingMenuItemId) {
            return;
        }

        $this->validate([
            'editingMenuItemTitle' => 'required|string|max:100',
            'editingMenuItemUrl' => 'required|string|max:255',
        ]);

        $item = MenuItem::findOrFail($this->editingMenuItemId);
        $item->update([
            'title' => trim($this->editingMenuItemTitle),
            'url' => trim($this->editingMenuItemUrl),
            'target' => $this->editingMenuItemTarget,
            'is_active' => $this->editingMenuItemActive,
        ]);

        $this->cancelEditMenuItem();
        MenuItem::clearMenuCache($item->location);
        $this->dispatch('notify', message: __('Menu item updated.'));
    }

    public function cancelEditMenuItem(): void
    {
        $this->editingMenuItemId = null;
        $this->editingMenuItemTitle = '';
        $this->editingMenuItemUrl = '';
        $this->editingMenuItemTarget = '_self';
        $this->editingMenuItemActive = true;
    }

    // Section Ordering Methods
    public function moveSectionUp(int $index): void
    {
        if ($index <= 0 || ! isset($this->landingSectionOrder[$index])) {
            return;
        }
        $prev = $index - 1;
        $temp = $this->landingSectionOrder[$prev];
        $this->landingSectionOrder[$prev] = $this->landingSectionOrder[$index];
        $this->landingSectionOrder[$index] = $temp;
        $this->landingSectionOrder = array_values($this->landingSectionOrder);
    }

    public function moveSectionDown(int $index): void
    {
        if ($index >= count($this->landingSectionOrder) - 1 || ! isset($this->landingSectionOrder[$index])) {
            return;
        }
        $next = $index + 1;
        $temp = $this->landingSectionOrder[$next];
        $this->landingSectionOrder[$next] = $this->landingSectionOrder[$index];
        $this->landingSectionOrder[$index] = $temp;
        $this->landingSectionOrder = array_values($this->landingSectionOrder);
    }

    public function resetSectionOrder(): void
    {
        $this->landingSectionOrder = [
            'hero', 'trust_bar', 'features', 'solutions', 'downloads', 'stats',
            'about', 'testimonials', 'pricing', 'faq', 'contact', 'cta',
        ];
    }

    // Repeaters for dynamic items
    public function addHeroHighlight(): void
    {
        if (count($this->landingHeroHighlights) < 20) {
            $this->landingHeroHighlights[] = '';
        }
    }

    public function removeHeroHighlight(int $index): void
    {
        unset($this->landingHeroHighlights[$index]);
        $this->landingHeroHighlights = array_values($this->landingHeroHighlights);
    }

    public function addHighlight(): void { $this->addHeroHighlight(); }
    public function removeHighlight(int $index): void { $this->removeHeroHighlight($index); }

    public function addHeroProduct(): void
    {
        if (count($this->landingHeroProducts) < 15) {
            $this->landingHeroProducts[] = ['name' => '', 'price' => '', 'status' => 'In Stock', 'tone' => 'emerald'];
        }
    }

    public function removeHeroProduct(int $index): void
    {
        unset($this->landingHeroProducts[$index]);
        $this->landingHeroProducts = array_values($this->landingHeroProducts);
    }

    public function addProduct(): void { $this->addHeroProduct(); }
    public function removeProduct(int $index): void { $this->removeHeroProduct($index); }

    public function addHardwareItem(): void
    {
        if (count($this->landingHardwareItems) < 15) {
            $this->landingHardwareItems[] = ['label' => '', 'tag' => '', 'icon' => 'barcode'];
        }
    }

    public function removeHardwareItem(int $index): void
    {
        unset($this->landingHardwareItems[$index]);
        $this->landingHardwareItems = array_values($this->landingHardwareItems);
    }

    public function addHardware(): void { $this->addHardwareItem(); }
    public function removeHardware(int $index): void { $this->removeHardwareItem($index); }

    public function addFeature(): void
    {
        if (count($this->landingFeatures) < 30) {
            $this->landingFeatures[] = ['icon' => '✨', 'title' => '', 'body' => ''];
        }
    }

    public function removeFeature(int $index): void
    {
        unset($this->landingFeatures[$index]);
        $this->landingFeatures = array_values($this->landingFeatures);
    }

    public function addSolution(): void
    {
        if (count($this->landingSolutions) < 20) {
            $this->landingSolutions[] = ['icon' => '⚡', 'title' => '', 'body' => ''];
        }
    }

    public function removeSolution(int $index): void
    {
        unset($this->landingSolutions[$index]);
        $this->landingSolutions = array_values($this->landingSolutions);
    }

    public function addStat(): void
    {
        if (count($this->landingStats) < 12) {
            $this->landingStats[] = ['value' => '', 'label' => ''];
        }
    }

    public function removeStat(int $index): void
    {
        unset($this->landingStats[$index]);
        $this->landingStats = array_values($this->landingStats);
    }

    public function addTestimonial(): void
    {
        if (count($this->landingTestimonials) < 20) {
            $this->landingTestimonials[] = ['quote' => '', 'name' => '', 'role' => ''];
        }
    }

    public function removeTestimonial(int $index): void
    {
        unset($this->landingTestimonials[$index]);
        $this->landingTestimonials = array_values($this->landingTestimonials);
    }

    public function addFaq(): void
    {
        if (count($this->landingFaqs) < 30) {
            $this->landingFaqs[] = ['q' => '', 'a' => ''];
        }
    }

    public function removeFaq(int $index): void
    {
        unset($this->landingFaqs[$index]);
        $this->landingFaqs = array_values($this->landingFaqs);
    }

    public function addLandingItem(string $type): void
    {
        if ($type === 'highlight') $this->addHeroHighlight();
        elseif ($type === 'product') $this->addHeroProduct();
        elseif ($type === 'hardware') $this->addHardwareItem();
        elseif ($type === 'stat') $this->addStat();
        elseif ($type === 'solution') $this->addSolution();
        elseif ($type === 'feature') $this->addFeature();
        elseif ($type === 'testimonial') $this->addTestimonial();
        elseif ($type === 'faq') $this->addFaq();
    }

    public function removeLandingItem(string $type, int $index): void
    {
        if ($type === 'highlight') $this->removeHeroHighlight($index);
        elseif ($type === 'product') $this->removeHeroProduct($index);
        elseif ($type === 'hardware') $this->removeHardwareItem($index);
        elseif ($type === 'stat') $this->removeStat($index);
        elseif ($type === 'solution') $this->removeSolution($index);
        elseif ($type === 'feature') $this->removeFeature($index);
        elseif ($type === 'testimonial') $this->removeTestimonial($index);
        elseif ($type === 'faq') $this->removeFaq($index);
    }

    public function removeLogo(): void
    {
        $this->logoImage = null;
        $this->logoUrl = '';
        DynamicSetting::put('platform_logo_url', '');
        session()->flash('status', 'Platform logo removed.');
    }

    public function removeAuthBanner(): void
    {
        $this->authBannerImage = null;
        $this->authBannerImageUrl = '';
        DynamicSetting::put('auth_banner_image_url', '');
        session()->flash('status', 'Auth banner image removed.');
    }

    public function removeHeroBanner(): void
    {
        $this->landingHeroBannerImage = null;
        $this->landingHeroBannerImageUrl = '';
        session()->flash('status', 'Hero banner image removed. Default interactive dashboard will be used.');
    }

    protected function rules(): array
    {
        return [
            'platformName' => ['required', 'string', 'max:255'],
            'logoUrl' => ['nullable', 'string', 'max:500'],
            'logoImage' => ['nullable', 'image', 'max:5120'],
            'faviconUrl' => ['nullable', 'string', 'max:500'],
            'showAuthBanner' => ['boolean'],
            'authBannerImageUrl' => ['nullable', 'string', 'max:500'],
            'authBannerImage' => ['nullable', 'image', 'max:5120'],
            'enableRegistrationDomainSetup' => ['boolean'],
            'primaryColor' => ['nullable', 'string', 'max:32'],
            'superadminSidebarColor' => ['nullable', 'string', 'max:32'],
            'landingPrimaryColor' => ['nullable', 'string', 'max:32'],
            'landingAccentColor' => ['nullable', 'string', 'max:32'],
            'supportEmail' => ['nullable', 'email'],
            'supportPhone' => ['nullable', 'string', 'max:50'],
            'landingPageId' => ['nullable', 'exists:pages,id'],
            'landingTheme' => ['required', 'string', 'in:theme_fast,theme_modern,theme_minimal,theme_enterprise,theme_dark_studio'],
            'landingHeroBadge' => ['nullable', 'string', 'max:255'],
            'landingHeroTitle' => ['nullable', 'string', 'max:255'],
            'landingHeroSubtitle' => ['nullable', 'string', 'max:1000'],
            'landingHeroCtaPrimaryText' => ['nullable', 'string', 'max:100'],
            'landingHeroCtaPrimaryUrl' => ['nullable', 'string', 'max:500'],
            'landingHeroCtaSecondaryText' => ['nullable', 'string', 'max:100'],
            'landingHeroCtaSecondaryUrl' => ['nullable', 'string', 'max:500'],
            'landingHeroBannerImageUrl' => ['nullable', 'string', 'max:500'],
            'landingHeroBannerImage' => ['nullable', 'image', 'max:5120'],
            'landingPlaystoreUrl' => ['nullable', 'url', 'max:500'],
            'landingPlaystoreEnabled' => ['boolean'],
            'landingWindowsUrl' => ['nullable', 'url', 'max:500'],
            'landingWindowsEnabled' => ['boolean'],
            'sectionMeta.*.title' => ['nullable', 'string', 'max:255'],
            'sectionMeta.*.subtitle' => ['nullable', 'string', 'max:500'],
            'sectionMeta.*.badge' => ['nullable', 'string', 'max:255'],
            'sectionMeta.*.body' => ['nullable', 'string', 'max:5000'],
            'sectionMeta.*.background' => ['nullable', 'string', 'max:32'],
            'sectionMeta.*.accent' => ['nullable', 'string', 'max:32'],
            'landingFaqs' => ['array', 'max:50'],
            'landingFaqs.*.q' => ['nullable', 'string', 'max:255'],
            'landingFaqs.*.a' => ['nullable', 'string', 'max:2000'],
            'landingFeatures' => ['nullable', 'array', 'max:50'],
            'landingFeatures.*.icon' => ['nullable', 'string', 'max:32'],
            'landingFeatures.*.title' => ['nullable', 'string', 'max:160'],
            'landingFeatures.*.body' => ['nullable', 'string', 'max:1500'],
            'landingTestimonials' => ['nullable', 'array', 'max:30'],
            'landingTestimonials.*.quote' => ['nullable', 'string', 'max:1000'],
            'landingTestimonials.*.name' => ['nullable', 'string', 'max:120'],
            'landingTestimonials.*.role' => ['nullable', 'string', 'max:160'],
            'landingHardwareItems' => ['nullable', 'array', 'max:20'],
            'landingHardwareItems.*.label' => ['nullable', 'string', 'max:120'],
            'landingHardwareItems.*.tag' => ['nullable', 'string', 'max:120'],
            'landingHardwareItems.*.icon' => ['nullable', 'string', 'max:32'],
            'landingSolutions' => ['nullable', 'array', 'max:20'],
            'landingSolutions.*.icon' => ['nullable', 'string', 'max:32'],
            'landingSolutions.*.title' => ['nullable', 'string', 'max:160'],
            'landingSolutions.*.body' => ['nullable', 'string', 'max:1000'],
            'landingStats' => ['nullable', 'array', 'max:16'],
            'landingStats.*.value' => ['nullable', 'string', 'max:60'],
            'landingStats.*.label' => ['nullable', 'string', 'max:120'],
            'landingHeroHighlights' => ['nullable', 'array', 'max:20'],
            'landingHeroHighlights.*' => ['nullable', 'string', 'max:140'],
            'landingCustomHtml' => ['nullable', 'string', 'max:500000'],
            'landingContentJson' => ['nullable', 'string', 'max:100000'],
        ];
    }

    public function save(): void
    {
        $data = $this->validate();

        if ($this->logoImage) {
            $logoPath = $this->logoImage->store('branding', 'public');
            $data['logoUrl'] = Storage::disk('public')->url($logoPath);
            $this->logoUrl = $data['logoUrl'];
            $this->logoImage = null;
        }

        if ($this->authBannerImage) {
            $bannerPath = $this->authBannerImage->store('branding', 'public');
            $this->authBannerImageUrl = Storage::disk('public')->url($bannerPath);
            $this->authBannerImage = null;
        }

        if ($this->landingHeroBannerImage) {
            $heroPath = $this->landingHeroBannerImage->store('branding', 'public');
            $this->landingHeroBannerImageUrl = Storage::disk('public')->url($heroPath);
            $this->landingHeroBannerImage = null;
        }

        DynamicSetting::put('show_auth_banner', $this->showAuthBanner);
        DynamicSetting::put('auth_banner_image_url', $this->authBannerImageUrl);
        DynamicSetting::put('enable_registration_domain_setup', $this->enableRegistrationDomainSetup);
        if (! empty($data['logoUrl'])) {
            DynamicSetting::put('platform_logo_url', $data['logoUrl']);
        }
        if (! empty($data['platformName'])) {
            DynamicSetting::put('platform_brand_name', $data['platformName']);
        }

        // Save theme setting
        set_setting('landing_page_theme', $this->landingTheme);
        cache()->forget('app_landing_page_theme');

        $sectionsConfig = [
            'hero' => $this->sectionHero,
            'trust_bar' => $this->sectionTrustBar,
            'features' => $this->sectionFeatures,
            'solutions' => $this->sectionSolutions,
            'downloads' => $this->sectionDownloads,
            'stats' => $this->sectionStats,
            'about' => $this->sectionAbout,
            'testimonials' => $this->sectionTestimonials,
            'pricing' => $this->sectionPricing,
            'faq' => $this->sectionFaq,
            'contact' => $this->sectionContact,
            'cta' => $this->sectionCta,
        ];

        // Section order validation
        $allowedOrder = ['hero', 'trust_bar', 'features', 'solutions', 'downloads', 'stats', 'about', 'testimonials', 'pricing', 'faq', 'contact', 'cta'];
        $order = array_values(array_unique(array_filter($this->landingSectionOrder, fn ($slug) => in_array($slug, $allowedOrder, true))));
        $sectionsConfig['order'] = array_values(array_unique(array_merge($order, array_diff($allowedOrder, $order))));

        // Sanitize section meta
        $sectionMeta = [];
        foreach ($this->sectionMeta as $key => $metaRow) {
            $title = trim((string) ($metaRow['title'] ?? ''));
            $subtitle = trim((string) ($metaRow['subtitle'] ?? ''));
            $badge = trim((string) ($metaRow['badge'] ?? ''));
            $body = trim((string) ($metaRow['body'] ?? ''));
            $bg = trim((string) ($metaRow['background'] ?? ''));
            $accent = trim((string) ($metaRow['accent'] ?? ''));
            if ($title !== '' || $subtitle !== '' || $badge !== '' || $body !== '' || $bg !== '' || $accent !== '') {
                $sectionMeta[$key] = [
                    'title' => $title,
                    'subtitle' => $subtitle,
                    'badge' => $badge,
                    'body' => $body,
                    'background' => $bg ?: null,
                    'accent' => $accent ?: null,
                ];
            }
        }

        // FAQs
        $faqs = collect($this->landingFaqs)
            ->map(fn ($row) => ['q' => trim((string) ($row['q'] ?? '')), 'a' => trim((string) ($row['a'] ?? ''))])
            ->filter(fn ($row) => $row['q'] !== '' && $row['a'] !== '')
            ->values()
            ->all();

        // Features
        $features = collect($this->landingFeatures)
            ->map(fn ($row) => [
                'icon' => trim((string) ($row['icon'] ?? '✨')) ?: '✨',
                'title' => trim((string) ($row['title'] ?? '')),
                'body' => trim((string) ($row['body'] ?? '')),
                'mockup' => trim((string) ($row['mockup'] ?? '')) ?: null,
            ])
            ->filter(fn ($row) => $row['title'] !== '' && $row['body'] !== '')
            ->values()
            ->all();

        // Testimonials
        $testimonials = collect($this->landingTestimonials)
            ->map(fn ($row) => [
                'quote' => trim((string) ($row['quote'] ?? '')),
                'name' => trim((string) ($row['name'] ?? '')),
                'role' => trim((string) ($row['role'] ?? '')),
            ])
            ->filter(fn ($row) => $row['quote'] !== '' && $row['name'] !== '')
            ->values()
            ->all();

        // Build landing content
        $landingContent = [];
        if (filled($this->landingContentJson)) {
            $decoded = json_decode($this->landingContentJson, true);
            if (is_array($decoded)) {
                $landingContent = $decoded;
            }
        }

        // Highlights
        $highlights = array_values(array_filter(array_map('trim', $this->landingHeroHighlights)));
        if (! empty($highlights)) {
            $landingContent['hero']['highlights'] = $highlights;
        }

        // Hero products
        $products = collect($this->landingHeroProducts)
            ->map(fn ($p) => [
                'name' => trim((string) ($p['name'] ?? '')),
                'price' => trim((string) ($p['price'] ?? '')),
                'status' => trim((string) ($p['status'] ?? 'In Stock')),
                'tone' => trim((string) ($p['tone'] ?? 'emerald')),
            ])
            ->filter(fn ($p) => $p['name'] !== '')
            ->values()
            ->all();
        if (! empty($products)) {
            $landingContent['hero']['products'] = $products;
        }

        // Hero mockup copy
        if (filled($this->landingHeroDashboardTitle)) {
            $landingContent['hero']['dashboard_title'] = $this->landingHeroDashboardTitle;
        }
        if (filled($this->landingHeroDashboardStatus)) {
            $landingContent['hero']['dashboard_status'] = $this->landingHeroDashboardStatus;
        }
        if (filled($this->landingHeroTotalLabel)) {
            $landingContent['hero']['total_label'] = $this->landingHeroTotalLabel;
        }
        if (filled($this->landingHeroPaymentLabel)) {
            $landingContent['hero']['payment_label'] = $this->landingHeroPaymentLabel;
        }
        if (filled($this->landingHeroTotalAmount)) {
            $landingContent['hero']['total_amount'] = $this->landingHeroTotalAmount;
        }

        // Hardware cards
        $hardware = collect($this->landingHardwareItems)
            ->map(fn ($h) => [
                'label' => trim((string) ($h['label'] ?? '')),
                'tag' => trim((string) ($h['tag'] ?? '')),
                'icon' => trim((string) ($h['icon'] ?? 'barcode')),
            ])
            ->filter(fn ($h) => $h['label'] !== '')
            ->values()
            ->all();
        if (! empty($hardware)) {
            $landingContent['trust']['hardware'] = $hardware;
        }

        // Solutions
        $solutions = collect($this->landingSolutions)
            ->map(fn ($s) => [
                'icon' => trim((string) ($s['icon'] ?? '⚡')) ?: '⚡',
                'title' => trim((string) ($s['title'] ?? '')),
                'body' => trim((string) ($s['body'] ?? '')),
            ])
            ->filter(fn ($s) => $s['title'] !== '')
            ->values()
            ->all();
        if (! empty($solutions)) {
            $landingContent['solutions']['items'] = $solutions;
        }

        // Stats
        $stats = collect($this->landingStats)
            ->map(fn ($st) => [
                'value' => trim((string) ($st['value'] ?? '')),
                'label' => trim((string) ($st['label'] ?? '')),
            ])
            ->filter(fn ($st) => $st['value'] !== '' || $st['label'] !== '')
            ->values()
            ->all();
        if (! empty($stats)) {
            $landingContent['stats'] = $stats;
        }

        // Pricing & CTA overrides
        if (filled($this->pricingDiscountBadge)) {
            $landingContent['pricing']['discount_badge'] = $this->pricingDiscountBadge;
        }
        if (filled($this->pricingNote)) {
            $landingContent['pricing']['note'] = $this->pricingNote;
        }
        if (filled($this->ctaPrimaryText)) {
            $landingContent['cta']['primary_text'] = $this->ctaPrimaryText;
        }
        if (filled($this->ctaPrimaryUrl)) {
            $landingContent['cta']['primary_url'] = $this->ctaPrimaryUrl;
        }
        if (filled($this->ctaSecondaryText)) {
            $landingContent['cta']['secondary_text'] = $this->ctaSecondaryText;
        }
        if (filled($this->ctaSecondaryUrl)) {
            $landingContent['cta']['secondary_url'] = $this->ctaSecondaryUrl;
        }

        // Homepage display mode
        if ($this->homepageMode === 'static_page' && empty($this->landingPageId)) {
            $this->homepageMode = 'modular';
        }
        $landingContent['homepage_mode'] = $this->homepageMode;

        // Custom HTML override
        if (filled($this->landingCustomHtml)) {
            $landingContent['html'] = $this->landingCustomHtml;
        }

        PlatformBranding::current()->update([
            'platform_name' => $data['platformName'],
            'logo_url' => $data['logoUrl'] ?: null,
            'favicon_url' => $data['faviconUrl'] ?: null,
            'primary_color' => $data['primaryColor'] ?: '#4f46e5',
            'superadmin_sidebar_color' => $data['superadminSidebarColor'] ?: '#4338ca',
            'landing_primary_color' => $data['landingPrimaryColor'] ?: '#10b981',
            'landing_accent_color' => $data['landingAccentColor'] ?: '#d7f24e',
            'support_email' => $data['supportEmail'] ?: null,
            'support_phone' => $data['supportPhone'] ?: null,
            'otp_registration_enabled' => $this->otpRegistrationEnabled,
            'landing_page_enabled' => $this->landingPageEnabled,
            'landing_page_id' => $data['landingPageId'] ?: null,
            'landing_hero_badge' => $data['landingHeroBadge'] ?: null,
            'landing_hero_title' => $data['landingHeroTitle'] ?: null,
            'landing_hero_subtitle' => $data['landingHeroSubtitle'] ?: null,
            'landing_hero_cta_primary_text' => $data['landingHeroCtaPrimaryText'] ?: null,
            'landing_hero_cta_primary_url' => $data['landingHeroCtaPrimaryUrl'] ?: null,
            'landing_hero_cta_secondary_text' => $data['landingHeroCtaSecondaryText'] ?: null,
            'landing_hero_cta_secondary_url' => $data['landingHeroCtaSecondaryUrl'] ?: null,
            'landing_hero_banner_image_url' => $this->landingHeroBannerImageUrl ?: null,
            'landing_playstore_url' => $data['landingPlaystoreUrl'] ?: null,
            'landing_playstore_enabled' => $this->landingPlaystoreEnabled,
            'landing_windows_url' => $data['landingWindowsUrl'] ?: null,
            'landing_windows_enabled' => $this->landingWindowsEnabled,
            'landing_sections_config' => $sectionsConfig,
            'landing_section_meta' => $sectionMeta,
            'landing_faqs' => $faqs,
            'landing_features' => $features,
            'landing_testimonials' => $testimonials,
            'landing_content' => $landingContent,
        ]);

        MenuItem::clearMenuCache();
        Cache::forget('public_settings');
        Cache::forget('platform_branding_settings');
        if (Cache::has('landing_page_cache_version')) {
            Cache::increment('landing_page_cache_version');
        } else {
            Cache::forever('landing_page_cache_version', 2);
        }

        AuditLog::record('branding.updated', null, auth('platform_web')->id());
        session()->flash('status', 'Landing page & branding configurations saved successfully.');
    }

    public function render()
    {
        return view('livewire.superadmin.branding.index', [
            'availablePages' => Page::orderBy('title')->get(),
            'currentMenuItems' => MenuItem::where('location', $this->menuLocation)->orderBy('order_index')->get(),
            'selectedLandingPage' => $this->landingPageId ? Page::find($this->landingPageId) : null,
        ]);
    }
}
