<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Extend Promotional Broadcasts to support multi-tenant targeting & Tenant Announcements
        if (Schema::hasTable('promotional_broadcasts')) {
            Schema::table('promotional_broadcasts', function (Blueprint $table) {
                if (!Schema::hasColumn('promotional_broadcasts', 'sender_id')) {
                    $table->string('sender_id', 255)->nullable()->after('id');
                }
                if (!Schema::hasColumn('promotional_broadcasts', 'tenant_id')) {
                    $table->string('tenant_id', 255)->nullable()->index()->after('sender_id'); // NULL if Super Admin
                }
                if (!Schema::hasColumn('promotional_broadcasts', 'target_type')) {
                    $table->enum('target_type', ['all_tenants', 'selected_tenants', 'all_staff', 'selected_staff'])
                        ->default('all_tenants')
                        ->after('tenant_id');
                }
                if (!Schema::hasColumn('promotional_broadcasts', 'target_ids')) {
                    $table->json('target_ids')->nullable()->after('target_type'); // Array of tenant_ids or user_ids
                }
            });
        }

        // 2. Track Dismissed/Read Announcements per User
        if (!Schema::hasTable('broadcast_dismissals')) {
            Schema::create('broadcast_dismissals', function (Blueprint $table) {
                $table->id();
                $table->string('user_id', 255)->index();
                $table->foreignId('broadcast_id')->constrained('promotional_broadcasts')->cascadeOnDelete();
                $table->timestamp('dismissed_at')->useCurrent();

                $table->unique(['user_id', 'broadcast_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_dismissals');
        if (Schema::hasTable('promotional_broadcasts')) {
            Schema::table('promotional_broadcasts', function (Blueprint $table) {
                $columns = ['sender_id', 'tenant_id', 'target_type', 'target_ids'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('promotional_broadcasts', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
