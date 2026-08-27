<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->string('id')->primary(); // emp_<hex> — embedded directly in tenant bearer tokens
            $table->string('unique_account_id')->unique(); // ACC-XXXXXXXX
            $table->string('name');
            $table->string('trade_name')->nullable();
            $table->string('legal_name')->nullable();
            $table->string('tax_id')->nullable();
            $table->string('tax_id_label')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country', 2)->default('US');
            $table->string('currency', 8)->default('USD');
            $table->string('language', 8)->default('en');
            $table->string('logo')->nullable();
            $table->string('status')->default('active'); // active|suspended|cancelled
            $table->string('plan_name')->nullable();
            $table->string('activation_key')->unique(); // no-password account recovery credential
            $table->timestamp('registered_at')->useCurrent();
            $table->timestamp('expires_at')->nullable(); // null handled as "not gated"; never auto-deletes data
            $table->unsignedInteger('max_users')->nullable(); // overrides plan limit when set
            $table->unsignedInteger('max_devices')->nullable();
            $table->string('pricing_mode')->nullable();
            $table->string('tax_api_mode')->nullable();
            $table->text('tax_api_key')->nullable();
            $table->string('tax_api_endpoint')->nullable();
            $table->string('invoice_prefix')->nullable();
            $table->string('quotation_prefix')->nullable();
            $table->json('tax_settings')->nullable();
            $table->text('invoice_terms')->nullable();
            $table->text('quote_terms')->nullable();
            $table->text('bank_details')->nullable();
            $table->timestamps();

            $table->foreign('plan_name')->references('name')->on('plans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
