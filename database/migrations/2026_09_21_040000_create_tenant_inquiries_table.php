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
        if (! Schema::hasTable('tenant_inquiries')) {
            Schema::create('tenant_inquiries', function (Blueprint $table) {
                $table->id();
                $table->string('company_id');
                $table->string('tenant_id')->nullable();
                $table->string('name');
                $table->string('email')->nullable();
                $table->string('phone', 25);
                $table->string('subject')->nullable();
                $table->text('message');
                $table->string('status')->default('unread'); // unread, contacted, closed
                $table->string('ip_address', 45)->nullable();
                $table->timestamps();

                $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
                $table->index(['company_id', 'status']);
                $table->index(['company_id', 'created_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_inquiries');
    }
};
