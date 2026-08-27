<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_registers', function (Blueprint $table) {
            $table->id();
            $table->string('company_id');
            $table->string('opened_by')->nullable();
            $table->string('closed_by')->nullable();
            $table->decimal('opening_balance', 12, 2)->default(0);
            $table->decimal('expected_closing_balance', 12, 2)->nullable();
            $table->decimal('counted_closing_balance', 12, 2)->nullable();
            $table->decimal('cash_difference', 12, 2)->nullable();
            $table->string('status', 16)->default('open'); // open, closed
            $table->dateTime('opened_at');
            $table->dateTime('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('opened_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('closed_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['company_id', 'status']);
        });

        Schema::create('cash_register_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('company_id');
            $table->foreignId('cash_register_id')->constrained('cash_registers')->cascadeOnDelete();
            $table->string('type', 16); // cash_in, cash_out
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('reason', 255)->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['company_id', 'cash_register_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_register_transactions');
        Schema::dropIfExists('cash_registers');
    }
};
