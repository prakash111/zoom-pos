<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Flat (user_id, module, action) -> allowed matrix. Privileged roles bypass this
        // entirely (see User::isPrivilegedRole()); everyone else is default-deny.
        Schema::create('permissions', function (Blueprint $table) {
            $table->string('id')->primary(); // perm_<hex>
            $table->string('user_id');
            $table->string('company_id');
            $table->string('module');
            $table->string('action');
            $table->boolean('allowed')->default(true);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->unique(['user_id', 'module', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
