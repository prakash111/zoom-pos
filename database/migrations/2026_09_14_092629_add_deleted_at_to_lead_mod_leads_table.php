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
        if (Schema::hasTable('lead_mod_leads')) {
            Schema::table('lead_mod_leads', function (Blueprint $table) {
                if (! Schema::hasColumn('lead_mod_leads', 'stage')) {
                    $table->string('stage', 50)->default('new')->after('status')->index();
                }

                if (! Schema::hasColumn('lead_mod_leads', 'source')) {
                    $table->string('source', 255)->nullable()->after('source_name');
                }

                if (! Schema::hasColumn('lead_mod_leads', 'title')) {
                    $table->string('title', 255)->nullable()->after('name');
                }

                if (! Schema::hasColumn('lead_mod_leads', 'requirement_summary')) {
                    $table->text('requirement_summary')->nullable()->after('notes');
                }

                if (! Schema::hasColumn('lead_mod_leads', 'expected_value')) {
                    $table->decimal('expected_value', 15, 2)->default(0.00)->after('estimated_value');
                }

                if (! Schema::hasColumn('lead_mod_leads', 'deleted_at')) {
                    $table->softDeletes()->after('updated_at');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('lead_mod_leads')) {
            Schema::table('lead_mod_leads', function (Blueprint $table) {
                if (Schema::hasColumn('lead_mod_leads', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
            });
        }
    }
};
