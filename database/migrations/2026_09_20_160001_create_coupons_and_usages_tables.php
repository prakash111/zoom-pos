<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('coupons')) {
            Schema::create('coupons', function (Blueprint $table) {
                $table->id();
                $table->string('company_id', 64)->index();
                $table->string('code', 100);
                $table->enum('discount_type', ['percentage', 'fixed_amount'])->default('percentage');
                $table->decimal('discount_value', 12, 2);
                $table->decimal('min_order_amount', 12, 2)->default(0);
                $table->decimal('max_discount_amount', 12, 2)->nullable();
                $table->integer('usage_limit_total')->nullable();
                $table->integer('usage_limit_per_customer')->default(1);
                $table->integer('used_count')->default(0);
                $table->dateTime('starts_at')->nullable();
                $table->dateTime('expires_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->text('description')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'code']);
            });
        }

        if (! Schema::hasTable('coupon_usages')) {
            Schema::create('coupon_usages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('coupon_id')->index();
                $table->string('company_id', 64)->index();
                $table->unsignedBigInteger('customer_id')->nullable()->index();
                $table->string('customer_email')->nullable()->index();
                $table->string('customer_phone')->nullable()->index();
                $table->unsignedBigInteger('sale_id')->nullable()->index();
                $table->decimal('discount_amount', 12, 2)->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_usages');
        Schema::dropIfExists('coupons');
    }
};
