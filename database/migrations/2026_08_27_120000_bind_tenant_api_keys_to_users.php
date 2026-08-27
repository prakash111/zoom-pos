<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_api_keys', function (Blueprint $table) {
            if (! Schema::hasColumn('tenant_api_keys', 'user_id')) {
                $table->string('user_id')->nullable()->after('company_id')->index();
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenant_api_keys', function (Blueprint $table) {
            if (Schema::hasColumn('tenant_api_keys', 'user_id')) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            }
        });
    }
};
