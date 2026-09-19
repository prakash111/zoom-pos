<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automated_reminder_dispatches', function (Blueprint $table) {
            $table->id();
            $table->string('company_id');
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->string('document_type', 24);
            $table->string('channel', 24);
            $table->string('recipient')->nullable();
            $table->string('cycle_key', 48);
            $table->char('dispatch_key', 64)->unique();
            $table->timestamp('scheduled_for');
            $table->string('status', 20)->default('queued');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->index(['company_id', 'status', 'scheduled_for'], 'auto_reminder_tenant_status_schedule_idx');
            $table->index(['company_id', 'sale_id', 'document_type'], 'auto_reminder_document_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automated_reminder_dispatches');
    }
};
