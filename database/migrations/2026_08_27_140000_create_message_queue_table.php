<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Local outbound delivery queue for invoice/quotation email + WhatsApp
 * sends. Deliberately NOT part of the desktop sync surface (DesktopSyncEngine)
 * — it's a local delivery log, not business data the server needs a copy of.
 * A send is attempted immediately; only on failure (typically: offline) does
 * a row land here, so the UI can show "Queued" instead of lying about "Sent".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_queue', function (Blueprint $table) {
            $table->id();
            $table->string('company_id');
            $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->string('type', 20); // email | whatsapp
            $table->string('recipient');
            $table->json('payload'); // custom_message, attach_pdf, document_type — never the raw PDF bytes
            $table->string('status', 20)->default('queued'); // queued | sending | sent | failed
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_queue');
    }
};
