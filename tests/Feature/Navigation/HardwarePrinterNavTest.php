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
            ->json('schema');
        $this->assertEmpty((new SchemaValidator())->validate($schema));

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
        $this->assertEmpty((new SchemaValidator())->validate($screen));
    }
}
