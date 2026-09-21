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
        if (! Schema::hasTable('tenant_custom_pages')) {
            Schema::create('tenant_custom_pages', function (Blueprint $table) {
                $table->id();
                $table->string('company_id')->index();
                $table->string('tenant_id')->nullable()->index();
                $table->string('title');
                $table->string('slug')->index();
                $table->longText('content')->nullable();
                $table->string('meta_title')->nullable();
                $table->text('meta_description')->nullable();
                $table->boolean('is_published')->default(true)->index();
                $table->timestamps();

                $table->unique(['company_id', 'slug'], 'tenant_pages_comp_slug_unique');
            });
        }

        if (! Schema::hasTable('tenant_store_menus')) {
            Schema::create('tenant_store_menus', function (Blueprint $table) {
                $table->id();
                $table->string('company_id')->index();
                $table->string('tenant_id')->nullable()->index();
                $table->string('location')->default('header_nav')->index(); // header_nav, footer_col_1, footer_col_2, footer_col_3
                $table->string('title');
                $table->string('type')->default('custom_url')->index(); // cms_page, category, anchor, custom_url
                $table->text('target_url')->nullable();
                $table->unsignedBigInteger('page_id')->nullable()->index();
                $table->unsignedBigInteger('category_id')->nullable()->index();
                $table->integer('sort_order')->default(0)->index();
                $table->boolean('is_visible')->default(true)->index();
                $table->string('target', 20)->default('_self');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_store_menus');
        Schema::dropIfExists('tenant_custom_pages');
    }
};
