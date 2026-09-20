<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'password')) {
                $table->string('password')->nullable()->after('email');
            }
            if (! Schema::hasColumn('customers', 'auth_token')) {
                $table->string('auth_token', 128)->nullable()->index()->after('password');
            }
        });

        if (! Schema::hasTable('customer_addresses')) {
            Schema::create('customer_addresses', function (Blueprint $table) {
                $table->id();
                $table->string('company_id');
                $table->unsignedBigInteger('customer_id');
                $table->string('name')->nullable();
                $table->string('phone')->nullable();
                $table->string('type', 50)->default('home');
                $table->text('street_address');
                $table->string('city')->nullable();
                $table->string('state')->nullable();
                $table->string('postal_code')->nullable();
                $table->string('country')->default('India');
                $table->boolean('is_default')->default(false);
                $table->timestamps();

                $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
                $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
                $table->index(['company_id', 'customer_id']);
            });
        }

        if (! Schema::hasTable('customer_wishlists')) {
            Schema::create('customer_wishlists', function (Blueprint $table) {
                $table->id();
                $table->string('company_id');
                $table->unsignedBigInteger('customer_id');
                $table->unsignedBigInteger('product_id');
                $table->timestamps();

                $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
                $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
                $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
                $table->unique(['customer_id', 'product_id']);
                $table->index(['company_id', 'customer_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_wishlists');
        Schema::dropIfExists('customer_addresses');

        Schema::table('customers', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('customers', 'auth_token')) {
                $columns[] = 'auth_token';
            }
            if (Schema::hasColumn('customers', 'password')) {
                $columns[] = 'password';
            }
            if (! empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
