<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Sales Targets (Metas de Vendas)
        if (! Schema::hasTable('sales_targets')) {
            Schema::create('sales_targets', function (Blueprint $table) {
                $table->id();
                $table->string('company_id');
                $table->string('user_id')->nullable(); // null = store-wide target
                $table->unsignedSmallInteger('year');
                $table->unsignedTinyInteger('month');
                $table->decimal('target_amount', 14, 2)->default(0);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
                $table->index(['company_id', 'year', 'month']);
            });
        }

        // 2. Consignment Sales Module (Vendas em Consignação)
        if (! Schema::hasTable('consignments')) {
            Schema::create('consignments', function (Blueprint $table) {
                $table->id();
                $table->string('company_id');
                $table->string('consignment_number')->index();
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->string('customer_name');
                $table->string('user_id')->nullable();
                $table->string('status')->default('draft'); // draft, dispatched, reconciled, finalized, cancelled
                $table->dateTime('dispatched_at')->nullable();
                $table->date('due_date')->nullable();
                $table->dateTime('reconciled_at')->nullable();
                $table->decimal('total_dispatched_amount', 14, 2)->default(0);
                $table->decimal('total_sold_amount', 14, 2)->default(0);
                $table->decimal('total_returned_amount', 14, 2)->default(0);
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('sale_id')->nullable();
                $table->timestamps();

                $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
                $table->index(['company_id', 'status']);
            });
        }

        if (! Schema::hasTable('consignment_items')) {
            Schema::create('consignment_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('consignment_id');
                $table->unsignedBigInteger('product_id')->nullable();
                $table->string('product_name');
                $table->decimal('dispatched_quantity', 10, 3)->default(0);
                $table->decimal('returned_quantity', 10, 3)->default(0);
                $table->decimal('sold_quantity', 10, 3)->default(0);
                $table->decimal('unit_price', 12, 2)->default(0);
                $table->decimal('sold_total', 14, 2)->default(0);
                $table->timestamps();

                $table->foreign('consignment_id')->references('id')->on('consignments')->cascadeOnDelete();
            });
        }

        // 3. Columns on Sales
        Schema::table('sales', function (Blueprint $table) {
            if (! Schema::hasColumn('sales', 'agreed_payment_method')) {
                $table->string('agreed_payment_method')->nullable()->after('payment_method');
            }
            if (! Schema::hasColumn('sales', 'merchant_fee_percentage')) {
                $table->decimal('merchant_fee_percentage', 5, 2)->default(0)->after('discount');
            }
            if (! Schema::hasColumn('sales', 'merchant_fee_amount')) {
                $table->decimal('merchant_fee_amount', 12, 2)->default(0)->after('merchant_fee_percentage');
            }
            if (! Schema::hasColumn('sales', 'net_amount')) {
                $table->decimal('net_amount', 14, 2)->default(0)->after('total');
            }
            if (! Schema::hasColumn('sales', 'installments')) {
                $table->unsignedTinyInteger('installments')->default(1)->after('payment_method');
            }
        });

        // 4. Columns on Order Payments
        Schema::table('order_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('order_payments', 'merchant_fee_percentage')) {
                $table->decimal('merchant_fee_percentage', 5, 2)->default(0)->after('change_returned');
            }
            if (! Schema::hasColumn('order_payments', 'merchant_fee_amount')) {
                $table->decimal('merchant_fee_amount', 12, 2)->default(0)->after('merchant_fee_percentage');
            }
            if (! Schema::hasColumn('order_payments', 'net_amount')) {
                $table->decimal('net_amount', 14, 2)->default(0)->after('amount');
            }
            if (! Schema::hasColumn('order_payments', 'installments')) {
                $table->unsignedTinyInteger('installments')->default(1)->after('payment_method');
            }
            if (! Schema::hasColumn('order_payments', 'pix_key')) {
                $table->string('pix_key')->nullable()->after('reference_number');
            }
            if (! Schema::hasColumn('order_payments', 'pix_payload')) {
                $table->text('pix_payload')->nullable()->after('pix_key');
            }
        });

        // 5. Columns on Companies for PIX, Card Machine Fees & Scales
        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'pix_key_type')) {
                $table->string('pix_key_type')->nullable()->after('bank_details');
            }
            if (! Schema::hasColumn('companies', 'pix_key')) {
                $table->string('pix_key')->nullable()->after('pix_key_type');
            }
            if (! Schema::hasColumn('companies', 'pix_merchant_name')) {
                $table->string('pix_merchant_name')->nullable()->after('pix_key');
            }
            if (! Schema::hasColumn('companies', 'pix_merchant_city')) {
                $table->string('pix_merchant_city')->nullable()->after('pix_merchant_name');
            }
            if (! Schema::hasColumn('companies', 'pix_qr_image')) {
                $table->string('pix_qr_image')->nullable()->after('pix_merchant_city');
            }
            if (! Schema::hasColumn('companies', 'card_fee_debit')) {
                $table->decimal('card_fee_debit', 5, 2)->default(1.50)->after('pix_qr_image');
            }
            if (! Schema::hasColumn('companies', 'card_fee_credit_1x')) {
                $table->decimal('card_fee_credit_1x', 5, 2)->default(3.20)->after('card_fee_debit');
            }
            if (! Schema::hasColumn('companies', 'card_fee_credit_installments')) {
                $table->json('card_fee_credit_installments')->nullable()->after('card_fee_credit_1x');
            }
            if (! Schema::hasColumn('companies', 'barcode_scale_prefix')) {
                $table->string('barcode_scale_prefix', 4)->default('2')->after('card_fee_credit_installments');
            }
            if (! Schema::hasColumn('companies', 'barcode_scale_type')) {
                $table->string('barcode_scale_type', 12)->default('weight')->after('barcode_scale_prefix'); // weight | price
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consignment_items');
        Schema::dropIfExists('consignments');
        Schema::dropIfExists('sales_targets');

        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn([
                'agreed_payment_method',
                'merchant_fee_percentage',
                'merchant_fee_amount',
                'net_amount',
                'installments',
            ]);
        });

        Schema::table('order_payments', function (Blueprint $table) {
            $table->dropColumn([
                'merchant_fee_percentage',
                'merchant_fee_amount',
                'net_amount',
                'installments',
                'pix_key',
                'pix_payload',
            ]);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'pix_key_type',
                'pix_key',
                'pix_merchant_name',
                'pix_merchant_city',
                'pix_qr_image',
                'card_fee_debit',
                'card_fee_credit_1x',
                'card_fee_credit_installments',
                'barcode_scale_prefix',
                'barcode_scale_type',
            ]);
        });
    }
};
