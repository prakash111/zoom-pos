<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (! Schema::hasColumn('sales', 'tax_name')) {
                $table->string('tax_name')->nullable()->after('tax_amount');
            }
            if (! Schema::hasColumn('sales', 'tax_rate')) {
                $table->decimal('tax_rate', 8, 3)->default(0)->after('tax_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['tax_name', 'tax_rate']);
        });
    }
};
