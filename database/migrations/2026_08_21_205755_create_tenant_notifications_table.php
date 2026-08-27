<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_notifications', function (Blueprint $table) {
            $table->string('id')->primary(); // notif_<hex>
            $table->string('company_id');
            $table->string('category')->nullable();
            $table->string('title');
            $table->text('message');
            $table->boolean('read_status')->default(false);
            $table->string('cta_label')->nullable();
            $table->string('cta_url')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->index(['company_id', 'read_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_notifications');
    }
};
