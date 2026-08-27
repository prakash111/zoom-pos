<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tenant auth sessions (bearer tokens), NOT the framework's HTTP session store
        // (SESSION_DRIVER=file, see .env) — table name matches the legacy wire contract.
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('token', 128)->primary(); // "{company_id}.{64 hex}"
            $table->string('user_id');
            $table->string('company_id');
            $table->timestamp('expires_at');
            $table->boolean('revoked')->default(false);
            $table->string('impersonated_by')->nullable(); // platform_admin_id or user_id who started impersonation
            $table->string('ip', 64)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->index(['company_id', 'revoked']);
            $table->index(['user_id', 'revoked']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};
