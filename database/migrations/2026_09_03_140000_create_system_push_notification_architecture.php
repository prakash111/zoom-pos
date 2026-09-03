<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_notification_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->string('fcm_project_id')->nullable();
            $table->longText('fcm_service_account_json')->nullable();
            $table->text('fcm_server_key')->nullable();
            $table->text('android_api_key')->nullable();
            $table->string('android_app_id')->nullable();
            $table->string('messaging_sender_id')->nullable();
            $table->string('order_channel_id')->default('delayed_orders_alarm');
            $table->string('order_channel_name')->default('Delayed order alarms');
            $table->string('order_sound')->default('alarm');
            $table->string('invoice_channel_id')->default('due_invoice_reminders');
            $table->string('invoice_channel_name')->default('Due invoice reminders');
            $table->string('invoice_sound')->default('alarm');
            $table->unsignedSmallInteger('alarm_repeat_seconds')->default(60);
            $table->timestamps();
        });

        Schema::create('push_devices', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('company_id');
            $table->string('user_id')->nullable();
            $table->string('token', 512)->unique();
            $table->string('platform', 30)->default('android');
            $table->string('device_name')->nullable();
            $table->string('app_version', 50)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['company_id', 'revoked_at']);
        });

        Schema::table('kitchen_tickets', function (Blueprint $table) {
            $table->timestamp('sent_to_kitchen_at')->nullable()->after('kitchen_notes');
            $table->unsignedSmallInteger('intimation_minutes')->default(0)->after('prep_minutes');
            $table->timestamp('alarm_at')->nullable()->after('target_completion_at');
            $table->timestamp('alarm_sent_at')->nullable()->after('alarm_at');
            $table->timestamp('alarm_dismissed_at')->nullable()->after('alarm_sent_at');
            $table->index(['company_id', 'alarm_at', 'alarm_sent_at'], 'kot_alarm_due_idx');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->timestamp('due_reminder_at')->nullable()->after('due_date');
            $table->timestamp('due_reminder_sent_at')->nullable()->after('due_reminder_at');
            $table->timestamp('due_reminder_dismissed_at')->nullable()->after('due_reminder_sent_at');
            $table->index(['company_id', 'due_reminder_at', 'due_reminder_sent_at'], 'sale_due_reminder_idx');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex('sale_due_reminder_idx');
            $table->dropColumn(['due_reminder_at', 'due_reminder_sent_at', 'due_reminder_dismissed_at']);
        });

        Schema::table('kitchen_tickets', function (Blueprint $table) {
            $table->dropIndex('kot_alarm_due_idx');
            $table->dropColumn([
                'sent_to_kitchen_at', 'intimation_minutes', 'alarm_at',
                'alarm_sent_at', 'alarm_dismissed_at',
            ]);
        });

        Schema::dropIfExists('push_devices');
        Schema::dropIfExists('push_notification_settings');
    }
};
