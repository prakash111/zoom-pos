<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Ensure lead_mod_leads has all core integration columns
        if (Schema::hasTable('lead_mod_leads')) {
            // Sanitize existing assigned_to & customer_id values before applying foreign key constraints
            try {
                DB::statement("UPDATE lead_mod_leads SET assigned_to = NULL WHERE assigned_to IS NOT NULL AND assigned_to NOT IN (SELECT id FROM users)");
                DB::statement("UPDATE lead_mod_leads SET customer_id = NULL WHERE customer_id IS NOT NULL AND customer_id NOT IN (SELECT id FROM customers)");
            } catch (\Throwable $e) {
                // Non-fatal if tables are empty
            }

            Schema::table('lead_mod_leads', function (Blueprint $table) {
                if (! Schema::hasColumn('lead_mod_leads', 'stage')) {
                    $table->enum('stage', ['new', 'contacted', 'qualified', 'proposal_sent', 'won', 'lost'])
                        ->default('new')
                        ->after('status')
                        ->index();
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

            // Ensure assigned_to is varchar(255) to match users.id
            try {
                Schema::table('lead_mod_leads', function (Blueprint $table) {
                    $table->string('assigned_to', 255)->nullable()->change();
                });
            } catch (\Throwable $e) {
            }

            // Ensure foreign keys on lead_mod_leads
            $foreignKeys = collect();
            try {
                $foreignKeys = collect(Schema::getForeignKeys('lead_mod_leads'));
            } catch (\Throwable $e) {
            }

            $hasCustomerFk = $foreignKeys->contains(function ($fk) {
                return ($fk['name'] ?? '') === 'lead_mod_leads_customer_id_foreign'
                    || in_array('customer_id', $fk['columns'] ?? []);
            });

            $hasAssignedToFk = $foreignKeys->contains(function ($fk) {
                return ($fk['name'] ?? '') === 'lead_mod_leads_assigned_to_foreign'
                    || in_array('assigned_to', $fk['columns'] ?? []);
            });

            if (! $hasCustomerFk || ! $hasAssignedToFk) {
                try {
                    $existingConstraints = DB::table('information_schema.TABLE_CONSTRAINTS')
                        ->where('TABLE_SCHEMA', DB::raw('DATABASE()'))
                        ->where('TABLE_NAME', 'lead_mod_leads')
                        ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
                        ->pluck('CONSTRAINT_NAME')
                        ->all();

                    if (in_array('lead_mod_leads_customer_id_foreign', $existingConstraints)) {
                        $hasCustomerFk = true;
                    }
                    if (in_array('lead_mod_leads_assigned_to_foreign', $existingConstraints)) {
                        $hasAssignedToFk = true;
                    }
                } catch (\Throwable $e) {
                }
            }

            if (! $hasCustomerFk && Schema::hasTable('customers')) {
                try {
                    Schema::table('lead_mod_leads', function (Blueprint $table) {
                        $table->foreign('customer_id')
                            ->references('id')
                            ->on('customers')
                            ->nullOnDelete();
                    });
                } catch (\Throwable $e) {
                }
            }

            if (! $hasAssignedToFk && Schema::hasTable('users')) {
                try {
                    Schema::table('lead_mod_leads', function (Blueprint $table) {
                        $table->foreign('assigned_to')
                            ->references('id')
                            ->on('users')
                            ->nullOnDelete();
                    });
                } catch (\Throwable $e) {
                }
            }

            // Backfill stage from status if stage is 'new' and status has a specific state
            try {
                DB::statement("UPDATE lead_mod_leads SET stage = 'won' WHERE status = 'won' AND stage = 'new'");
                DB::statement("UPDATE lead_mod_leads SET stage = 'lost' WHERE status = 'lost' AND stage = 'new'");
                DB::statement("UPDATE lead_mod_leads SET stage = 'proposal_sent' WHERE status = 'proposal' AND stage = 'new'");
                DB::statement("UPDATE lead_mod_leads SET stage = status WHERE status IN ('contacted', 'qualified') AND stage = 'new'");
                DB::statement("UPDATE lead_mod_leads SET expected_value = estimated_value WHERE expected_value = 0 AND estimated_value > 0");
                DB::statement("UPDATE lead_mod_leads SET source = source_name WHERE source IS NULL AND source_name IS NOT NULL");
            } catch (\Throwable $e) {
                // Ignore backfill error
            }

            // Create or replace view `leads` pointing to `lead_mod_leads`
            try {
                DB::statement('CREATE OR REPLACE VIEW leads AS SELECT * FROM lead_mod_leads');
            } catch (\Throwable $e) {
                // View creation fallback
            }
        }

        // 2. Add lead_id to sales table
        if (Schema::hasTable('sales')) {
            Schema::table('sales', function (Blueprint $table) {
                if (! Schema::hasColumn('sales', 'lead_id')) {
                    $table->unsignedBigInteger('lead_id')->nullable()->index()->after('customer_id');
                }
            });
        }

        // 3. Add source to customers table
        if (Schema::hasTable('customers')) {
            Schema::table('customers', function (Blueprint $table) {
                if (! Schema::hasColumn('customers', 'source')) {
                    $table->string('source', 255)->nullable()->after('phone');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('customers') && Schema::hasColumn('customers', 'source')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('source');
            });
        }

        if (Schema::hasTable('sales') && Schema::hasColumn('sales', 'lead_id')) {
            Schema::table('sales', function (Blueprint $table) {
                try {
                    $table->dropIndex(['lead_id']);
                } catch (\Throwable $e) {
                }
                $table->dropColumn('lead_id');
            });
        }

        try {
            DB::statement('DROP VIEW IF EXISTS leads');
        } catch (\Throwable $e) {
        }

        if (Schema::hasTable('lead_mod_leads')) {
            $foreignKeys = collect();
            try {
                $foreignKeys = collect(Schema::getForeignKeys('lead_mod_leads'));
            } catch (\Throwable $e) {
            }

            $hasCustomerFk = $foreignKeys->contains(function ($fk) {
                return ($fk['name'] ?? '') === 'lead_mod_leads_customer_id_foreign'
                    || in_array('customer_id', $fk['columns'] ?? []);
            });

            $hasAssignedToFk = $foreignKeys->contains(function ($fk) {
                return ($fk['name'] ?? '') === 'lead_mod_leads_assigned_to_foreign'
                    || in_array('assigned_to', $fk['columns'] ?? []);
            });

            if (! $hasCustomerFk || ! $hasAssignedToFk) {
                try {
                    $existingConstraints = DB::table('information_schema.TABLE_CONSTRAINTS')
                        ->where('TABLE_SCHEMA', DB::raw('DATABASE()'))
                        ->where('TABLE_NAME', 'lead_mod_leads')
                        ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
                        ->pluck('CONSTRAINT_NAME')
                        ->all();

                    if (in_array('lead_mod_leads_customer_id_foreign', $existingConstraints)) {
                        $hasCustomerFk = true;
                    }
                    if (in_array('lead_mod_leads_assigned_to_foreign', $existingConstraints)) {
                        $hasAssignedToFk = true;
                    }
                } catch (\Throwable $e) {
                }
            }

            if ($hasCustomerFk) {
                try {
                    Schema::table('lead_mod_leads', function (Blueprint $table) {
                        $table->dropForeign(['customer_id']);
                    });
                } catch (\Throwable $e) {
                }
            }

            if ($hasAssignedToFk) {
                try {
                    Schema::table('lead_mod_leads', function (Blueprint $table) {
                        $table->dropForeign(['assigned_to']);
                    });
                } catch (\Throwable $e) {
                }
            }

            Schema::table('lead_mod_leads', function (Blueprint $table) {
                $columnsToDrop = [];
                foreach (['stage', 'source', 'title', 'requirement_summary', 'expected_value', 'deleted_at'] as $col) {
                    if (Schema::hasColumn('lead_mod_leads', $col)) {
                        $columnsToDrop[] = $col;
                    }
                }
                if (! empty($columnsToDrop)) {
                    $table->dropColumn($columnsToDrop);
                }
            });
        }
    }
};
