<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('company_id');
            $table->string('name');
            $table->string('code', 64);
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('tax_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_primary')->default(false);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'is_active']);
        });

        Schema::create('store_user', function (Blueprint $table) {
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('user_id');
            $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->primary(['store_id', 'user_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->integer('store_limit')->default(1);
        });
        foreach (DB::table('plans')->select('name', 'limits')->get() as $plan) {
            $limits = json_decode($plan->limits ?? '{}', true) ?: [];
            if (isset($limits['filiais'])) {
                DB::table('plans')->where('name', $plan->name)->update(['store_limit' => (int) $limits['filiais']]);
            }
        }
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('current_store_id')->nullable()->constrained('stores')->nullOnDelete();
        });
        foreach (['sales', 'cash_registers', 'order_payments'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            });
        }

        Schema::create('product_store_stock', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->decimal('quantity', 12, 3)->default(0);
            $table->timestamps();
            $table->primary(['product_id', 'store_id']);
        });

        foreach (DB::table('companies')->select('id', 'name', 'trade_name', 'phone', 'email', 'address', 'tax_id')->cursor() as $company) {
            $now = now();
            $storeId = DB::table('stores')->insertGetId([
                'company_id' => $company->id,
                'name' => $company->trade_name ?: $company->name,
                'code' => 'main',
                'phone' => $company->phone,
                'email' => $company->email,
                'address' => $company->address,
                'tax_id' => $company->tax_id,
                'is_active' => true,
                'is_primary' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('users')->where('company_id', $company->id)->update(['current_store_id' => $storeId]);
            foreach (DB::table('users')->where('company_id', $company->id)->pluck('id') as $userId) {
                DB::table('store_user')->insert(['store_id' => $storeId, 'user_id' => $userId]);
            }
            foreach (['sales', 'cash_registers', 'order_payments'] as $name) {
                DB::table($name)->where('company_id', $company->id)->update(['store_id' => $storeId]);
            }
            foreach (DB::table('products')->where('company_id', $company->id)->select('id', 'current_stock')->cursor() as $product) {
                DB::table('product_store_stock')->insert([
                    'product_id' => $product->id,
                    'store_id' => $storeId,
                    'quantity' => $product->current_stock ?? 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_store_stock');
        foreach (['order_payments', 'cash_registers', 'sales'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropConstrainedForeignId('store_id'));
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('current_store_id'));
        Schema::table('plans', fn (Blueprint $table) => $table->dropColumn('store_limit'));
        Schema::dropIfExists('store_user');
        Schema::dropIfExists('stores');
    }
};
