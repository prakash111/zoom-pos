<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('custom_notification_channels') && ! Schema::hasColumn('custom_notification_channels', 'payload_format')) {
            Schema::table('custom_notification_channels', function (Blueprint $table) {
                $table->string('payload_format', 20)->default('json')->after('method');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('custom_notification_channels') && Schema::hasColumn('custom_notification_channels', 'payload_format')) {
            Schema::table('custom_notification_channels', function (Blueprint $table) {
                $table->dropColumn('payload_format');
            });
        }
    }
};
