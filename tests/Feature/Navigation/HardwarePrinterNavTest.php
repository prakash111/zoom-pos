<?php

namespace Tests\Feature\Navigation;

use App\Models\Company;
use App\Models\TenantApiKey;
use App\Models\User;
use App\Services\Navigation\TenantNavRegistry;
use App\Services\Sdui\SchemaResponse;
use App\Services\Sdui\SchemaValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HardwarePrinterNavTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<array{0:string}> */
    public static function operatingModes(): array
    {
        return [['retail'], ['restaurant'], ['pharmacy'], ['repair'], ['service_booking']];
    }

    #[DataProvider('operatingModes')]
    public function test_printer_setup_row_is_present_in_administration_for_every_mode(string $mode): void
    {
        $company = Company::create([
            'name' => 'Mode Co', 'slug' => 'mode-'.$mode, 'status' => 'active',
            'pos_mode' => $mode, 'currency' => 'USD', 'currency_symbol' => '$',
        ]);

        $sections = TenantNavRegistry::getEffectiveNavForTenant($company);
        $admin = collect($sections)->firstWhere('key', 'administration');
        $this->assertNotNull($admin, "administration section missing for {$mode}");

        $items = collect($admin['items']);
        $printer = $items->firstWhere('key', 'hardware_printer');
        $this->assertNotNull($printer, "Printer & Hardware Setup missing for {$mode}");
        $this->assertSame('Printer & Hardware Setup', $printer['title']);
        $this->assertSame('print', $printer['icon']);
        // Resolves to the native GlobalPrinterSetupScreen, not a schema page.
        $this->assertSame('printer_setup', $printer['component']);

        // Change Password stays pinned as the very last administration row.
        $this->assertSame('change_password', $items->pluck('key')->last());
    }

    public function test_printer_setup_row_is_reconciled_into_a_saved_custom_nav_tree(): void
    {
        // A tenant who customised their drawer before this row shipped keeps a
        // saved nav_config that never mentions it — it must still be injected.
        $company = Company::create([
            'name' => 'Custom Nav Co', 'slug' => 'custom-nav-co', 'status' => 'active',
            'pos_mode' => 'retail', 'currency' => 'USD', 'currency_symbol' => '$',
        ]);
        $company->update(['nav_config' => [
            'sections' => [
                ['key' => 'cashier_sales', 'order' => 0],
                ['key' => 'administration', 'order' => 1],
            ],
            'items' => [
                ['key' => 'pos', 'section' => 'cashier_sales', 'order' => 0, 'visible' => true],
                ['key' => 'settings', 'section' => 'administration', 'order' => 0, 'visible' => true],
                ['key' => 'change_password', 'section' => 'administration', 'order' => 1, 'visible' => true],
            ],
        ]]);

        $sections = TenantNavRegistry::getEffectiveNavForTenant($company->fresh());
        $admin = collect($sections)->firstWhere('key', 'administration');
        $this->assertNotNull($admin);

        $keys = collect($admin['items'])->pluck('key');
        $this->assertContains('hardware_printer', $keys, 'Printer & Hardware Setup was not reconciled into the custom nav.');
        $printer = collect($admin['items'])->firstWhere('key', 'hardware_printer');
        $this->assertSame('printer_setup', $printer['component']);
        // Still lands ahead of Change Password.
        $this->assertLessThan(
            $keys->search('change_password'),
            $keys->search('hardware_printer'),
        );
    }

    public function test_explicitly_hidden_printer_row_is_not_re_added(): void
    {
        $company = Company::create([
            'name' => 'Hidden Row Co', 'slug' => 'hidden-row-co', 'status' => 'active',
            'pos_mode' => 'retail', 'currency' => 'USD', 'currency_symbol' => '$',
        ]);
        $company->update(['nav_config' => [
            'sections' => [['key' => 'administration', 'order' => 0]],
            'items' => [
                ['key' => 'settings', 'section' => 'administration', 'order' => 0, 'visible' => true],
                ['key' => 'hardware_printer', 'section' => 'administration', 'order' => 1, 'visible' => false],
                ['key' => 'change_password', 'section' => 'administration', 'order' => 2, 'visible' => true],
            ],
        ]]);

        $sections = TenantNavRegistry::getEffectiveNavForTenant($company->fresh());
        $admin = collect($sections)->firstWhere('key', 'administration');
        $this->assertNotContains('hardware_printer', collect($admin['items'])->pluck('key'));
    }

    public function test_printer_setup_view_is_a_valid_graceful_fallback_screen(): void
    {
        file_put_contents(storage_path('installed'), '{}');
        $company = Company::create([
            'name' => 'Fallback Co', 'slug' => 'fallback-co', 'status' => 'active',
            'pos_mode' => 'retail', 'currency' => 'USD', 'currency_symbol' => '$',
        ]);
        $user = User::create([
            'company_id' => $company->id, 'name' => 'Owner', 'email' => 'o@fallback.test',
            'password' => Hash::make('password'), 'role' => 'administrator', 'status' => 'active',
        ]);
        $token = 'zk_live_'.bin2hex(random_bytes(16));
        TenantApiKey::create([
            'company_id' => $company->id, 'user_id' => $user->id,
            'name' => 'Device', 'token' => $token, 'permissions' => ['*'],
        ]);
        $headers = ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];

        $schema = $this->withHeaders($headers)
            ->getJson('/api/tenant/views/printer-setup')
            ->assertOk()
            ->assertJsonPath('schema.title', 'Printer & Hardware Setup')
            ->assertJsonPath('schema.background_color', '#0B1120')
            ->assertJsonPath('schema.components.0.type', 'segmented_tabs')
            ->assertJsonPath('schema.components.0.active_value', 'bluetooth')
            ->assertJsonPath('schema.components.0.active_background_color', '#10B981')
            ->assertJsonPath('schema.components.0.active_text_color', '#0B1120')
            ->assertJsonPath('schema.components.0.inactive_background_color', '#1E293B')
            ->assertJsonPath('schema.components.0.inactive_text_color', '#94A3B8')
            ->assertJsonPath('schema.components.1.type', 'empty_state')
            ->assertJsonPath('schema.components.1.text_color', '#E2E8F0')
            ->json('schema');
        $this->assertStringContainsString('No paired Bluetooth printers found', $schema['components'][1]['message']);
        $this->assertEmpty((new SchemaValidator)->validate($schema));

        // The Receipt Settings screen exposes the same native pairing route.
        $receipts = $this->withHeaders($headers)
            ->getJson('/api/tenant/views/settings-receipts')->assertOk()->json('schema');
        $json = json_encode($receipts, JSON_UNESCAPED_SLASHES);
        $this->assertStringContainsString('"type":"navigate"', $json);
        $this->assertStringContainsString('"endpoint":"printer_setup"', $json);
        $this->assertStringContainsString('Printer & Hardware Setup', $json);

        @unlink(storage_path('installed'));
    }

    public function test_navigate_action_to_native_printer_key_passes_schema_validation(): void
    {
        // A bare snake_case route key is a legal `navigate` endpoint.
        $action = SchemaResponse::navigateAction('printer_setup', 'native', 'Printer & Hardware Setup');
        $screen = SchemaResponse::screen('T', [
            SchemaResponse::buttonOutlined('Printer & Hardware Setup', $action, 'print'),
        ]);
        $this->assertEmpty((new SchemaValidator)->validate($screen));
    }
}
