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
        // 1. Add review settings to companies table if not present
        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'enable_product_reviews')) {
                $table->boolean('enable_product_reviews')->default(true)->after('store_banner_is_active');
            }
            if (! Schema::hasColumn('companies', 'require_review_approval')) {
                $table->boolean('require_review_approval')->default(false)->after('enable_product_reviews');
            }
        });

        // 2. Create product_reviews table
        if (! Schema::hasTable('product_reviews')) {
            Schema::create('product_reviews', function (Blueprint $table) {
                $table->id();
                $table->string('company_id', 64)->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->unsignedBigInteger('customer_id')->nullable()->index();
                $table->string('customer_name', 150);
                $table->string('customer_email', 150)->nullable();
                $table->unsignedTinyInteger('rating')->default(5); // 1 - 5 stars
                $table->string('title', 200)->nullable();
                $table->text('comment');
                $table->boolean('is_approved')->default(true)->index();
                $table->boolean('is_verified_purchase')->default(false);
                $table->timestamps();

                $table->index(['company_id', 'product_id', 'is_approved']);
                $table->index(['company_id', 'customer_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_reviews');

        Schema::table('companies', function (Blueprint $table) {
            if (Schema::hasColumn('companies', 'require_review_approval')) {
                $table->dropColumn('require_review_approval');
            }
            if (Schema::hasColumn('companies', 'enable_product_reviews')) {
                $table->dropColumn('enable_product_reviews');
            }
        });
    }
};
