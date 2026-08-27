<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add locale column to users table if not already present
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'locale')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('locale', 10)->nullable()->after('role');
            });
        }

        // 2. Add default_locale column to companies table if not already present
        if (Schema::hasTable('companies') && ! Schema::hasColumn('companies', 'default_locale')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->string('default_locale', 10)->nullable()->after('language');
            });
        }

        // 3. Add terms column to sales table if not already present
        if (Schema::hasTable('sales') && ! Schema::hasColumn('sales', 'terms')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->text('terms')->nullable()->after('notes');
            });
        }

        // 4. Create tenant_translations table
        if (! Schema::hasTable('tenant_translations')) {
            Schema::create('tenant_translations', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id', 36)->index();
                $table->string('locale', 10)->index();
                $table->string('group', 100)->default('*');
                $table->string('key', 191);
                $table->text('value')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'locale', 'group', 'key'], 'tenant_loc_grp_key_unique');
            });

            // Migrate existing records from company_translations if present
            if (Schema::hasTable('company_translations')) {
                try {
                    $existing = DB::table('company_translations')->get();
                    foreach ($existing as $row) {
                        DB::table('tenant_translations')->insertOrIgnore([
                            'tenant_id' => $row->company_id,
                            'locale' => $row->locale,
                            'group' => '*',
                            'key' => $row->key,
                            'value' => $row->value,
                            'created_at' => $row->created_at ?? now(),
                            'updated_at' => $row->updated_at ?? now(),
                        ]);
                    }
                } catch (Throwable) {
                    // Ignore migration data sync errors
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_translations');

        if (Schema::hasTable('companies') && Schema::hasColumn('companies', 'default_locale')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->dropColumn('default_locale');
            });
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'locale')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('locale');
            });
        }
    }
};
