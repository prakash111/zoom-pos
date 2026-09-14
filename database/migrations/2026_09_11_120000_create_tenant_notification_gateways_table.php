<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenant_notification_gateways')) {
            Schema::create('tenant_notification_gateways', function (Blueprint $table) {
                $table->id();
                $table->string('company_id');
                $table->string('tenant_id')->nullable()->index();
                $table->string('channel', 50)->index(); // whatsapp, sms, email, custom_webhook
                $table->string('provider', 50)->default('default'); // meta_cloud_api, twilio, msg91, generic_http, smtp, webhook
                $table->boolean('is_enabled')->default(false)->index();
                $table->longText('credentials')->nullable(); // encrypted:array
                $table->timestamps();

                $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
                $table->unique(['company_id', 'channel']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_notification_gateways');
    }
};
