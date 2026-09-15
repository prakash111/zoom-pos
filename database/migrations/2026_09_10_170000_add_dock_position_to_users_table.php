<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Server-side home for the per-user dockable navigation position so it
     * survives a cleared localStorage / a different browser or device. The
     * live in-page state still lives in localStorage (anti-flicker); this is
     * the cross-device seed the layout server-renders on first paint.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('dock_position', 16)->nullable()->after('shift');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('dock_position');
        });
    }
};
