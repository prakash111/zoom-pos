<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dismissed_notifications')) {
            Schema::create('dismissed_notifications', function (Blueprint $table) {
                $table->id();
                $table->string('company_id')->index();
                $table->string('notification_type', 50)->nullable()->index();
                $table->string('notification_id', 100)->index();
                $table->string('user_id')->nullable()->index();
                $table->timestamps();

                $table->unique(['company_id', 'notification_type', 'notification_id'], 'company_notif_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dismissed_notifications');
    }
};
