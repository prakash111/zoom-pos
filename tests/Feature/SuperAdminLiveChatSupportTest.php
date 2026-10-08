<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\PlatformAdmin;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Chat\Models\ChatConversation;
use Modules\Chat\Models\ChatMessage;
use Modules\Chat\Models\ChatParticipant;
use Tests\TestCase;

class SuperAdminLiveChatSupportTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $staffUser;
    protected PlatformAdmin $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        file_put_contents(storage_path('installed'), '{}');

        \App\Models\Plan::create([
            'name'           => 'Enterprise Pro',
            'display_name'   => 'Enterprise Pro Plan',
            'slug'           => 'enterprise-pro',
            'price_monthly'  => 99.00,
            'is_active'      => true,
        ]);

        $this->company = Company::create([
            'name'              => 'Acme Retail Technologies',
            'trade_name'        => 'Acme Retail',
            'activation_key'    => 'ZK-ACME-SUPPORT-999',
            'unique_account_id' => 'acme_live_desk',
            'slug'              => 'acmeretail',
            'email'             => 'contact@acmeretail.com',
            'phone'             => '+1 555 123 4567',
            'status'            => 'active',
            'plan_name'         => 'Enterprise Pro',
            'registered_at'     => now()->subMonths(2),
            'expires_at'        => now()->addMonths(10),
        ]);

        Subscription::create([
            'company_id' => $this->company->id,
            'plan_name'  => 'Enterprise Pro',
            'status'     => 'active',
            'started_at' => now()->subMonths(2),
            'expires_at' => now()->addMonths(10),
            'auto_renew' => true,
        ]);

        $this->staffUser = User::create([
            'company_id'        => $this->company->id,
            'name'              => 'Sarah Jenkins',
            'login'             => 'sarah_jenkins',
            'email'             => 'sarah@acmeretail.com',
            'phone'             => '+1 555 987 6543',
            'role'              => 'manager',
            'password'          => 'password123',
            'status'            => 'approved',
            'email_verified_at' => now(),
        ]);

        $this->superAdmin = PlatformAdmin::create([
            'id'       => 'padm_testadmin',
            'name'     => 'Global Super Admin',
            'email'    => 'admin@platform.test',
            'password' => bcrypt('adminsecret'),
            'role'     => 'super_admin',
            'status'   => 'active',
        ]);

        (new \App\Providers\ModuleServiceProvider($this->app))->bootModule('Chat');
    }

    public function test_registered_staff_can_initiate_support_conversation_associated_with_tenant(): void
    {
        $this->actingAs($this->staffUser, 'web');

        $response = $this->postJson('/api/tenant/chat/conversations', [
            'type' => 'support',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $convId = $response->json('conversation_id');
        $this->assertNotNull($convId);

        $conversation = ChatConversation::find($convId);
        $this->assertNotNull($conversation);
        $this->assertEquals('support', $conversation->type);
        $this->assertEquals($this->company->id, $conversation->tenant_id);

        // Verify participant is the registered staff user
        $this->assertDatabaseHas('chat_participants', [
            'conversation_id' => $convId,
            'user_id'         => $this->staffUser->id,
        ]);
    }

    public function test_guest_cannot_initiate_support_conversation(): void
    {
        $response = $this->postJson('/api/tenant/chat/conversations', [
            'type' => 'support',
        ]);

        $response->assertUnauthorized();
    }

    public function test_staff_can_send_message_and_attachment_in_support_conversation(): void
    {
        Storage::fake('public');
        $this->actingAs($this->staffUser, 'web');

        $conversation = ChatConversation::create([
            'tenant_id'       => $this->company->id,
            'type'            => 'support',
            'title'           => 'Help & Support Desk',
            'last_message_at' => now(),
        ]);

        ChatParticipant::create([
            'conversation_id' => $conversation->id,
            'user_id'         => $this->staffUser->id,
            'last_read_at'    => now(),
        ]);

        $file = UploadedFile::fake()->create('error_screenshot.png', 150, 'image/png');

        $response = $this->postJson('/api/tenant/chat/messages', [
            'conversation_id' => $conversation->id,
            'message'         => 'We need assistance with payment gateway synchronization 🚀',
            'attachment'      => $file,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $conversation->id,
            'sender_id'       => $this->staffUser->id,
            'sender_type'     => 'user',
            'attachment_type' => 'image',
            'attachment_name' => 'error_screenshot.png',
        ]);
    }

    public function test_superadmin_can_view_support_conversations_with_tenant_information(): void
    {
        $conversation = ChatConversation::create([
            'tenant_id'       => $this->company->id,
            'type'            => 'support',
            'title'           => 'Help & Support — Acme Retail (Sarah Jenkins)',
            'last_message_at' => now(),
        ]);

        ChatParticipant::create([
            'conversation_id' => $conversation->id,
            'user_id'         => $this->staffUser->id,
        ]);

        ChatMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id'       => $this->staffUser->id,
            'sender_type'     => 'user',
            'message'         => 'Can you check our subscription renewal date?',
        ]);

        $component = Livewire::actingAs($this->superAdmin, 'platform_web')
            ->test(\App\Livewire\SuperAdmin\LiveChatSupport\Index::class, ['id' => $conversation->id]);

        $component->assertOk()
            ->assertSee('Live Chat Support')
            ->assertSee('Acme Retail Technologies')
            ->assertSee('contact@acmeretail.com')
            ->assertSee('+1 555 123 4567')
            ->assertSee('Enterprise Pro')
            ->assertSee('Sarah Jenkins')
            ->assertSee('Can you check our subscription renewal date?');

        // Confirm NO "+" button to manually create a new chat exists
        $component->assertDontSee('New Chat')
            ->assertDontSee('+ New');
    }

    public function test_superadmin_can_reply_to_tenant_support_conversation(): void
    {
        $conversation = ChatConversation::create([
            'tenant_id'       => $this->company->id,
            'type'            => 'support',
            'title'           => 'Help & Support Desk',
            'last_message_at' => now(),
        ]);

        ChatParticipant::create([
            'conversation_id' => $conversation->id,
            'user_id'         => $this->staffUser->id,
        ]);

        Livewire::actingAs($this->superAdmin, 'platform_web')
            ->test(\App\Livewire\SuperAdmin\LiveChatSupport\Index::class, ['id' => $conversation->id])
            ->set('replyMessage', 'Hello Sarah, your subscription is active until next year! 👍')
            ->call('sendReply')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $conversation->id,
            'sender_id'       => $this->superAdmin->id,
            'sender_type'     => 'super_admin',
            'message'         => 'Hello Sarah, your subscription is active until next year! 👍',
        ]);
    }

    public function test_superadmin_sidebar_layout_contains_live_chat_support_menu(): void
    {
        $response = $this->actingAs($this->superAdmin, 'platform_web')
            ->get('/superadmin/live-chat-support');

        $response->assertOk();
        $response->assertSee('Web Inquiries &amp; Form', false);
        $response->assertSee('Live Chat Support', false);
    }

    public function test_superadmin_live_chat_support_shows_ai_suggestions_and_autocorrect(): void
    {
        $conversation = ChatConversation::create([
            'tenant_id'       => $this->company->id,
            'type'            => 'support',
            'title'           => 'Help & Support Desk',
            'last_message_at' => now(),
        ]);

        ChatParticipant::create([
            'conversation_id' => $conversation->id,
            'user_id'         => $this->staffUser->id,
        ]);

        ChatMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id'       => $this->staffUser->id,
            'sender_type'     => 'user',
            'message'         => 'We need help resetting our store register printer.',
        ]);

        $test = Livewire::actingAs($this->superAdmin, 'platform_web')
            ->test(\App\Livewire\SuperAdmin\LiveChatSupport\Index::class, ['id' => $conversation->id]);

        $test->assertOk()
            ->assertSee('AI Suggestions')
            ->assertSet('activeConversationId', $conversation->id);

        $suggestions = $test->get('aiSuggestions');
        $this->assertNotEmpty($suggestions);

        // Test using AI suggestion
        $chosen = $suggestions[0];
        $test->call('useAiSuggestion', $chosen)
            ->assertSet('replyMessage', $chosen);

        // Test AI polish & autocorrect
        $test->set('replyMessage', 'we are fixin register right now')
            ->call('autoCorrectMessage')
            ->assertSet('aiToast', 'Polished with AI ✨');

        $this->assertNotEmpty($test->get('replyMessage'));
    }
}

