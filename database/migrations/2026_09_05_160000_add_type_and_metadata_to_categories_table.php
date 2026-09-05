<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            if (! Schema::hasColumn('categories', 'type')) {
                $table->string('type', 50)->nullable()->default('retail')->after('name')->index();
            }
            if (! Schema::hasColumn('categories', 'metadata')) {
                $table->json('metadata')->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            if (Schema::hasColumn('categories', 'type')) {
                $table->dropColumn('type');
            }
            if (Schema::hasColumn('categories', 'metadata')) {
                $table->dropColumn('metadata');
            }
        });
    }
};
