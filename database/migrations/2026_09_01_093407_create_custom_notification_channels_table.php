<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-configurable outbound webhooks for invoices, quotations, and due
 * payment reminders (Settings > Notifications > Custom Notification
 * Channels). Dispatch attempts are logged through the existing
 * message_queue table (type = 'webhook'), not a separate queue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_notification_channels', function (Blueprint $table) {
            $table->id();
            $table->string('company_id');
            $table->string('name');
            $table->string('url', 500);
            $table->string('method', 10)->default('POST'); // POST | GET
            $table->json('headers')->nullable();
            $table->string('auth_type', 20)->default('none'); // none | bearer | api_key
            $table->text('auth_value')->nullable();
            $table->text('payload_template')->nullable();
            $table->json('event_types')->nullable(); // invoice | quotation | due_reminder
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->index(['company_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_notification_channels');
    }
};
