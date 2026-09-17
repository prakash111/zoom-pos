<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\RepairTicket;
use App\Models\TenantApiKey;
use App\Models\User;
use App\Services\Sdui\SchemaValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RepairChecklistCustomizationTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected array $headers;

    protected function setUp(): void
    {
        parent::setUp();
        file_put_contents(storage_path('installed'), '{}');

        $this->company = Company::create([
            'name' => 'Cobbler & Co',
            'slug' => 'cobbler-co',
            'status' => 'active',
            'pos_mode' => 'repair',
            'currency' => 'USD',
            'currency_symbol' => '$',
        ]);
        $user = User::create([
            'company_id' => $this->company->id,
            'name' => 'Owner',
            'email' => 'owner@cobbler.test',
            'password' => Hash::make('password'),
            'role' => 'administrator',
            'status' => 'active',
        ]);
        $token = 'zk_live_'.bin2hex(random_bytes(16));
        TenantApiKey::create([
            'company_id' => $this->company->id,
            'user_id' => $user->id,
            'name' => 'Device',
            'token' => $token,
            'permissions' => ['*'],
        ]);
        $this->headers = ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed'));
        parent::tearDown();
    }

    /** @param array<string,mixed> $node */
    private function firstOfType(array $node, string $type): ?array
    {
        if (($node['type'] ?? null) === $type) {
            return $node;
        }
        foreach (['components', 'children'] as $bucket) {
            foreach ($node[$bucket] ?? [] as $child) {
                if (is_array($child)) {
                    $found = $this->firstOfType($child, $type);
                    if ($found !== null) {
                        return $found;
                    }
                }
            }
        }

        return null;
    }

    /** @param array<string,mixed> $node @return list<array<string,mixed>> */
    private function allOfType(array $node, string $type): array
    {
        $out = [];
        if (($node['type'] ?? null) === $type) {
            $out[] = $node;
        }
        $sub = $node['components'] ?? $node['children'] ?? [];
        foreach ($sub as $child) {
            if (is_array($child)) {
                $out = array_merge($out, $this->allOfType($child, $type));
            }
        }

        return $out;
    }

    public function test_checklist_schema_falls_back_to_defaults_then_honours_custom(): void
    {
        $this->assertSame(
            Company::DEFAULT_REPAIR_CHECKLIST,
            $this->company->fresh()->repairChecklistSchema(),
        );

        $this->company->update(['repair_checklist_schema' => [
            ['label' => 'Sole & heel wear'],
            ['label' => 'Stitching integrity', 'default' => 'fail'],
            'Waterproofing check',
        ]]);

        $schema = $this->company->fresh()->repairChecklistSchema();
        $this->assertCount(3, $schema);
        $this->assertSame('sole_heel_wear', $schema[0]['key']);
        $this->assertSame('pass', $schema[0]['default']);
        $this->assertSame('fail', $schema[1]['default']);
        $this->assertSame('Waterproofing check', $schema[2]['label']);
    }

    public function test_settings_view_and_save_endpoint_round_trip(): void
    {
        $view = $this->withHeaders($this->headers)
            ->getJson('/api/tenant/views/repair-checklist-settings')
            ->assertOk()
            ->assertJsonPath('schema.title', 'Repair Intake Checklist')
            ->json('schema');
        $this->assertEmpty((new SchemaValidator())->validate($view));
        $this->assertNotNull($this->firstOfType($view, 'text_input'));

        $save = $this->withHeaders($this->headers)->postJson('/api/tenant/settings/repair-checklist', [
            'checklist_labels' => "Frame & fork alignment | pass\nBrake pads & cables | fail\nTyre tread & pressure | pending\nDrivetrain wear",
        ]);
        $save->assertOk()->assertJsonPath('success', true);

        $schema = $this->company->fresh()->repair_checklist_schema;
        $this->assertCount(4, $schema);
        $this->assertSame('frame_fork_alignment', $schema[0]['key']);
        $this->assertSame('fail', $schema[1]['default']);
        $this->assertSame('pending', $schema[2]['default']);

        // Reset drops the custom list.
        $this->withHeaders($this->headers)->postJson('/api/tenant/settings/repair-checklist', ['reset' => true])
            ->assertOk()->assertJsonPath('success', true);
        $this->assertNull($this->company->fresh()->repair_checklist_schema);
    }

    public function test_intake_wizard_step_4_reflects_the_custom_checklist(): void
    {
        $this->company->update(['repair_checklist_schema' => [
            ['label' => 'Motor & ESC test', 'default' => 'pass'],
            ['label' => 'Propeller balance', 'default' => 'pending'],
            ['label' => 'GPS lock & compass', 'default' => 'pass'],
        ]]);

        $schema = $this->withHeaders($this->headers)
            ->getJson('/api/tenant/views/repair-create-ticket')->assertOk()->json('schema');
        $json = json_encode($schema, JSON_UNESCAPED_SLASHES);

        $this->assertStringContainsString('"name":"check_motor_esc_test"', $json);
        $this->assertStringContainsString('1. Motor & ESC test', $json);
        $this->assertStringContainsString('2. Propeller balance', $json);
        $this->assertStringContainsString('"name":"custom_checklist"', $json);
        // Hardcoded phone checkpoints are gone.
        $this->assertStringNotContainsString('Front & Back Cameras', $json);
    }

    public function test_intake_submit_builds_inspection_checklist_from_check_fields(): void
    {
        $this->company->update(['repair_checklist_schema' => [
            ['label' => 'Zipper & pull tabs', 'default' => 'pass'],
            ['label' => 'Lining condition', 'default' => 'pass'],
        ]]);

        $res = $this->withHeaders($this->headers)->postJson('/api/tenant/repair/tickets', [
            'customer_name' => 'Walk-in',
            'brand' => 'Herschel',
            'model' => 'Little America',
            'issue_description' => 'Broken zipper',
            'check_zipper_pull_tabs' => 'Fail',
            'check_lining_condition' => 'Pass',
            'custom_checklist' => "Strap rivets\nBase abrasion",
        ]);
        $res->assertOk()->assertJsonPath('success', true);

        $checklist = collect($res->json('ticket.inspection_checklist'));
        $this->assertCount(4, $checklist);
        $this->assertSame('fail', $checklist->firstWhere('key', 'zipper_pull_tabs')['status']);
        $this->assertSame('pass', $checklist->firstWhere('key', 'lining_condition')['status']);
        $this->assertSame('pending', $checklist->firstWhere('item_name', 'Strap rivets')['status']);
    }

    public function test_workbench_checklist_and_status_are_interactive(): void
    {
        $ticket = RepairTicket::create([
            'company_id' => $this->company->id,
            'ticket_number' => 'REP-WB-1',
            'customer_name' => 'Jane',
            'brand' => 'Trek',
            'model' => 'FX 3',
            'issue_description' => 'Squeaky brakes',
            'status' => 'received',
            'inspection_checklist' => [
                ['key' => 'brakes', 'item_name' => 'Brake pads', 'status' => 'pending'],
                ['key' => 'gears', 'item_name' => 'Drivetrain', 'status' => 'pending'],
            ],
        ]);

        $schema = $this->withHeaders($this->headers)
            ->getJson("/api/tenant/views/repair-detail?ticket_id={$ticket->id}")->assertOk()->json('schema');
        $this->assertEmpty((new SchemaValidator())->validate($schema));

        $triggers = $this->allOfType($schema, 'action_sheet_trigger');
        // 2 checklist rows + 1 header status selector.
        $this->assertCount(3, $triggers);
        $statusSelector = collect($triggers)->firstWhere('label', 'Change Ticket Status');
        $this->assertNotNull($statusSelector);
        $this->assertCount(6, $statusSelector['options']);
        $this->assertSame('waiting_parts', $statusSelector['options'][2]['action']['payload']['status']);

        $brakesRow = collect($triggers)->first(fn ($t) => str_contains($t['label'], 'Brake pads'));
        $this->assertNotNull($brakesRow);
        $passOpt = collect($brakesRow['options'])->firstWhere('label', 'Pass');
        $this->assertSame(
            ['key' => 'brakes', 'value' => 'pass'],
            $passOpt['action']['payload'],
        );

        // The option's api_post updates that one item and refreshes.
        $upd = $this->withHeaders($this->headers)
            ->postJson("/api/tenant/repair/tickets/{$ticket->id}/checklist", ['key' => 'brakes', 'value' => 'PASS']);
        $upd->assertOk()->assertJsonPath('action', 'refresh_view');
        $list = collect($ticket->fresh()->inspection_checklist);
        $this->assertSame('pass', $list->firstWhere('key', 'brakes')['status']);
        $this->assertSame('pending', $list->firstWhere('key', 'gears')['status']);

        // Header status selector -> ready_pickup normalises to "ready".
        $this->withHeaders($this->headers)
            ->postJson("/api/tenant/repair/tickets/{$ticket->id}/status", ['status' => 'ready_pickup'])
            ->assertOk();
        $this->assertSame('ready', $ticket->fresh()->status);
    }

    public function test_workbench_share_button_opens_native_share_sheet(): void
    {
        $ticket = RepairTicket::create([
            'company_id' => $this->company->id,
            'ticket_number' => 'REP-SH-1',
            'customer_name' => 'Priya',
            'customer_phone' => '+91 90000 11111',
            'brand' => 'Apple',
            'model' => 'iPhone 13',
            'issue_description' => 'Cracked screen',
            'status' => 'diagnosing',
        ]);

        $schema = $this->withHeaders($this->headers)
            ->getJson("/api/tenant/views/repair-detail?ticket_id={$ticket->id}")->assertOk()->json('schema');

        $buttons = $this->allOfType($schema, 'button_outlined');
        $share = collect($buttons)->firstWhere('label', 'Share Ticket');
        $this->assertNotNull($share, 'Workbench is missing the Share Ticket button.');
        $this->assertSame('form_submit', $share['action']['type']);
        $this->assertSame(
            "/api/tenant/repair/tickets/{$ticket->id}/share",
            $share['action']['endpoint'],
        );
        // Must NOT redirect away from the workbench after sharing.
        $this->assertArrayNotHasKey('redirect_route', $share['action']);

        $res = $this->withHeaders($this->headers)
            ->postJson("/api/tenant/repair/tickets/{$ticket->id}/share");
        $res->assertOk()
            ->assertJsonPath('action', 'show_ticket_share_sheet')
            ->assertJsonPath('share.id', 'REP-SH-1')
            ->assertJsonPath('share.customer_name', 'Priya')
            ->assertJsonPath('share.device', 'Apple iPhone 13')
            ->assertJsonPath('share.status', 'diagnosing');
        $share = $res->json('share');
        $this->assertStringContainsString('/portal/repair/REP-SH-1', $share['tracking_url']);
        $this->assertStringStartsWith('https://wa.me/919000011111', $share['whatsapp_url']);
        $this->assertStringContainsString('REP-SH-1', $share['share_text']);
        $this->assertStringContainsString("/repair/tickets/{$ticket->id}/intake-sheet", $share['print_url']);
    }
}
