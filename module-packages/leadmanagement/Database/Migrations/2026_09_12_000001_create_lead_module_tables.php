<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tables owned by the "leadmanagement" package module. Prefixed `lead_mod_` so
 * the package is self-contained and never collides with any core table.
 *
 * Run by ModulePackageService::activate(); rolled back by
 * ModulePackageService::uninstall($dropData = true).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lead_mod_sources')) {
            Schema::create('lead_mod_sources', function (Blueprint $table) {
                $table->id();
                $table->string('company_id', 255)->index();
                $table->string('name', 100);
                $table->string('description', 255)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['company_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('lead_mod_leads')) {
            Schema::create('lead_mod_leads', function (Blueprint $table) {
                $table->id();
                $table->string('company_id', 255)->index();
                $table->string('lead_code', 60)->unique();
                $table->string('name', 200);
                $table->string('title', 255)->nullable();
                $table->string('company_name', 200)->nullable();
                $table->string('email', 150)->nullable();
                $table->string('phone', 50)->nullable();
                $table->unsignedBigInteger('source_id')->nullable()->index();
                $table->string('source_name', 100)->nullable();
                $table->string('source', 255)->nullable();
                $table->string('status', 50)->default('active'); // active, won, lost, archived
                $table->enum('stage', ['new', 'contacted', 'qualified', 'proposal_sent', 'won', 'lost'])->default('new')->index();
                $table->string('priority', 30)->default('medium'); // low, medium, high, urgent
                $table->decimal('estimated_value', 12, 2)->default(0);
                $table->decimal('expected_value', 15, 2)->default(0);
                $table->string('assigned_to', 255)->nullable()->index();
                $table->text('notes')->nullable();
                $table->text('requirement_summary')->nullable();
                $table->string('lost_reason', 255)->nullable();
                $table->timestamp('converted_at')->nullable();
                $table->unsignedBigInteger('customer_id')->nullable()->index();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['company_id', 'status']);
                $table->index(['company_id', 'stage']);
                $table->index(['company_id', 'priority']);

                if (Schema::hasTable('customers')) {
                    $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
                }
                if (Schema::hasTable('users')) {
                    $table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();
                }
            });

            try {
                DB::statement('CREATE OR REPLACE VIEW leads AS SELECT * FROM lead_mod_leads');
            } catch (\Throwable $e) {
            }
        }

        if (! Schema::hasTable('lead_mod_activities')) {
            Schema::create('lead_mod_activities', function (Blueprint $table) {
                $table->id();
                $table->string('company_id', 255)->index();
                $table->unsignedBigInteger('lead_id')->index();
                $table->string('type', 40)->default('call'); // call, meeting, email, note, task
                $table->string('title', 200);
                $table->text('description')->nullable();
                $table->dateTime('due_date')->nullable();
                $table->string('status', 40)->default('pending'); // pending, completed, cancelled
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'status']);
                $table->index(['lead_id', 'status']);
            });
        }

        // Add lead_id to sales table if missing
        if (Schema::hasTable('sales') && ! Schema::hasColumn('sales', 'lead_id')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->unsignedBigInteger('lead_id')->nullable()->index()->after('customer_id');
            });
        }

        // Add source to customers table if missing
        if (Schema::hasTable('customers') && ! Schema::hasColumn('customers', 'source')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->string('source', 255)->nullable()->after('phone');
            });
        }
    }

    public function down(): void
    {
        try {
            DB::statement('DROP VIEW IF EXISTS leads');
        } catch (\Throwable $e) {
        }
        Schema::dropIfExists('lead_mod_activities');
        Schema::dropIfExists('lead_mod_leads');
        Schema::dropIfExists('lead_mod_sources');
    }
};
