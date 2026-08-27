<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('company_id')->nullable(); // null = platform-level event
            $table->string('user_id')->nullable(); // tenant user_id OR platform_admin_id
            $table->string('action');
            $table->json('details')->nullable();
            $table->string('result')->nullable(); // success|failure
            $table->string('ip', 64)->nullable();
            $table->timestamps();

            $table->index(['company_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
