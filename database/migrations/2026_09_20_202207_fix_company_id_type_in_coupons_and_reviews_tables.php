<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // Alter company_id to VARCHAR(64) on coupons, coupon_usages, and product_reviews
        if (Schema::hasTable('coupons')) {
            try {
                DB::statement("ALTER TABLE `coupons` MODIFY `company_id` VARCHAR(64) NOT NULL");
            } catch (\Throwable) {}
        }

        if (Schema::hasTable('coupon_usages')) {
            try {
                DB::statement("ALTER TABLE `coupon_usages` MODIFY `company_id` VARCHAR(64) NOT NULL");
            } catch (\Throwable) {}
        }

        if (Schema::hasTable('product_reviews')) {
            try {
                DB::statement("ALTER TABLE `product_reviews` MODIFY `company_id` VARCHAR(64) NOT NULL");
            } catch (\Throwable) {}
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (Schema::hasTable('product_reviews')) {
            try {
                DB::statement("ALTER TABLE `product_reviews` MODIFY `company_id` BIGINT UNSIGNED NOT NULL");
            } catch (\Throwable) {}
        }

        if (Schema::hasTable('coupon_usages')) {
            try {
                DB::statement("ALTER TABLE `coupon_usages` MODIFY `company_id` BIGINT UNSIGNED NOT NULL");
            } catch (\Throwable) {}
        }

        if (Schema::hasTable('coupons')) {
            try {
                DB::statement("ALTER TABLE `coupons` MODIFY `company_id` BIGINT UNSIGNED NOT NULL");
            } catch (\Throwable) {}
        }
    }
};
