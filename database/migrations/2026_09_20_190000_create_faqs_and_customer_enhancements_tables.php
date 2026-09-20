<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('faqs')) {
            Schema::create('faqs', function (Blueprint $table) {
                $table->id();
                $table->string('company_id')->index();
                $table->text('question');
                $table->text('answer');
                $table->string('category', 100)->nullable()->default('General');
                $table->integer('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'date_of_birth')) {
                $table->date('date_of_birth')->nullable()->after('age');
            }
            if (! Schema::hasColumn('customers', 'avatar_url')) {
                $table->string('avatar_url', 500)->nullable()->after('date_of_birth');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('faqs');

        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'avatar_url')) {
                $table->dropColumn('avatar_url');
            }
            if (Schema::hasColumn('customers', 'date_of_birth')) {
                $table->dropColumn('date_of_birth');
            }
        });
    }
};
