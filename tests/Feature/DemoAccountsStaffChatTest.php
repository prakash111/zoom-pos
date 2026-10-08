<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\DemoChatSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Modules\Chat\Models\ChatConversation;
use Modules\Chat\Models\ChatMessage;
use Modules\Chat\Models\PromotionalBroadcast;
use Tests\TestCase;

class DemoAccountsStaffChatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new \App\Providers\ModuleServiceProvider($this->app))->bootModule('Chat');
        $chatProvider = new \Modules\Chat\Providers\ChatServiceProvider($this->app);
        $chatProvider->boot();

        $demoSlugs = [
            'tenant-demo-retail' => ['name' => 'Metro Retail Mart', 'mode' => 'retail'],
            'tenant-demo-restaurant' => ['name' => 'Urban Bistro & Cafe', 'mode' => 'restaurant'],
            'tenant-demo-pharmacy' => ['name' => 'CareWell Chemist & Pharmacy', 'mode' => 'pharmacy'],
            'tenant-demo-repairs' => ['name' => 'QuickFix Device Repair Center', 'mode' => 'repair_technician'],
            'tenant-demo-salon' => ['name' => 'Luna Salon & Spa', 'mode' => 'service_booking'],
            'tenant-demo-all-enterprise' => ['name' => 'ZoomNearby Enterprise Demo', 'mode' => 'retail'],
        ];

        foreach ($demoSlugs as $slug => $meta) {
            $company = Company::create([
                'name' => $meta['name'],
                'slug' => $slug,
                'is_demo' => true,
                'pos_mode' => $meta['mode'],
                'licensed_modules' => [$meta['mode'], 'customers', 'inventory', 'sales', 'finance', 'hrm', 'chat'],
            ]);

            $store = Store::create([
                'company_id' => $company->id,
                'name' => $meta['name'] . ' Primary Store',
                'code' => 'STR-' . substr(md5($slug), 0, 6),
                'is_active' => true,
                'is_primary' => true,
            ]);

            User::create([
                'company_id' => $company->id,
                'name' => $meta['name'] . ' Admin',
                'login' => $slug . '_admin',
                'email' => $slug . '@zoomnearby.com',
                'password' => bcrypt('demo1234'),
                'role' => User::ROLE_ADMINISTRATOR,
                'status' => 'active',
                'is_demo' => true,
                'current_store_id' => $store->id,
            ]);

            (new DemoChatSeeder)->seedCompanyChat($company);
        }

        // Run promotional broadcasts seeding
        $seeder = new DemoChatSeeder;
        $ref = new \ReflectionMethod($seeder, 'seedPromotionalBroadcasts');
        $ref->setAccessible(true);
        $ref->invoke($seeder);
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed'));
        parent::tearDown();
    }

    public function test_all_demo_accounts_have_chat_enabled_and_seeded(): void
    {
        $demoSlugs = [
            'tenant-demo-retail',
            'tenant-demo-restaurant',
            'tenant-demo-pharmacy',
            'tenant-demo-repairs',
            'tenant-demo-salon',
            'tenant-demo-all-enterprise',
        ];

        foreach ($demoSlugs as $slug) {
            $company = Company::query()->withoutGlobalScopes()->where('slug', $slug)->first();
            $this->assertNotNull($company, "Demo company with slug {$slug} must exist.");

            // Verify company has chat module enabled
            $this->assertTrue($company->hasModule('chat'), "Company {$slug} must have chat module enabled.");
            $this->assertTrue($company->hasChatAccess(), "Company {$slug} must have chat access.");

            // Verify company has at least 2 staff members
            $users = User::query()->withoutGlobalScopes()->where('company_id', $company->id)->get();
            $this->assertGreaterThanOrEqual(2, $users->count(), "Company {$slug} must have at least 2 staff members.");

            // Verify admin can access /tenant/chat via web
            $admin = $users->firstWhere('role', User::ROLE_ADMINISTRATOR) ?? $users->first();
            $response = $this->actingAs($admin)->get('/tenant/chat');
            $response->assertStatus(200);
            $response->assertSee('Team Directory');
            $response->assertSee('Live Chat Support');

            // Verify staff list API returns staff and presence
            $apiStaffResponse = $this->actingAs($admin)->getJson('/api/chat/staff');
            $apiStaffResponse->assertStatus(200);
            $apiStaffResponse->assertJsonStructure([
                'success',
                'staff' => [
                    '*' => ['id', 'name', 'role', 'avatar', 'is_online', 'status_label', 'conversation_id'],
                ],
            ]);
            $staffList = $apiStaffResponse->json('staff');
            $this->assertNotEmpty($staffList, "Staff list for {$slug} should not be empty.");

            // Verify at least one staff member is online
            $hasOnline = collect($staffList)->contains('is_online', true);
            $this->assertTrue($hasOnline, "At least one staff member in {$slug} should be marked online.");

            // Verify conversation messages API
            $firstStaff = $staffList[0];
            $conversationId = $firstStaff['conversation_id'];
            if ($conversationId) {
                $messagesResponse = $this->actingAs($admin)->getJson("/api/chat/conversations/{$conversationId}/messages");
                $messagesResponse->assertStatus(200);
                $messagesResponse->assertJsonStructure([
                    'success',
                    'messages',
                    'active_promotion',
                    'ai_suggestions',
                ]);
                $this->assertNotEmpty($messagesResponse->json('messages'), "Conversation {$conversationId} should have seeded messages.");
                $this->assertNotEmpty($messagesResponse->json('ai_suggestions'), "Conversation {$conversationId} should have AI suggestions.");
            }
        }
    }

    public function test_promotional_broadcasts_surface_in_demo_chat(): void
    {
        $broadcast = PromotionalBroadcast::where('is_active', true)->first();
        $this->assertNotNull($broadcast, "At least one active promotional broadcast must exist.");

        $retailAdmin = User::query()->withoutGlobalScopes()->where('email', 'tenant-demo-retail@zoomnearby.com')->first();
        $response = $this->actingAs($retailAdmin)->getJson('/api/chat/promotions');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
        $this->assertNotEmpty($response->json('broadcasts'));
    }

    public function test_staff_can_send_message_in_demo_account(): void
    {
        $retailAdmin = User::query()->withoutGlobalScopes()->where('email', 'tenant-demo-retail@zoomnearby.com')->first();
        $staff = User::query()->withoutGlobalScopes()->where('company_id', $retailAdmin->company_id)->where('id', '!=', $retailAdmin->id)->first();

        $conversation = ChatConversation::where('tenant_id', $retailAdmin->company_id)
            ->where('type', 'direct')
            ->whereHas('participants', fn ($q) => $q->where('user_id', $staff->id))
            ->first();

        $this->assertNotNull($conversation);

        $sendResponse = $this->actingAs($retailAdmin)->postJson('/api/chat/messages', [
            'conversation_id' => $conversation->id,
            'message' => 'Override approved for customer #1042.',
        ]);

        $sendResponse->assertStatus(200);
        $sendResponse->assertJson([
            'success' => true,
            'message' => [
                'message' => 'Override approved for customer #1042.',
            ],
        ]);

        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $retailAdmin->id,
            'message' => 'Override approved for customer #1042.',
        ]);
    }
}
