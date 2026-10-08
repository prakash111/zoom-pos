<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Loyalty Configuration & Rules
        if (!Schema::hasTable('loyalty_settings')) {
            Schema::create('loyalty_settings', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id', 100)->index();
                $table->string('store_id', 100)->nullable()->index();
                $table->boolean('is_points_enabled')->default(true);
                $table->decimal('spend_amount_per_point', 10, 2)->default(100.00); // Spend ₹100
                $table->decimal('points_awarded', 10, 2)->default(1.00);           // Earn 1 Point
                $table->decimal('redemption_value_per_point', 10, 2)->default(1.00); // 1 Point = ₹1 discount
                $table->unsignedInteger('min_points_to_redeem')->default(50);
                $table->unsignedInteger('max_redemption_percentage')->default(50); // Max 50% of invoice total
                $table->boolean('is_wallet_enabled')->default(true);
                $table->timestamps();

                $table->unique(['tenant_id', 'store_id']);
            });
        }

        // 2. VIP Membership Tiers
        if (!Schema::hasTable('loyalty_tiers')) {
            Schema::create('loyalty_tiers', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id', 100)->index();
                $table->string('name', 50); // Bronze, Silver, Gold, Platinum
                $table->string('badge_color', 20)->default('#10B981');
                $table->decimal('min_spend_threshold', 12, 2)->default(0.00);
                $table->decimal('discount_percentage', 5, 2)->default(0.00); // e.g. 5% off for Gold
                $table->decimal('points_multiplier', 3, 2)->default(1.00);   // e.g. 1.5x points for VIP
                $table->timestamps();
            });
        }

        // 3. Extend Existing Customers Table
        if (Schema::hasTable('customers')) {
            Schema::table('customers', function (Blueprint $table) {
                if (!Schema::hasColumn('customers', 'loyalty_tier_id')) {
                    $table->unsignedBigInteger('loyalty_tier_id')->nullable()->after('phone');
                    try {
                        $table->foreign('loyalty_tier_id')->references('id')->on('loyalty_tiers')->nullOnDelete();
                    } catch (\Throwable) {
                        // Safe fallback if foreign keys are restricted
                    }
                }
                if (!Schema::hasColumn('customers', 'points_balance')) {
                    $table->decimal('points_balance', 12, 2)->default(0.00)->after('loyalty_tier_id');
                }
                if (!Schema::hasColumn('customers', 'wallet_balance')) {
                    $table->decimal('wallet_balance', 14, 2)->default(0.00)->after('points_balance');
                }
                if (!Schema::hasColumn('customers', 'total_lifetime_spend')) {
                    $table->decimal('total_lifetime_spend', 14, 2)->default(0.00)->after('wallet_balance');
                }
            });
        }

        // 4. Points Ledger / Transaction History
        if (!Schema::hasTable('loyalty_points_transactions')) {
            Schema::create('loyalty_points_transactions', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id', 100)->index();
                $table->unsignedBigInteger('customer_id')->index();
                $table->string('order_id', 100)->nullable()->index();
                $table->string('type', 50); // earned, redeemed, expired, adjusted
                $table->decimal('points', 12, 2);
                $table->decimal('monetary_equivalent', 12, 2)->default(0.00);
                $table->string('description')->nullable();
                $table->string('created_by', 100)->nullable();
                $table->timestamps();
            });
        }

        // 5. Store Wallet Ledger (Prepaid Balance & Top-ups)
        if (!Schema::hasTable('customer_wallet_transactions')) {
            Schema::create('customer_wallet_transactions', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id', 100)->index();
                $table->unsignedBigInteger('customer_id')->index();
                $table->string('order_id', 100)->nullable()->index();
                $table->string('type', 50); // topup, purchase_debit, cashback_credit, refund_credit, adjustment
                $table->decimal('amount', 12, 2);
                $table->decimal('bonus_amount', 12, 2)->default(0.00);
                $table->decimal('running_balance', 14, 2);
                $table->string('payment_method')->nullable(); // Cash, UPI, Card
                $table->string('reference_number')->nullable();
                $table->string('created_by', 100)->nullable();
                $table->string('description')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_wallet_transactions');
        Schema::dropIfExists('loyalty_points_transactions');
        if (Schema::hasTable('customers')) {
            Schema::table('customers', function (Blueprint $table) {
                try {
                    $table->dropForeign(['loyalty_tier_id']);
                } catch (\Throwable) {}
                $cols = [];
                foreach (['loyalty_tier_id', 'points_balance', 'wallet_balance', 'total_lifetime_spend'] as $col) {
                    if (Schema::hasColumn('customers', $col)) {
                        $cols[] = $col;
                    }
                }
                if (!empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }
        Schema::dropIfExists('loyalty_tiers');
        Schema::dropIfExists('loyalty_settings');
    }
};
