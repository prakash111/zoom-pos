<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sdui_modules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 80)->unique();
            $table->text('description')->nullable();
            $table->string('icon', 80)->default('widgets');
            $table->string('layout_type', 80)->default('standard_grid');
            $table->json('features')->nullable();
            $table->json('routes')->nullable();
            $table->json('navigation')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('registration_allowed')->default(false)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('sdui_screens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sdui_module_id')->nullable()->constrained('sdui_modules')->cascadeOnDelete();
            $table->string('key', 120)->unique();
            $table->string('title', 160);
            $table->string('permission', 120)->nullable();
            $table->json('schema');
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sdui_screens');
        Schema::dropIfExists('sdui_modules');
    }
};
