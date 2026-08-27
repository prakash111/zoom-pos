<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('content')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('show_in_footer')->default(true);
            $table->string('created_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('platform_admins')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
