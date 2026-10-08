<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Chat Conversations (1-on-1 or Support Channels)
        if (!Schema::hasTable('chat_conversations')) {
            Schema::create('chat_conversations', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id', 255)->nullable()->index();
                $table->unsignedBigInteger('store_id')->nullable()->index();
                $table->enum('type', ['direct', 'support', 'broadcast'])->default('direct');
                $table->string('title')->nullable();
                $table->timestamp('last_message_at')->nullable();
                $table->timestamps();
            });
        }

        // 2. Conversation Participants
        if (!Schema::hasTable('chat_participants')) {
            Schema::create('chat_participants', function (Blueprint $table) {
                $table->id();
                $table->foreignId('conversation_id')->constrained('chat_conversations')->cascadeOnDelete();
                $table->string('user_id', 255)->index();
                $table->timestamp('last_read_at')->nullable();
                $table->timestamps();

                $table->unique(['conversation_id', 'user_id']);
            });
        }

        // 3. Chat Messages
        if (!Schema::hasTable('chat_messages')) {
            Schema::create('chat_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('conversation_id')->constrained('chat_conversations')->cascadeOnDelete();
                $table->string('sender_id', 255)->nullable()->index(); // null if system broadcast
                $table->enum('sender_type', ['user', 'super_admin', 'system'])->default('user');
                $table->text('message')->nullable();
                $table->string('attachment_type')->nullable(); // image, pdf, document
                $table->string('attachment_url')->nullable();
                $table->string('attachment_name')->nullable();
                $table->boolean('is_promotional')->default(false);
                $table->json('metadata')->nullable(); // CTA links, button payloads
                $table->timestamps();
            });
        }

        // 4. Super Admin Promotional Announcements
        if (!Schema::hasTable('promotional_broadcasts')) {
            Schema::create('promotional_broadcasts', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->text('message');
                $table->string('banner_image_url')->nullable();
                $table->string('pdf_url')->nullable();
                $table->string('cta_label')->nullable();
                $table->string('cta_url')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }

        // 5. User Online Presence & Settings Extension
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'last_seen_at')) {
                    $table->timestamp('last_seen_at')->nullable()->after('updated_at');
                }
            });
        }

        if (Schema::hasTable('tenant_settings')) {
            Schema::table('tenant_settings', function (Blueprint $table) {
                if (!Schema::hasColumn('tenant_settings', 'enable_chat_promotions')) {
                    $table->boolean('enable_chat_promotions')->default(true);
                }
                if (!Schema::hasColumn('tenant_settings', 'enable_ai_reply')) {
                    $table->boolean('enable_ai_reply')->default(true);
                }
            });
        }

        if (Schema::hasTable('sdui_modules')) {
            \Illuminate\Support\Facades\DB::table('sdui_modules')->updateOrInsert(
                ['slug' => 'chat'],
                [
                    'name' => 'Unified Internal Staff Chat & Support',
                    'version' => '1.0.0',
                    'author' => 'ZoomNearby',
                    'source_type' => 'package',
                    'package_path' => 'Chat',
                    'description' => 'Internal staff messaging, AI smart reply suggestions, Super Admin promotional broadcasts, and Help & Support live chat.',
                    'icon' => 'chat',
                    'layout_type' => 'standard_grid',
                    'is_active' => true,
                    'type' => 'extension',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_participants');
        Schema::dropIfExists('chat_conversations');
        Schema::dropIfExists('promotional_broadcasts');
    }
};
