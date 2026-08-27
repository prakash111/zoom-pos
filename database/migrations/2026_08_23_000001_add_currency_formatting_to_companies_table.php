<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'currency_symbol')) {
                $table->string('currency_symbol', 8)->default('$')->after('currency');
            }
            if (! Schema::hasColumn('companies', 'currency_decimals')) {
                $table->unsignedTinyInteger('currency_decimals')->default(2)->after('currency_symbol');
            }
            if (! Schema::hasColumn('companies', 'currency_symbol_position')) {
                $table->string('currency_symbol_position', 8)->default('prefix')->after('currency_decimals');
            }
            if (! Schema::hasColumn('companies', 'other_currencies')) {
                $table->json('other_currencies')->nullable()->after('currency_symbol_position');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $columns = ['currency_symbol', 'currency_decimals', 'currency_symbol_position', 'other_currencies'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('companies', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
