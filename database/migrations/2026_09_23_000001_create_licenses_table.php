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
        if (!Schema::hasTable('licenses')) {
            Schema::create('licenses', function (Blueprint $table) {
                $table->id();
                $table->string('license_key', 64)->nullable()->unique();
                $table->string('product_slug', 64)->nullable();
                $table->string('client_email', 191)->nullable();
                $table->string('registered_domain', 191)->nullable()->index();
                $table->string('bound_domain', 191)->nullable()->index();
                $table->string('bound_ip', 64)->nullable();
                $table->json('allowed_domains')->nullable();
                $table->string('license_type', 32)->default('regular');
                $table->string('plan', 64)->nullable();
                $table->string('status', 32)->default('active');
                $table->boolean('is_active')->default(true);
                $table->json('branding_json')->nullable();
                $table->date('valid_until')->nullable();
                $table->timestamp('last_verified_at')->nullable();
                $table->string('last_verified_ip', 64)->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('licenses');
    }
};
