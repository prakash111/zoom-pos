<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'favicon')) {
                $table->string('favicon')->nullable()->after('logo');
            }
            if (! Schema::hasColumn('companies', 'primary_color')) {
                $table->string('primary_color', 20)->default('#2d7a58')->after('favicon');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['favicon', 'primary_color']);
        });
    }
};
