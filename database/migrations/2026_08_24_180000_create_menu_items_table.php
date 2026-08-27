<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->string('location')->default('header'); // header, footer_col_1, footer_col_2
            $table->string('title');
            $table->string('type')->default('custom'); // page, anchor, custom
            $table->string('url'); // /page/terms, #pricing, https://...
            $table->foreignId('page_id')->nullable()->constrained('pages')->nullOnDelete();
            $table->string('target')->default('_self'); // _self, _blank
            $table->string('icon')->nullable();
            $table->integer('order_index')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
