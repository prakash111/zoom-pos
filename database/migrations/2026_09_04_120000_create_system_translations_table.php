<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_translations', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 10)->index();
            $table->string('module', 100)->default('core')->index();
            $table->string('key', 191);
            $table->text('value')->nullable();
            $table->unsignedBigInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['locale', 'key'], 'system_translations_locale_key_unique');
        });

        if (Schema::hasTable('sdui_modules') && ! Schema::hasColumn('sdui_modules', 'translation_keys')) {
            Schema::table('sdui_modules', function (Blueprint $table) {
                $table->json('translation_keys')->nullable()->after('navigation');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sdui_modules') && Schema::hasColumn('sdui_modules', 'translation_keys')) {
            Schema::table('sdui_modules', function (Blueprint $table) {
                $table->dropColumn('translation_keys');
            });
        }

        Schema::dropIfExists('system_translations');
    }
};
