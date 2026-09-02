<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('custom_notification_channels', function (Blueprint $table) {
            // Preset icon key (e.g. "slack", "telegram", "webhook") or an
            // absolute/storage URL to an uploaded custom icon image.
            $table->string('icon')->nullable()->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('custom_notification_channels', function (Blueprint $table) {
            $table->dropColumn('icon');
        });
    }
};
