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
        Schema::table('kitchen_tickets', function (Blueprint $table) {
            $table->unsignedInteger('prep_minutes')->nullable()->after('kitchen_notes');
            $table->timestamp('target_completion_at')->nullable()->after('prep_minutes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kitchen_tickets', function (Blueprint $table) {
            $table->dropColumn(['prep_minutes', 'target_completion_at']);
        });
    }
};
