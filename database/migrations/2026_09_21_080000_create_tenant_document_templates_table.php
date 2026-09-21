<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenant_document_templates')) {
            Schema::create('tenant_document_templates', function (Blueprint $table) {
                $table->id();
                $table->string('company_id')->index();
                $table->string('tenant_id')->nullable()->index();
                $table->string('template_type', 32); // 'invoice', 'quotation'
                $table->string('theme_color', 32)->default('#10b981'); // primary accent color, hex
                $table->string('logo_placement', 32)->default('left'); // 'left', 'center', 'right', 'hidden'
                $table->string('header_title', 120)->nullable(); // e.g. "Tax Invoice", "Commercial Quotation"
                $table->text('terms_conditions')->nullable();
                $table->boolean('show_qr_code')->default(true);
                $table->boolean('show_tax_breakup')->default(true);
                $table->text('footer_notes')->nullable();
                $table->boolean('send_as_attachment')->default(true);
                $table->boolean('send_text_with_link')->default(false);
                $table->text('message_body_template')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'template_type'], 'tenant_doc_templates_comp_type_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_document_templates');
    }
};
