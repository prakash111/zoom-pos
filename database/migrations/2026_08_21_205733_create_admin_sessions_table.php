<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_sessions', function (Blueprint $table) {
            $table->string('token', 64)->primary(); // plain 64-char hex, no prefix
            $table->string('platform_admin_id');
            $table->timestamp('expires_at');
            $table->boolean('revoked')->default(false);
            $table->string('ip', 64)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamps();

            $table->foreign('platform_admin_id')->references('id')->on('platform_admins')->cascadeOnDelete();
            $table->index(['platform_admin_id', 'revoked']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_sessions');
    }
};
