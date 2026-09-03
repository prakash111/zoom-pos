<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'timezone')) {
                // Null means "no manual override" — Company::resolveTimezone()
                // falls back to a default derived from the `country` column.
                // See Settings > Profile > Timezone & Regional Settings.
                $table->string('timezone', 64)->nullable()->after('country');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('timezone');
        });
    }
};
