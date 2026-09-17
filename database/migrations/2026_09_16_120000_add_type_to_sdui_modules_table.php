<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sdui_modules', function (Blueprint $table) {
            $table->string('type', 20)->default('core')->index();
        });

        // Keep existing installation, activation, licensing and tenant assignments.
        DB::table('sdui_modules')->whereIn('slug', ['leadmanagement', 'lead-management', 'lead_management', 'leads', 'lead'])->update([
            'type' => 'extension',
            'registration_allowed' => false,
        ]);

        $row = DB::table('platform_system')->where('key', 'allowed_registration_modes')->first();
        $modes = $row ? json_decode($row->value, true) : null;
        if (is_array($modes)) {
            DB::table('platform_system')->where('key', 'allowed_registration_modes')->update([
                'value' => json_encode(array_values(array_diff($modes, ['leadmanagement', 'lead_management', 'lead-management', 'leads', 'lead']))),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('sdui_modules', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn('type');
        });
    }
};
