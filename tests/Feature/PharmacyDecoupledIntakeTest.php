<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\PharmacyBatch;
use App\Models\PharmacyPrescription;
use App\Models\Product;
use App\Models\TenantApiKey;
use App\Models\User;
use App\Services\Sdui\SchemaValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PharmacyDecoupledIntakeTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $user;
    protected string $apiKeyToken;

    protected function setUp(): void
    {
        parent::setUp();
        file_put_contents(storage_path('installed'), '{}');

        $this->company = Company::create([
            'name' => 'Apex Health Pharmacy',
            'slug' => 'apex-health',
            'status' => 'active',
            'pos_mode' => 'pharmacy',
            'licensed_modules' => ['pharmacy', 'retail'],
            'currency' => 'USD',
            'currency_symbol' => '$',
            'timezone' => 'UTC',
        ]);

        $this->user = User::create([
            'company_id' => $this->company->id,
            'name' => 'Lead Pharmacist',
            'email' => 'pharmacist@apexhealth.test',
            'password' => Hash::make('secret123'),
            'role' => 'administrator',
            'status' => 'active',
        ]);

        $apiKey = TenantApiKey::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'name' => 'Mobile POS Device',
            'token' => 'zk_live_' . bin2hex(random_bytes(16)),
            'permissions' => ['*'],
        ]);
        $this->apiKeyToken = $apiKey->token;
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed'));
        parent::tearDown();
    }

    protected function authHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->apiKeyToken,
            'Accept' => 'application/json',
        ];
    }

    public function test_drawer_navigation_has_decoupled_pharmacy_operations_structure(): void
    {
        $res = $this->withHeaders($this->authHeaders())->getJson('/api/tenant/navigation/drawer');
        $res->assertOk()
            ->assertJsonPath('success', true);

        $sections = $res->json('sections');
        $this->assertNotEmpty($sections);

        // Locate PHARMACY OPERATIONS section
        $pharmacySection = null;
        foreach ($sections as $section) {
            $title = strtoupper($section['title'] ?? $section['label'] ?? '');
            if (str_contains($title, 'PHARMACY')) {
                $pharmacySection = $section;
                break;
            }
        }

        $this->assertNotNull($pharmacySection, 'PHARMACY OPERATIONS section must exist in drawer tree.');
        $this->assertSame('PHARMACY OPERATIONS', $pharmacySection['title']);

        $itemKeys = array_column($pharmacySection['items'], 'key');
        $this->assertContains('pharmacy_pos', $itemKeys);
        $this->assertContains('new_prescription_intake', $itemKeys);
        $this->assertContains('prescriptions_queue', $itemKeys);
        $this->assertContains('batch_inventory', $itemKeys);

        // Verify specific route mappings
        $itemsByKey = [];
        foreach ($pharmacySection['items'] as $item) {
            $itemsByKey[$item['key']] = $item;
        }

        // Pharmacy POS opens the core native POS resolver directly, never the
        // retired "Pharmacy Counter POS" SDUI screen.
        $this->assertSame('pos', $itemsByKey['pharmacy_pos']['route']);
        $this->assertSame('pos', $itemsByKey['pharmacy_pos']['component']);
        $this->assertSame('/api/tenant/views/pharmacy-rx-create', $itemsByKey['new_prescription_intake']['route']);
        $this->assertSame('/api/tenant/views/pharmacy-prescriptions', $itemsByKey['prescriptions_queue']['route']);
        $this->assertSame('/api/tenant/views/pharmacy-batches', $itemsByKey['batch_inventory']['route']);

        $this->assertSame('note_add', $itemsByKey['new_prescription_intake']['icon']);
        $this->assertSame('medical_information', $itemsByKey['prescriptions_queue']['icon']);
    }

    public function test_custom_navigation_labels_apply_to_decoupled_pharmacy_items(): void
    {
        // Custom labels override via settings endpoint
        $updateRes = $this->withHeaders($this->authHeaders())->postJson('/api/tenant/settings/navigation-labels', [
            'new_rx_intake' => 'Record Doctor Rx',
            'prescriptions_queue' => 'Patient Dispensary Queue',
            'batch_inventory' => 'Batch Expiration Register',
        ]);
        $updateRes->assertOk();

        $drawerRes = $this->withHeaders($this->authHeaders())->getJson('/api/tenant/navigation/drawer');
        $drawerRes->assertOk();

        $sections = $drawerRes->json('sections');
        $pharmacySection = null;
        foreach ($sections as $section) {
            if (str_contains(strtoupper($section['title'] ?? ''), 'PHARMACY')) {
                $pharmacySection = $section;
                break;
            }
        }

        $itemsByKey = [];
        foreach ($pharmacySection['items'] as $item) {
            $itemsByKey[$item['key']] = $item;
        }

        $this->assertSame('Record Doctor Rx', $itemsByKey['new_prescription_intake']['title']);
        $this->assertSame('Patient Dispensary Queue', $itemsByKey['prescriptions_queue']['title']);
        $this->assertSame('Batch Expiration Register', $itemsByKey['batch_inventory']['title']);
    }

    public function test_pharmacy_rx_create_view_returns_standalone_intake_screen(): void
    {
        $response = $this->withHeaders($this->authHeaders())->getJson('/api/tenant/views/pharmacy-rx-create');
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('schema.title', 'New Prescription Intake');

        $schema = $response->json('schema');
        $validator = new SchemaValidator();
        $errors = $validator->validate($schema);
        $this->assertEmpty($errors, 'Schema errors: ' . implode(', ', $errors));

        $schemaJson = json_encode($schema, JSON_UNESCAPED_SLASHES);
        $this->assertStringContainsString('New Prescription Intake', $schemaJson);
        $this->assertStringContainsString('customer_selector', $schemaJson);
        $this->assertStringContainsString('patient_name', $schemaJson);
        $this->assertStringContainsString('patient_phone', $schemaJson);
        $this->assertStringContainsString('doctor_name', $schemaJson);
        $this->assertStringContainsString('doctor_registration_no', $schemaJson);
        $this->assertStringContainsString('prescription_date', $schemaJson);
        $this->assertStringContainsString('diagnosis', $schemaJson);
        $this->assertStringContainsString('dosage_duration_days', $schemaJson);
        $this->assertStringContainsString('/api/tenant/pharmacy/prescriptions', $schemaJson);
        $this->assertStringContainsString('/api/tenant/views/pharmacy-prescriptions', $schemaJson);

        // The raw "Scanned Rx Image URL" text field is gone, replaced by a
        // native secure file_picker that uploads to the tenant upload endpoint.
        $this->assertStringNotContainsString('Scanned Rx Image URL', $schemaJson);
        $picker = $this->firstComponentOfType($schema, 'file_picker');
        $this->assertNotNull($picker, 'Intake form must render a file_picker for the prescription document.');
        $this->assertSame('rx_attachment_url', $picker['name']);
        $this->assertSame('/api/tenant/uploads/prescription-doc', $picker['upload_endpoint']);
        $this->assertSame(['jpg', 'jpeg', 'png', 'webp', 'pdf', 'heic'], $picker['allowed_extensions']);
        $this->assertSame(10, $picker['max_size_mb']);
        $this->assertTrue($picker['allow_camera']);
        $this->assertTrue($picker['allow_gallery']);
        $this->assertTrue($picker['allow_document']);

        // No text_input should still carry the old URL field name.
        $this->assertStringNotContainsString('"name":"rx_image_url"', $schemaJson);
    }

    /**
     * Depth-first search for the first component of $type in a schema tree.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>|null
     */
    private function firstComponentOfType(array $node, string $type): ?array
    {
        if (($node['type'] ?? null) === $type) {
            return $node;
        }

        foreach (['components', 'children'] as $bucket) {
            foreach ($node[$bucket] ?? [] as $child) {
                if (is_array($child)) {
                    $found = $this->firstComponentOfType($child, $type);
                    if ($found !== null) {
                        return $found;
                    }
                }
            }
        }

        return null;
    }

    public function test_pharmacy_prescriptions_queue_is_clean_and_includes_intake_action_button(): void
    {
        // Seed prescription
        PharmacyPrescription::create([
            'company_id' => $this->company->id,
            'tenant_id' => $this->company->id,
            'prescription_number' => 'RX-APEX-001',
            'prescription_date' => now()->toDateString(),
            'patient_name' => 'John Doe Patient',
            'patient_phone' => '+15550001',
            'doctor_name' => 'Dr. Robert Smith',
            'status' => 'pending',
            'notes' => 'Ciprofloxacin 500mg BD x 5 days',
        ]);

        $response = $this->withHeaders($this->authHeaders())->getJson('/api/tenant/views/pharmacy-prescriptions');
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('schema.title', 'Prescriptions Queue');

        $schema = $response->json('schema');
        $validator = new SchemaValidator();
        $errors = $validator->validate($schema);
        $this->assertEmpty($errors, 'Schema errors: ' . implode(', ', $errors));

        $schemaJson = json_encode($schema, JSON_UNESCAPED_SLASHES);

        // Must display the top intake action button
        $this->assertStringContainsString('+ New Prescription Intake', $schemaJson);
        $this->assertStringContainsString('/api/tenant/views/pharmacy-rx-create', $schemaJson);

        // The "Pharmacy POS" quick action opens the core native POS ('pos'),
        // never the retired "Pharmacy Counter POS" SDUI screen.
        $this->assertStringContainsString('"label":"Pharmacy POS"', $schemaJson);
        $this->assertStringNotContainsString('/api/tenant/views/pharmacy-pos', $schemaJson);
        $this->assertStringNotContainsString('Pharmacy Counter POS', $schemaJson);
        $posButton = $this->firstButtonWithLabel($schema, 'Pharmacy POS');
        $this->assertNotNull($posButton, 'Prescriptions queue must expose a "Pharmacy POS" button.');
        $this->assertSame('navigate', $posButton['action']['type']);
        $this->assertSame('pos', $posButton['action']['endpoint']);

        // Must display existing patient in the queue
        $this->assertStringContainsString('John Doe Patient', $schemaJson);
        $this->assertStringContainsString('RX-APEX-001', $schemaJson);
        $this->assertStringContainsString('Load Prescription into POS', $schemaJson);

        // Must NOT render an embedded accordion form
        $this->assertStringNotContainsString('accordion_group', $schemaJson);
    }

    public function test_load_prescription_button_carries_structured_line_items_for_the_native_pos_cart(): void
    {
        $product = Product::create([
            'company_id' => $this->company->id,
            'name' => 'Amoxicillin 500mg Capsules',
            'generic_name' => 'Amoxicillin',
            'sale_price' => 9.50,
            'current_stock' => 50,
            'active' => true,
        ]);
        PharmacyBatch::create([
            'company_id' => $this->company->id,
            'product_id' => $product->id,
            'batch_number' => 'AMOX-FEFO-1',
            'expiry_date' => now()->addMonths(8),
            'selling_price' => 9.50,
            'stock_qty' => 50,
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'company_id' => $this->company->id,
            'name' => 'prakash',
            'phone' => '918535075196',
        ]);

        $rx = PharmacyPrescription::create([
            'company_id' => $this->company->id,
            'tenant_id' => $this->company->id,
            'prescription_number' => 'RX-20260907-0001',
            'prescription_date' => now()->toDateString(),
            'customer_id' => $customer->id,
            'patient_name' => 'prakash',
            'patient_phone' => '918535075196',
            'doctor_name' => 'Ramesh agarwal',
            'doctor_registration_no' => 'MED-88912',
            'diagnosis' => 'bdbeve',
            'medicines' => [
                ['name' => 'Amoxicillin 500mg Capsules', 'qty' => 2, 'dosage' => '1 cap every 8 hrs', 'days_supply' => 7],
            ],
            'status' => 'pending',
        ]);

        $schema = $this->withHeaders($this->authHeaders())
            ->getJson('/api/tenant/views/pharmacy-prescriptions')
            ->assertOk()
            ->json('schema');

        $this->assertEmpty((new SchemaValidator())->validate($schema));

        $button = $this->firstButtonWithLabel($schema, 'Load Prescription into POS');
        $this->assertNotNull($button);

        $action = $button['action'];
        $this->assertSame('load_rx_to_pos', $action['type']);

        $payload = $action['payload'];
        $this->assertSame((string) $rx->id, $payload['rx_id']);
        $this->assertSame('RX-20260907-0001', $payload['rx_number']);
        $this->assertSame($customer->id, $payload['customer']['id']);
        $this->assertSame('prakash', $payload['customer']['name']);
        $this->assertSame('918535075196', $payload['customer']['phone']);
        $this->assertSame('Ramesh agarwal', $payload['doctor']['name']);
        $this->assertSame('MED-88912', $payload['doctor']['registration_no']);

        $this->assertCount(1, $payload['items']);
        $item = $payload['items'][0];
        $this->assertSame((int) $product->id, $item['product_id']);
        $this->assertSame('Amoxicillin 500mg Capsules', $item['product_name']);
        $this->assertSame(2, $item['quantity']);
        $this->assertSame(9.5, $item['unit_price']);
        $this->assertSame('1 cap every 8 hrs', $item['dosage']);
        $this->assertSame(7, $item['days_supply']);

        // The button no longer opens the detached checkout sheet.
        $schemaJson = json_encode($schema, JSON_UNESCAPED_SLASHES);
        $this->assertStringNotContainsString('/checkout-sheet', $schemaJson);
    }

    public function test_queue_filter_and_action_rows_are_laid_out_to_avoid_text_clipping(): void
    {
        PharmacyPrescription::create([
            'company_id' => $this->company->id,
            'tenant_id' => $this->company->id,
            'prescription_number' => 'RX-LAYOUT-1',
            'prescription_date' => now()->toDateString(),
            'patient_name' => 'Layout Patient',
            'doctor_name' => 'Dr. Layout',
            'status' => 'pending',
        ]);

        $schema = $this->withHeaders($this->authHeaders())
            ->getJson('/api/tenant/views/pharmacy-prescriptions')
            ->assertOk()
            ->json('schema');

        $this->assertEmpty((new SchemaValidator())->validate($schema));

        // Filter chips sit in a horizontally scrollable row so full labels
        // like "Pending (1)" / "Dispensed (2)" never get an ellipsis.
        $filterRow = $this->rowContainingButtonLabelPrefix($schema, 'All (');
        $this->assertNotNull($filterRow, 'Filter row not found.');
        $this->assertTrue($filterRow['scrollable'] ?? false);
        foreach ($filterRow['components'] as $chip) {
            $this->assertTrue($chip['dense'] ?? false, 'Filter chip must be dense.');
            $this->assertFalse($chip['full_width'] ?? true, 'Filter chip must not be full width.');
        }

        // The "+ New Prescription Intake" / "Pharmacy POS" action row wraps to
        // a second line on narrow screens instead of clipping.
        $actionRow = $this->rowContainingButtonLabelPrefix($schema, '+ New Prescription Intake');
        $this->assertNotNull($actionRow, 'Header action row not found.');
        $this->assertTrue($actionRow['wrap'] ?? false);
        foreach ($actionRow['components'] as $btn) {
            $this->assertTrue($btn['dense'] ?? false);
        }
    }

    /**
     * The `row` component whose direct button children include one whose label
     * starts with $prefix.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>|null
     */
    private function rowContainingButtonLabelPrefix(array $node, string $prefix): ?array
    {
        if (($node['type'] ?? null) === 'row') {
            foreach ($node['components'] ?? [] as $child) {
                if (is_array($child)
                    && str_starts_with((string) ($child['label'] ?? ''), $prefix)
                    && str_starts_with((string) ($child['type'] ?? ''), 'button')) {
                    return $node;
                }
            }
        }

        foreach (['components', 'children'] as $bucket) {
            foreach ($node[$bucket] ?? [] as $child) {
                if (is_array($child)) {
                    $found = $this->rowContainingButtonLabelPrefix($child, $prefix);
                    if ($found !== null) {
                        return $found;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Depth-first search for the first button component carrying $label.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>|null
     */
    private function firstButtonWithLabel(array $node, string $label): ?array
    {
        if (($node['label'] ?? null) === $label && str_starts_with((string) ($node['type'] ?? ''), 'button')) {
            return $node;
        }

        foreach (['components', 'children'] as $bucket) {
            foreach ($node[$bucket] ?? [] as $child) {
                if (is_array($child)) {
                    $found = $this->firstButtonWithLabel($child, $label);
                    if ($found !== null) {
                        return $found;
                    }
                }
            }
        }

        return null;
    }

    public function test_create_prescription_via_api_and_verify_in_queue(): void
    {
        $patient = Customer::create([
            'company_id' => $this->company->id,
            'name' => 'Sarah Connor',
            'phone' => '+15551234',
        ]);

        $createRes = $this->withHeaders($this->authHeaders())->postJson('/api/tenant/pharmacy/prescriptions', [
            'customer_id' => $patient->id,
            'patient_name' => 'Sarah Connor',
            'patient_phone' => '+15551234',
            'doctor_name' => 'Dr. Gregory House',
            'doctor_registration_no' => 'LIC-HOUSE-MD',
            'prescription_date' => now()->toDateString(),
            'diagnosis' => 'Lupus ruled out, severe inflammation',
            'notes' => 'Prednisone 20mg OD x 10 days',
            'dosage_duration_days' => 10,
        ]);

        $createRes->assertOk()
            ->assertJsonPath('success', true);

        $rxId = $createRes->json('prescription.id');
        $this->assertNotNull($rxId);

        // Verify appearance in prescriptions queue
        $queueRes = $this->withHeaders($this->authHeaders())->getJson('/api/tenant/views/pharmacy-prescriptions?status=pending');
        $queueRes->assertOk();
        $queueJson = json_encode($queueRes->json(), JSON_UNESCAPED_SLASHES);

        $this->assertStringContainsString('Sarah Connor', $queueJson);
        $this->assertStringContainsString('Dr. Gregory House', $queueJson);
    }

    public function test_batches_view_is_a_single_tabbed_screen_with_deep_links(): void
    {
        $product = Product::create([
            'company_id' => $this->company->id,
            'name' => 'Metformin 500mg',
            'sale_price' => 4.00,
            'current_stock' => 100,
            'active' => true,
        ]);
        $batch = PharmacyBatch::create([
            'company_id' => $this->company->id,
            'product_id' => $product->id,
            'batch_number' => 'MET-2026-01',
            'manufacturing_date' => now()->subMonths(2),
            'expiry_date' => now()->addMonths(10),
            'cost_price' => 2.00,
            'selling_price' => 4.00,
            'stock_qty' => 60,
            'rack_location' => 'A3',
            'is_active' => true,
        ]);

        $validator = new SchemaValidator();

        // --- default load: Active Batches tab ---
        $schema = $this->withHeaders($this->authHeaders())
            ->getJson('/api/tenant/views/pharmacy-batches')
            ->assertOk()->assertJsonPath('schema.title', 'Batch & Expiry Manager')
            ->json('schema');
        $this->assertEmpty($validator->validate($schema));

        $tabs = $this->firstComponentOfType($schema, 'tabs');
        $this->assertNotNull($tabs, 'Batches view must be a tabbed single-screen.');
        $this->assertSame(0, $tabs['initial_index']);
        $this->assertTrue($tabs['is_scrollable']);
        $this->assertSame(
            ['active_batches', 'register_batch', 'stock_adjust'],
            array_column($tabs['tabs'], 'id'),
        );
        // Only Tab 1 carries the batch card; the register/adjust forms are on
        // their own tabs, not stacked above the list.
        $active = $tabs['tabs'][0]['components'];
        $this->assertStringContainsString('Metformin 500mg', json_encode($active));
        $this->assertStringNotContainsString('Save Batch to Inventory', json_encode($active));

        // --- ?tab=register opens Tab 2 ---
        $reg = $this->withHeaders($this->authHeaders())
            ->getJson('/api/tenant/views/pharmacy-batches?tab=register')->assertOk()->json('schema');
        $this->assertSame(1, $this->firstComponentOfType($reg, 'tabs')['initial_index']);

        // --- ?tab=adjust&batch_id opens Tab 3 with the batch pre-filled ---
        $adj = $this->withHeaders($this->authHeaders())
            ->getJson("/api/tenant/views/pharmacy-batches?tab=adjust&batch_id={$batch->id}")
            ->assertOk()->json('schema');
        $adjTabs = $this->firstComponentOfType($adj, 'tabs');
        $this->assertSame(2, $adjTabs['initial_index']);
        $adjJson = json_encode($adjTabs['tabs'][2]['components']);
        $this->assertStringContainsString('"name":"batch_id","label":"Batch ID \/ Medicine Search","initial_value":"' . $batch->id . '"', $adjJson);
        $this->assertStringContainsString('Current Qty: 60', $adjJson);

        // --- ?search= filters the Active Batches list; the search field and
        //     button are bound to a filter_view action on `search` ---
        foreach (['search', 'q'] as $param) {
            $hit = $this->withHeaders($this->authHeaders())
                ->getJson("/api/tenant/views/pharmacy-batches?{$param}=MET-2026")->assertOk()->json('schema');
            $this->assertStringContainsString('Metformin 500mg', json_encode($this->firstComponentOfType($hit, 'tabs')['tabs'][0]));
        }

        $miss = $this->withHeaders($this->authHeaders())
            ->getJson('/api/tenant/views/pharmacy-batches?search=Ibuprofen')->assertOk()->json('schema');
        $missActive = json_encode($this->firstComponentOfType($miss, 'tabs')['tabs'][0]);
        $this->assertStringNotContainsString('Metformin 500mg', $missActive);
        $this->assertStringContainsString('No batches match your search', $missActive);

        // The Active Batches tab leads with a single full-width search_bar,
        // not a narrow text_input + detached button.
        $searchBar = $this->firstComponentOfType($tabs['tabs'][0], 'search_bar');
        $this->assertNotNull($searchBar, 'Active Batches must use a search_bar.');
        $this->assertSame('search', $searchBar['name']);
        $this->assertSame('Search medicine name, batch #, or rack...', $searchBar['placeholder']);
        $this->assertTrue($searchBar['clearable']);
        $this->assertSame(
            ['type' => 'filter_view', 'endpoint' => '/api/tenant/views/pharmacy-batches?tab=active', 'fields' => ['search']],
            $searchBar['action'],
        );
        $this->assertNull($this->firstComponentOfType($tabs['tabs'][0], 'text_input'));
    }

    public function test_register_batch_dropdown_emits_integer_ids_and_store_accepts_string_ints(): void
    {
        $amox = Product::create([
            'company_id' => $this->company->id, 'name' => 'Amoxicillin 500mg Capsules (10pk)',
            'sale_price' => 9.50, 'current_stock' => 0, 'active' => true,
        ]);
        Product::create([
            'company_id' => $this->company->id, 'name' => 'Ibuprofen 400mg Softgels (20pk)',
            'sale_price' => 6.00, 'current_stock' => 0, 'active' => true,
        ]);

        $schema = $this->withHeaders($this->authHeaders())
            ->getJson('/api/tenant/views/pharmacy-batches?tab=register')->assertOk()->json('schema');
        $tabs = $this->firstComponentOfType($schema, 'tabs');
        $dropdown = $this->firstComponentOfType($tabs['tabs'][1], 'dropdown_select');

        $this->assertSame('product_id', $dropdown['name']);
        // Option values are the integer primary keys (as strings on the wire),
        // never the medicine name.
        $values = array_column($dropdown['options'], 'value');
        $this->assertSame([(string) $amox->id, (string) Product::where('name', 'like', 'Ibuprofen%')->first()->id], $values);
        $this->assertSame($amox->name, $dropdown['options'][0]['label']);

        // Store accepts a string integer id (the shape the SDUI form submits).
        $ok = $this->withHeaders($this->authHeaders())->postJson('/api/tenant/pharmacy/batches', [
            'product_id' => (string) $amox->id,
            'batch_number' => 'AMX-2026-77',
            'expiry_date' => now()->addYear()->toDateString(),
            'stock_qty' => '40',
            'cost_price' => '5.00',
            'selling_price' => '9.50',
            'alert_days_before_expiry' => '60',
        ]);
        $ok->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('pharmacy_batches', [
            'batch_number' => 'AMX-2026-77', 'product_id' => $amox->id, 'stock_qty' => 40,
        ]);

        // A non-numeric product_id fails cleanly (422), never a 500.
        $bad = $this->withHeaders($this->authHeaders())->postJson('/api/tenant/pharmacy/batches', [
            'product_id' => 'Amoxicillin 500mg Capsules (10pk)',
            'batch_number' => 'AMX-BAD-1',
            'expiry_date' => now()->addYear()->toDateString(),
            'stock_qty' => '10',
        ]);
        $bad->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_adjustment_reason_is_creatable_and_accepts_free_text(): void
    {
        $product = Product::create([
            'company_id' => $this->company->id, 'name' => 'Ranitidine 150mg',
            'sale_price' => 3.00, 'current_stock' => 0, 'active' => true,
        ]);
        $batch = PharmacyBatch::create([
            'company_id' => $this->company->id, 'product_id' => $product->id,
            'batch_number' => 'RAN-01', 'expiry_date' => now()->addMonths(6),
            'cost_price' => 1.5, 'selling_price' => 3.0, 'stock_qty' => 30, 'is_active' => true,
        ]);

        // Schema: the reason field is a creatable_select with an "+ Other"
        // custom entry, not a rigid dropdown.
        $schema = $this->withHeaders($this->authHeaders())
            ->getJson("/api/tenant/views/pharmacy-batches?tab=adjust&batch_id={$batch->id}")
            ->assertOk()->json('schema');
        $this->assertEmpty((new SchemaValidator())->validate($schema));

        $reason = $this->firstComponentOfType(
            $this->firstComponentOfType($schema, 'tabs')['tabs'][2],
            'creatable_select',
        );
        $this->assertNotNull($reason, 'Adjustment Reason must be a creatable_select.');
        $this->assertSame('reason', $reason['name']);
        $this->assertTrue($reason['allow_custom']);
        $this->assertSame('__custom__', $reason['custom_value']);
        $this->assertContains('Supplier recall / withdrawal', array_column($reason['options'], 'value'));

        // A free-text custom reason is persisted verbatim on adjust.
        $adj = $this->withHeaders($this->authHeaders())->postJson('/api/tenant/pharmacy/batches/adjust', [
            'batch_id' => $batch->id,
            'new_stock_qty' => 25,
            'reason' => 'Temperature excursion during 3rd-party cold-chain transfer',
        ]);
        $adj->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'pharmacy.batch_adjusted',
        ]);
        $log = \App\Models\AuditLog::where('action', 'pharmacy.batch_adjusted')->latest('id')->first();
        $this->assertSame('Temperature excursion during 3rd-party cold-chain transfer', $log->details['reason']);

        // The bare "__custom__" sentinel never lands in the record.
        $ret = $this->withHeaders($this->authHeaders())->postJson('/api/tenant/pharmacy/batches/return', [
            'batch_id' => $batch->id,
            'quantity' => 2,
            'reason' => '__custom__',
        ]);
        $ret->assertOk()->assertJsonPath('success', true);
        $retLog = \App\Models\AuditLog::where('action', 'pharmacy.vendor_return')->latest('id')->first();
        $this->assertNull($retLog->details['reason']);
    }
}
