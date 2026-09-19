<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('published_catalogs', function (Blueprint $table) {
            if (! Schema::hasColumn('published_catalogs', 'description')) {
                $table->text('description')->nullable()->after('title');
            }
            if (! Schema::hasColumn('published_catalogs', 'meta')) {
                $table->json('meta')->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('published_catalogs', function (Blueprint $table) {
            if (Schema::hasColumn('published_catalogs', 'meta')) {
                $table->dropColumn('meta');
            }
            if (Schema::hasColumn('published_catalogs', 'description')) {
                $table->dropColumn('description');
            }
        });
    }
};
