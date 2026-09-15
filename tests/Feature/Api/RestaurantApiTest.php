<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\KitchenTicket;
use App\Models\Plan;
use App\Models\TenantApiKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RestaurantApiTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected User $user;

    protected TenantApiKey $apiKey;

    protected function setUp(): void
    {
        parent::setUp();

        Plan::create([
            'name' => 'trial', 'display_name' => 'Free Trial', 'price' => 0.00,
            'currency' => 'USD', 'billing_cycle' => 'monthly', 'duration_days' => 14,
            'features' => ['pos' => true], 'limits' => ['products' => 500, 'users' => 5],
            'active' => true,
        ]);

        $this->company = Company::create([
            'name' => 'Copper Kettle Café', 'slug' => 'copper-kettle',
            'email' => 'pos@copperkettle.test', 'country' => 'US',
            'currency' => 'USD', 'currency_symbol' => '$',
            'plan_name' => 'trial', 'expires_at' => now()->addDays(14),
            'pos_mode' => 'restaurant', 'restaurant_mode_locked' => false,
        ]);

        $this->user = User::factory()->create([
            'company_id' => $this->company->id,
            'email' => 'admin@copperkettle.test',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
        ]);

        $this->apiKey = TenantApiKey::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'name' => 'POS Register #1',
            'token' => 'zk_live_'.bin2hex(random_bytes(16)),
            'permissions' => ['*'],
            'active' => true,
        ]);
    }

    private function sendToKitchen(array $overrides = [])
    {
        return $this->withHeaders(['Authorization' => 'Bearer '.$this->apiKey->token])
            ->postJson('/api/v1/pos/restaurant/orders/send-to-kitchen', array_merge([
                'service_type' => 'takeaway',
                'items' => [
                    ['name' => 'Truffle Mushroom Burger', 'price' => 12.5, 'quantity' => 1],
                ],
            ], $overrides));
    }

    public function test_a_custom_prep_time_outside_the_quick_chip_presets_is_accepted(): void
    {
        $res = $this->sendToKitchen(['prep_minutes' => 45])->assertCreated();

        $ticket = KitchenTicket::withoutGlobalScope('company')
            ->where('id', $res->json('kot.id'))->firstOrFail();

        $this->assertSame(45, $ticket->prep_minutes);
        $this->assertEqualsWithDelta(
            now()->addMinutes(45)->timestamp,
            $ticket->target_completion_at->timestamp,
            2
        );
    }

    public function test_a_custom_alert_lead_time_outside_0_2_5_is_accepted(): void
    {
        $res = $this->sendToKitchen([
            'prep_minutes' => 20,
            'intimation_minutes' => 12,
        ])->assertCreated();

        $ticket = KitchenTicket::withoutGlobalScope('company')
            ->where('id', $res->json('kot.id'))->firstOrFail();

        $this->assertSame(12, $ticket->intimation_minutes);
        // alert_trigger_at = target_ready_at - alert_lead_minutes
        $this->assertEqualsWithDelta(
            $ticket->target_completion_at->copy()->subMinutes(12)->timestamp,
            $ticket->alarm_at->timestamp,
            1
        );
    }

    public function test_an_out_of_range_custom_value_is_still_rejected(): void
    {
        // The controller hand-rolls Validator::make()->fails() and returns
        // the error bag under `details`, not Laravel's default `errors` key.
        $this->sendToKitchen(['prep_minutes' => 500])
            ->assertStatus(422)
            ->assertJsonValidationErrors('prep_minutes', 'details');

        $this->sendToKitchen(['intimation_minutes' => -1])
            ->assertStatus(422)
            ->assertJsonValidationErrors('intimation_minutes', 'details');
    }

    public function test_an_alert_lead_longer_than_prep_time_is_clamped_not_negative(): void
    {
        $res = $this->sendToKitchen([
            'prep_minutes' => 5,
            'intimation_minutes' => 45,
        ])->assertCreated();

        $ticket = KitchenTicket::withoutGlobalScope('company')
            ->where('id', $res->json('kot.id'))->firstOrFail();

        // Clamped to prep_minutes so the alert can never fire before the KOT
        // was even sent (alarm_at never precedes sent_to_kitchen_at).
        $this->assertSame(5, $ticket->intimation_minutes);
        $this->assertTrue($ticket->alarm_at->gte($ticket->sent_to_kitchen_at));
    }

    public function test_the_original_quick_chip_presets_still_work(): void
    {
        foreach ([[5, 0], [10, 2], [15, 5], [20, 0], [30, 5]] as [$prep, $alert]) {
            $res = $this->sendToKitchen([
                'prep_minutes' => $prep,
                'intimation_minutes' => $alert,
            ])->assertCreated();

            $this->assertSame($prep, $res->json('kot.prep_minutes'));
            $this->assertSame($alert, $res->json('kot.intimation_minutes'));
        }
    }
}
