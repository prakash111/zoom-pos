<?php

namespace Tests\Feature;

use App\Http\Middleware\UpdateUserPresence;
use App\Models\Company;
use App\Models\PlatformAdmin;
use App\Models\TenantSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Modules\Chat\Models\ChatConversation;
use Modules\Chat\Models\ChatMessage;
use Modules\Chat\Models\ChatParticipant;
use Modules\Chat\Models\PromotionalBroadcast;
use Modules\Chat\Services\AiChatSuggestionService;
use Tests\TestCase;

class UnifiedStaffChatAndSupportTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        file_put_contents(storage_path('installed'), '{}');

        $this->company = Company::create([
            'name' => 'Acme Store',
            'activation_key' => 'ZK-ACME-1234',
            'unique_account_id' => 'acme_123',
            'status' => 'active',
            'expires_at' => now()->addMonth(),
        ]);

        $this->user = User::create([
            'company_id' => $this->company->id,
            'name' => 'Staff Main',
            'login' => 'staff_main',
            'email' => 'staff_main@example.com',
            'password' => 'secret123',
            'status' => 'approved',
            'email_verified_at' => now(),
        ]);

        (new \App\Providers\ModuleServiceProvider($this->app))->bootModule('Chat');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function test_user_presence_middleware_and_online_status_attribute(): void
    {
        $this->actingAs($this->user, 'web');

        $middleware = new UpdateUserPresence();
        $request = Request::create('/test-presence', 'GET');
        $request->setUserResolver(fn () => $this->user);

        $middleware->handle($request, function ($req) {
            return response('OK');
        });

        $this->assertTrue(Cache::has('user-is-online-' . $this->user->id));
        $this->assertTrue($this->user->is_online);
    }

    public function test_ai_smart_reply_generator_returns_three_chips(): void
    {
        $service = new AiChatSuggestionService();
        $replies = $service->getQuickReplies('Can you check if the item is in stock?');

        $this->assertIsArray($replies);
        $this->assertCount(3, $replies);
        foreach ($replies as $reply) {
            $this->assertIsString($reply);
            $this->assertNotEmpty($reply);
        }
    }

    public function test_staff_directory_list_with_presence_and_support_desk(): void
    {
        $peerUser = User::create([
            'company_id' => $this->company->id,
            'name' => 'Peer Staff',
            'login' => 'peer_staff',
            'email' => 'peer@example.com',
            'password' => 'secret123',
            'status' => 'approved',
        ]);

        // Put peerUser online in cache
        Cache::put('user-is-online-' . $peerUser->id, true, now()->addMinutes(2));

        $this->actingAs($this->user, 'web');

        // 1. Staff directory endpoint
        $staffResponse = $this->getJson('/api/chat/staff');
        $staffResponse->assertStatus(200);
        $staffResponse->assertJson(['success' => true]);

        $staffData = $staffResponse->json('staff');
        $this->assertNotEmpty($staffData);

        $peerItem = collect($staffData)->firstWhere('id', $peerUser->id);
        $this->assertNotNull($peerItem);
        $this->assertTrue($peerItem['is_online']);
        $this->assertEquals('Online', $peerItem['status_label']);

        // 2. Create Live Support conversation
        $supportResponse = $this->postJson('/api/chat/conversations', [
            'type' => 'support',
        ]);
        $supportResponse->assertStatus(200);
        $supportResponse->assertJson(['success' => true]);
        $this->assertNotNull($supportResponse->json('conversation_id'));
    }

    public function test_chat_conversation_messages_and_promotions_and_ai_replies(): void
    {
        $otherUser = User::create([
            'company_id' => $this->company->id,
            'name' => 'Peer Staff',
            'login' => 'peer_staff',
            'email' => 'peer@example.com',
            'password' => 'secret123',
            'status' => 'approved',
        ]);

        $conversation = ChatConversation::create([
            'tenant_id' => $this->company->id,
            'type' => 'direct',
            'title' => 'Test Direct Chat',
            'last_message_at' => now(),
        ]);

        ChatParticipant::create([
            'conversation_id' => $conversation->id,
            'user_id' => $this->user->id,
            'last_read_at' => now(),
        ]);

        ChatParticipant::create([
            'conversation_id' => $conversation->id,
            'user_id' => $otherUser->id,
            'last_read_at' => null,
        ]);

        // Incoming message from counterpart
        ChatMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $otherUser->id,
            'sender_type' => 'user',
            'message' => 'Are we ready to process order #1002?',
        ]);

        // Create an active promotional broadcast
        PromotionalBroadcast::create([
            'title' => 'Quarterly POS Upgrade Promo',
            'message' => 'Check out the new features and PDF brochure!',
            'banner_image_url' => 'https://example.com/banner.png',
            'pdf_url' => 'https://example.com/brochure.pdf',
            'cta_label' => 'Learn More',
            'cta_url' => 'https://example.com/learn',
            'is_active' => true,
        ]);

        $this->actingAs($this->user, 'web');

        // Test ChatApiController getConversationMessages
        $response = $this->getJson("/api/chat/conversations/{$conversation->id}/messages");
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $json = $response->json();
        $this->assertNotEmpty($json['messages']);
        $this->assertNotNull($json['active_promotion']);
        $this->assertEquals('Quarterly POS Upgrade Promo', $json['active_promotion']['title']);
        $this->assertIsArray($json['ai_suggestions']);
        $this->assertCount(3, $json['ai_suggestions']);

        // Test sending message
        $sendResponse = $this->postJson('/api/chat/messages', [
            'conversation_id' => $conversation->id,
            'message' => 'Yes, order #1002 is ready for checkout.',
        ]);
        $sendResponse->assertStatus(200);
        $sendResponse->assertJson(['success' => true]);
        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $conversation->id,
            'message' => 'Yes, order #1002 is ready for checkout.',
        ]);

        // Test polling unread alerts
        $alertResponse = $this->getJson('/api/chat/alerts');
        $alertResponse->assertStatus(200);
        $alertResponse->assertJson(['success' => true]);
    }

    public function test_tenant_settings_can_toggle_promotions_and_ai_reply(): void
    {
        $this->actingAs($this->user, 'web');

        $response = $this->post('/tenant/settings/chat', [
            'enable_ai_reply' => '1',
            // enable_chat_promotions omitted -> false
        ]);
        $response->assertRedirect();

        $tenantId = $this->company->id;
        $this->assertTrue((bool) TenantSetting::get($tenantId, 'enable_ai_reply', false));
        $this->assertFalse((bool) TenantSetting::get($tenantId, 'enable_chat_promotions', true));
    }

    public function test_superadmin_promotional_broadcast_dispatch(): void
    {
        $admin = PlatformAdmin::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@zoomnearby.com',
            'password' => Hash::make('secret123'),
            'role' => 'super_admin',
            'status' => 'active',
        ]);

        $this->actingAs($admin, 'platform_web');

        $response = $this->post('/superadmin/broadcasts', [
            'title' => 'Global Summer Promotion',
            'message' => 'Special 20% discount on POS hardware terminals!',
            'banner_image_url' => 'https://saas.zoomnearby.com/promo.png',
            'pdf_url' => 'https://saas.zoomnearby.com/brochure.pdf',
            'cta_label' => 'Order Terminal',
            'cta_url' => 'https://saas.zoomnearby.com/order',
            'is_active' => '1',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('promotional_broadcasts', [
            'title' => 'Global Summer Promotion',
            'is_active' => true,
        ]);
    }

    public function test_chat_route_aliases_resolve_cleanly(): void
    {
        $this->actingAs($this->user, 'web');

        // Test double prefix (/api/v1/pos/api/chat/staff) from Dio concatenation
        $res1 = $this->getJson('/api/v1/pos/api/chat/staff');
        $res1->assertStatus(200);
        $res1->assertJson(['success' => true]);
        $this->assertIsArray($res1->json('components'));

        // Test standard POS client prefix (/api/v1/pos/chat/staff)
        $res2 = $this->getJson('/api/v1/pos/chat/staff');
        $res2->assertStatus(200);
        $res2->assertJson(['success' => true]);
        $this->assertIsArray($res2->json('components'));

        // Test root api prefix (/api/chat/staff)
        $res3 = $this->getJson('/api/chat/staff');
        $res3->assertStatus(200);
        $res3->assertJson(['success' => true]);
        $this->assertIsArray($res3->json('components'));

        // Test active promotions aliases
        $resPromo1 = $this->getJson('/api/v1/pos/chat/promotions');
        $resPromo1->assertStatus(200);
        $resPromo1->assertJson(['success' => true]);
        $this->assertIsArray($resPromo1->json('components'));

        $resPromo2 = $this->getJson('/api/v1/pos/api/chat/promotions');
        $resPromo2->assertStatus(200);
        $resPromo2->assertJson(['success' => true]);
        $this->assertIsArray($resPromo2->json('components'));
    }

    public function test_sdui_views_return_components_as_list(): void
    {
        $this->actingAs($this->user, 'web');

        // SDUI Live Staff Chat view
        $staffChatRes = $this->getJson('/api/tenant/chat/views/staff-chat');
        $staffChatRes->assertStatus(200);
        $staffChatRes->assertJson(['success' => true]);
        $staffChatData = $staffChatRes->json();
        $this->assertIsArray($staffChatData['components']);
        $this->assertNotEmpty($staffChatData['components']);
        $this->assertTrue(in_array($staffChatData['components'][0]['type'], ['header_banner', 'search_bar']));

        // SDUI Promotional Announcements view
        PromotionalBroadcast::create([
            'title' => 'Exclusive Merchant Offer',
            'message' => 'Zero transaction fees on UPI payments this month.',
            'is_active' => true,
        ]);

        $promoRes = $this->getJson('/api/tenant/chat/views/promotions');
        $promoRes->assertStatus(200);
        $promoRes->assertJson(['success' => true]);
        $promoData = $promoRes->json();
        $this->assertIsArray($promoData['components']);
        $this->assertNotEmpty($promoData['components']);
        $this->assertEquals('header_banner', $promoData['components'][0]['type']);
    }

    public function test_promotional_broadcast_dismissal_persistence_and_exclusion(): void
    {
        $this->actingAs($this->user, 'web');

        $promo = PromotionalBroadcast::create([
            'title'     => 'ZooM POS Feature Release',
            'message'   => 'Read the new guide.',
            'is_active' => true,
        ]);

        $conversation = ChatConversation::create([
            'tenant_id' => $this->company->id,
            'type'      => 'direct',
            'title'     => 'Direct Chat',
        ]);

        ChatParticipant::create([
            'conversation_id' => $conversation->id,
            'user_id'         => $this->user->id,
        ]);

        // 1. Initial messages fetch: promotion is present
        $res = $this->getJson("/api/v1/pos/chat/conversations/{$conversation->id}/messages");
        $res->assertStatus(200);
        $this->assertNotNull($res->json('active_promotion'));
        $this->assertEquals($promo->id, $res->json('active_promotion.id'));

        // 2. Dismiss the promotional announcement
        $dismissRes = $this->postJson("/api/v1/pos/chat/promotions/{$promo->id}/dismiss");
        $dismissRes->assertStatus(200);
        $dismissRes->assertJson(['success' => true]);

        // Verify dismissal row exists in DB
        $this->assertDatabaseHas('broadcast_dismissals', [
            'user_id'      => (string) $this->user->id,
            'broadcast_id' => $promo->id,
        ]);

        // 3. Re-fetch messages: dismissed promotion must NOT appear
        $recheckRes = $this->getJson("/api/v1/pos/chat/conversations/{$conversation->id}/messages");
        $recheckRes->assertStatus(200);
        $this->assertNull($recheckRes->json('active_promotion'));

        // 4. Also check SDUI promotions view: must exclude dismissed broadcast
        $viewRes = $this->getJson('/api/tenant/chat/views/promotions');
        $viewRes->assertStatus(200);
        $items = $viewRes->json('items');
        $this->assertEmpty($items);
    }

    public function test_superadmin_targeted_broadcasts_for_tenants(): void
    {
        $companyB = Company::create([
            'name'              => 'Beta Store',
            'activation_key'    => 'ZK-BETA-5678',
            'unique_account_id' => 'beta_store',
        ]);

        $userB = User::create([
            'company_id' => $companyB->id,
            'name'       => 'User Beta',
            'login'      => 'user_beta',
            'email'      => 'beta@example.com',
            'password'   => 'secret123',
            'status'     => 'approved',
        ]);

        // Targeted announcement for Company B only
        $targetedPromo = PromotionalBroadcast::create([
            'title'       => 'Beta Store Exclusive Announcement',
            'message'     => 'Targeted only to Beta Store',
            'target_type' => 'selected_tenants',
            'target_ids'  => [(string) $companyB->id],
            'is_active'   => true,
        ]);

        // User A (from Acme Store) must NOT see the targeted promo
        $this->actingAs($this->user, 'web');
        $resA = $this->getJson('/api/v1/pos/chat/promotions');
        $resA->assertStatus(200);
        $promoIdsA = collect($resA->json('promotions'))->pluck('id')->all();
        $this->assertNotContains($targetedPromo->id, $promoIdsA);

        // User B (from Beta Store) MUST see the targeted promo
        $this->actingAs($userB, 'web');
        $resB = $this->getJson('/api/v1/pos/chat/promotions');
        $resB->assertStatus(200);
        $promoIdsB = collect($resB->json('promotions'))->pluck('id')->all();
        $this->assertContains($targetedPromo->id, $promoIdsB);
    }

    public function test_tenant_staff_announcements_form_and_dispatch(): void
    {
        $this->actingAs($this->user, 'web');

        // 1. Fetch Dynamic Form Schema
        $formRes = $this->getJson('/api/tenant/chat/forms/tenant-broadcast');
        $formRes->assertStatus(200);
        $formRes->assertJson(['success' => true]);
        $formData = $formRes->json();
        $this->assertIsArray($formData['components']);
        $this->assertIsArray($formData['fields']);
        $fieldNames = collect($formData['fields'])->pluck('name')->all();
        $this->assertContains('title', $fieldNames);
        $this->assertContains('message', $fieldNames);
        $this->assertContains('target_type', $fieldNames);
        $this->assertContains('staff_ids', $fieldNames);

        // 2. Dispatch Staff Announcement
        $dispatchRes = $this->postJson('/api/tenant/chat/staff-announcements', [
            'title'       => 'All Hands Team Meeting',
            'message'     => 'Store will close at 8 PM today for inventory count.',
            'target_type' => 'all_staff',
        ]);

        $dispatchRes->assertStatus(200);
        $dispatchRes->assertJson(['success' => true]);

        $this->assertDatabaseHas('promotional_broadcasts', [
            'title'       => 'All Hands Team Meeting',
            'tenant_id'   => (string) $this->company->id,
            'target_type' => 'all_staff',
            'is_active'   => 1,
        ]);
    }

    public function test_staff_notification_view_schema_contains_action_button_and_history_list(): void
    {
        $this->actingAs($this->user, 'web');

        // Create an existing announcement
        PromotionalBroadcast::create([
            'sender_id'   => (string) $this->user->id,
            'tenant_id'   => (string) $this->company->id,
            'title'       => 'Inventory Stocktake Tomorrow',
            'message'     => 'All floor staff report at 7 AM',
            'target_type' => 'all_staff',
            'is_active'   => true,
        ]);

        $res = $this->getJson('/api/tenant/chat/views/staff-notifications');
        $res->assertStatus(200);
        $res->assertJson(['success' => true]);

        $data = $res->json();
        $this->assertIsArray($data['components']);

        $compTypes = collect($data['components'])->pluck('type')->all();
        $this->assertContains('header_banner', $compTypes);
        $this->assertContains('action_button', $compTypes);
        $this->assertContains('section_title', $compTypes);
        $this->assertContains('list_container', $compTypes);

        $actionBtn = collect($data['components'])->firstWhere('type', 'action_button');
        $this->assertNotNull($actionBtn);
        $this->assertSame('+ Create New Staff Notification', $actionBtn['label']);
        $this->assertSame('action_sheet', $actionBtn['action']['type']);
        $this->assertSame('api/tenant/chat/forms/tenant-broadcast', $actionBtn['action']['target']);
    }

    public function test_pos_announcements_send_endpoint_dispatches_successfully(): void
    {
        $this->actingAs($this->user, 'web');

        $res = $this->postJson('/api/v1/pos/chat/announcements/send', [
            'title'       => 'Shift Handover Meeting',
            'message'     => 'Cash reconciliation at register 2',
            'target_type' => 'all_staff',
        ]);

        $res->assertStatus(200);
        $res->assertJson(['success' => true]);

        $this->assertDatabaseHas('promotional_broadcasts', [
            'title'     => 'Shift Handover Meeting',
            'tenant_id' => (string) $this->company->id,
        ]);
    }

    public function test_superadmin_promotional_broadcast_web_routes(): void
    {
        $admin = PlatformAdmin::create([
            'name'     => 'Super Admin',
            'email'    => 'admin@platform.test',
            'password' => Hash::make('secret123'),
        ]);

        $this->actingAs($admin, 'platform_web');

        // Index page
        $resIndex = $this->get('/superadmin/broadcasts');
        $resIndex->assertStatus(200);

        // Store broadcast
        $resStore = $this->post('/superadmin/broadcasts', [
            'title'       => 'Holiday System Maintenance Notice',
            'message'     => 'POS sync may experience 5-minute latency at midnight.',
            'target_type' => 'all_tenants',
        ]);
        $resStore->assertStatus(302);

        $this->assertDatabaseHas('promotional_broadcasts', [
            'title'       => 'Holiday System Maintenance Notice',
            'target_type' => 'all_tenants',
            'tenant_id'   => null,
        ]);
    }

    public function test_tenant_web_notifications_portal_routes(): void
    {
        $this->actingAs($this->user, 'web');
        app()->instance('tenant.company_id', $this->company->id);

        $controller = new \Modules\Chat\Http\Controllers\Web\TenantNotificationWebController();

        // 1. Index view
        $indexReq = Request::create('/tenant/staff-notifications', 'GET');
        $indexReq->setUserResolver(fn () => $this->user);
        $indexView = $controller->index($indexReq);
        $this->assertEquals('tenant.notifications.index', $indexView->name());
        $this->assertArrayHasKey('announcements', $indexView->getData());

        // 2. Create view
        $createReq = Request::create('/tenant/staff-notifications/create', 'GET');
        $createReq->setUserResolver(fn () => $this->user);
        $createView = $controller->create($createReq);
        $this->assertEquals('tenant.notifications.create', $createView->name());
        $this->assertArrayHasKey('staffList', $createView->getData());

        // 3. Dispatch announcement
        $storeReq = Request::create('/tenant/staff-notifications', 'POST', [
            'title'       => 'Shift Briefing Web',
            'message'     => 'Team meeting at 9:00 AM on floor.',
            'target_type' => 'all_staff',
        ]);
        $storeReq->setUserResolver(fn () => $this->user);
        $storeRes = $controller->store($storeReq);
        $this->assertTrue($storeRes->isRedirect(route('tenant.notifications.index')));

        $this->assertDatabaseHas('promotional_broadcasts', [
            'title'       => 'Shift Briefing Web',
            'tenant_id'   => (string) $this->company->id,
            'target_type' => 'all_staff',
            'is_active'   => 1,
        ]);

        $broadcast = PromotionalBroadcast::where('title', 'Shift Briefing Web')->first();
        $this->assertNotNull($broadcast);

        // 4. Destroy announcement
        $destroyReq = Request::create('/tenant/staff-notifications/' . $broadcast->id, 'DELETE');
        $destroyReq->setUserResolver(fn () => $this->user);
        $destroyRes = $controller->destroy($destroyReq, $broadcast->id);
        $this->assertTrue($destroyRes->isRedirect(route('tenant.notifications.index')));
        $this->assertDatabaseMissing('promotional_broadcasts', [
            'id' => $broadcast->id,
        ]);
    }
}
