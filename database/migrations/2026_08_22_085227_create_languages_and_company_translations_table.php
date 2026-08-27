<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('languages')) {
            Schema::create('languages', function (Blueprint $table) {
                $table->id();
                $table->string('code', 10)->unique();
                $table->string('name', 100);
                $table->string('native_name', 100)->nullable();
                $table->string('flag', 20)->nullable();
                $table->string('direction', 5)->default('ltr'); // ltr | rtl
                $table->boolean('is_active')->default(true);
                $table->boolean('is_default')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('company_translations')) {
            Schema::create('company_translations', function (Blueprint $table) {
                $table->id();
                $table->string('company_id', 36)->index();
                $table->string('locale', 10)->index();
                $table->string('key', 191);
                $table->text('value')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'locale', 'key'], 'comp_loc_key_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('company_translations');
        Schema::dropIfExists('languages');
    }
};
