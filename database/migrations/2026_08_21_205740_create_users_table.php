<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->string('id')->primary(); // usr_<hex>
            $table->string('company_id');
            $table->string('name');
            $table->string('login');
            $table->string('email')->nullable();
            $table->string('password');
            $table->string('role')->default('operador'); // administrator|admin|superadmin|owner (privileged) or custom
            $table->string('status')->default('pending'); // pending|approved|convidado|suspended
            $table->string('invitation_code_hash')->nullable();
            $table->timestamp('invitation_expires_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->unique(['company_id', 'login']);
            $table->index(['company_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
