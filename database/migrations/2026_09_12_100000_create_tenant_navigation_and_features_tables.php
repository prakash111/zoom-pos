<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenant_navigation_settings')) {
            Schema::create('tenant_navigation_settings', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id', 64)->index();
                $table->string('module_key', 60)->index();
                $table->string('group', 60)->nullable();
                $table->string('label', 100)->nullable();
                $table->string('icon', 60)->nullable();
                $table->string('route', 100)->nullable();
                $table->boolean('is_enabled')->default(true);
                $table->boolean('is_visible')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['tenant_id', 'module_key']);
            });
        }

        if (! Schema::hasTable('tenant_features')) {
            Schema::create('tenant_features', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id', 64)->index();
                $table->string('feature_key', 60)->index();
                $table->boolean('is_enabled')->default(true);
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'feature_key']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_features');
        Schema::dropIfExists('tenant_navigation_settings');
    }
};
