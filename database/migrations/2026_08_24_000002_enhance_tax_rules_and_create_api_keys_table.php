<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tax_rules', function (Blueprint $table) {
            if (! Schema::hasColumn('tax_rules', 'type')) {
                $table->string('type', 20)->default('percentage')->after('rate');
            }
            if (! Schema::hasColumn('tax_rules', 'is_inclusive')) {
                $table->boolean('is_inclusive')->default(false)->after('calc_type');
            }
            if (! Schema::hasColumn('tax_rules', 'is_compound')) {
                $table->boolean('is_compound')->default(false)->after('is_inclusive');
            }
            if (! Schema::hasColumn('tax_rules', 'is_default')) {
                $table->boolean('is_default')->default(false)->after('is_compound');
            }
            if (! Schema::hasColumn('tax_rules', 'sub_components')) {
                $table->json('sub_components')->nullable()->after('is_default');
            }
            if (! Schema::hasColumn('tax_rules', 'priority')) {
                $table->integer('priority')->default(0)->after('sub_components');
            }
            if (! Schema::hasColumn('tax_rules', 'description')) {
                $table->text('description')->nullable()->after('priority');
            }
        });

        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'tax_rule_id')) {
                $table->string('tax_rule_id')->nullable()->after('tax_rate');
            }
            if (! Schema::hasColumn('products', 'is_tax_inclusive')) {
                $table->boolean('is_tax_inclusive')->default(false)->after('tax_rule_id');
            }
        });

        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'tax_id')) {
                $table->string('tax_id', 50)->nullable()->after('document');
            }
            if (! Schema::hasColumn('customers', 'is_tax_exempt')) {
                $table->boolean('is_tax_exempt')->default(false)->after('tax_id');
            }
        });

        Schema::table('sales', function (Blueprint $table) {
            if (! Schema::hasColumn('sales', 'tax_amount')) {
                $table->decimal('tax_amount', 12, 2)->default(0)->after('discount');
            }
            if (! Schema::hasColumn('sales', 'tax_breakdown')) {
                $table->json('tax_breakdown')->nullable()->after('tax_amount');
            }
            if (! Schema::hasColumn('sales', 'einvoice_status')) {
                $table->string('einvoice_status', 30)->nullable()->after('tax_breakdown');
            }
            if (! Schema::hasColumn('sales', 'einvoice_irn')) {
                $table->string('einvoice_irn')->nullable()->after('einvoice_status');
            }
            if (! Schema::hasColumn('sales', 'einvoice_qr')) {
                $table->text('einvoice_qr')->nullable()->after('einvoice_irn');
            }
            if (! Schema::hasColumn('sales', 'einvoice_signed_payload')) {
                $table->longText('einvoice_signed_payload')->nullable()->after('einvoice_qr');
            }
        });

        if (! Schema::hasTable('tenant_api_keys')) {
            Schema::create('tenant_api_keys', function (Blueprint $table) {
                $table->string('id')->primary(); // key_<hex>
                $table->string('company_id');
                $table->string('name');
                $table->string('token', 80)->unique();
                $table->json('permissions')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();

                $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
                $table->index(['company_id', 'active']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_api_keys');

        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn([
                'tax_amount', 'tax_breakdown', 'einvoice_status',
                'einvoice_irn', 'einvoice_qr', 'einvoice_signed_payload',
            ]);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['tax_id', 'is_tax_exempt']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['tax_rule_id', 'is_tax_inclusive']);
        });

        Schema::table('tax_rules', function (Blueprint $table) {
            $table->dropColumn([
                'type', 'is_inclusive', 'is_compound',
                'is_default', 'sub_components', 'priority', 'description',
            ]);
        });
    }
};
