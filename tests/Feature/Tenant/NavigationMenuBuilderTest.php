<?php

namespace Tests\Feature\Tenant;

use App\Livewire\Tenant\Settings\Index as SettingsIndex;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

class NavigationMenuBuilderTest extends TestCase
{
    use ActsAsTenantUser, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        file_put_contents(storage_path('installed'), '{}');
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed'));
        parent::tearDown();
    }

    public function test_navigation_tab_initializes_from_the_compiled_in_retail_tree(): void
    {
        [$company] = $this->actingAsTenantAdmin();
        $this->assertFalse($company->isRestaurantMode());

        Livewire::test(SettingsIndex::class)
            ->assertViewHas('navSections', function (array $sections) {
                $cashierSales = collect($sections)->firstWhere('key', 'cashier_sales');

                return $cashierSales
                    && $cashierSales['label'] === 'Cashier & Sales'
                    && collect($cashierSales['items'])->pluck('key')->all() === ['pos', 'sales', 'quotations', 'customers']
                    && collect($cashierSales['items'])->every(fn ($i) => $i['visible'] === true);
            });
    }

    public function test_save_nav_config_persists_hidden_items_reordered_sections_and_moved_items(): void
    {
        [$company] = $this->actingAsTenantAdmin();

        $payload = [
            // Financial Management dragged above Cashier & Sales.
            [
                'key' => 'financial_management',
                'label' => 'Financial Management',
                'items' => [
                    ['key' => 'cash_register', 'label' => 'Cash Register', 'visible' => true],
                    ['key' => 'due_receivables', 'label' => 'Accounts Receivable', 'visible' => true],
                    ['key' => 'payables', 'label' => 'Accounts Payable', 'visible' => true],
                    ['key' => 'reports', 'label' => 'Reports & Analytics', 'visible' => true],
                    // "pos" dragged in from Cashier & Sales.
                    ['key' => 'pos', 'label' => 'Cashier POS Terminal', 'visible' => true],
                ],
            ],
            [
                'key' => 'cashier_sales',
                'label' => 'Cashier & Sales',
                'items' => [
                    ['key' => 'sales', 'label' => 'Sales & Invoices', 'visible' => true],
                    // Hidden via the checkbox.
                    ['key' => 'quotations', 'label' => 'Quotations & Proposals', 'visible' => false],
                    ['key' => 'customers', 'label' => 'Customers & CRM', 'visible' => true],
                ],
            ],
        ];

        Livewire::test(SettingsIndex::class)->call('saveNavConfig', $payload)->assertHasNoErrors();

        $nav = $company->fresh()->normalizedNavConfig();

        $this->assertSame(
            [['key' => 'financial_management', 'order' => 0], ['key' => 'cashier_sales', 'order' => 1]],
            $nav['sections']
        );

        $posItem = collect($nav['items'])->firstWhere('key', 'pos');
        $this->assertSame('financial_management', $posItem['section']);
        $this->assertSame(4, $posItem['order']);

        $quotationsItem = collect($nav['items'])->firstWhere('key', 'quotations');
        $this->assertFalse($quotationsItem['visible']);
    }

    public function test_save_nav_config_requires_settings_permission(): void
    {
        [$company, $staff] = $this->actingAsTenantStaff();
        // A staff role without the 'settings' permission by default.
        $this->assertFalse(\App\Services\Auth\PermissionChecker::can($staff, 'settings'));

        $response = Livewire::test(SettingsIndex::class)->call('saveNavConfig', [
            ['key' => 'cashier_sales', 'label' => 'Cashier & Sales', 'items' => []],
        ]);

        $response->assertStatus(403);
    }
}
