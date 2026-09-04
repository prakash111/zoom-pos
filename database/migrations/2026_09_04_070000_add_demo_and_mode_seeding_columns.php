<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add is_demo and pharmacy/service fields to products table
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'is_demo')) {
                $table->boolean('is_demo')->default(false)->index()->after('active');
            }
            if (! Schema::hasColumn('products', 'batch_number')) {
                $table->string('batch_number')->nullable()->index()->after('is_demo');
            }
            if (! Schema::hasColumn('products', 'mfg_date')) {
                $table->date('mfg_date')->nullable()->after('batch_number');
            }
            if (! Schema::hasColumn('products', 'expiry_date')) {
                $table->date('expiry_date')->nullable()->index()->after('mfg_date');
            }
            if (! Schema::hasColumn('products', 'requires_prescription')) {
                $table->boolean('requires_prescription')->default(false)->after('expiry_date');
            }
            if (! Schema::hasColumn('products', 'duration_minutes')) {
                $table->unsignedSmallInteger('duration_minutes')->nullable()->after('requires_prescription');
            }
        });

        // 2. Add is_demo to categories
        Schema::table('categories', function (Blueprint $table) {
            if (! Schema::hasColumn('categories', 'is_demo')) {
                $table->boolean('is_demo')->default(false)->index()->after('active');
            }
        });

        // 3. Add is_demo to sales
        Schema::table('sales', function (Blueprint $table) {
            if (! Schema::hasColumn('sales', 'is_demo')) {
                $table->boolean('is_demo')->default(false)->index()->after('status');
            }
        });

        // 4. Add is_demo to dining_tables
        if (Schema::hasTable('dining_tables')) {
            Schema::table('dining_tables', function (Blueprint $table) {
                if (! Schema::hasColumn('dining_tables', 'is_demo')) {
                    $table->boolean('is_demo')->default(false)->index()->after('is_active');
                }
            });
        }

        // 5. Add is_demo to dining_floors
        if (Schema::hasTable('dining_floors')) {
            Schema::table('dining_floors', function (Blueprint $table) {
                if (! Schema::hasColumn('dining_floors', 'is_demo')) {
                    $table->boolean('is_demo')->default(false)->index()->after('is_active');
                }
            });
        }

        // 6. Add is_demo to customers
        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'is_demo')) {
                $table->boolean('is_demo')->default(false)->index()->after('phone');
            }
        });

        // 7. Add is_demo to kitchen_tickets
        if (Schema::hasTable('kitchen_tickets')) {
            Schema::table('kitchen_tickets', function (Blueprint $table) {
                if (! Schema::hasColumn('kitchen_tickets', 'is_demo')) {
                    $table->boolean('is_demo')->default(false)->index()->after('status');
                }
            });
        }

        // 8. Add is_demo to service_orders
        if (Schema::hasTable('service_orders')) {
            Schema::table('service_orders', function (Blueprint $table) {
                if (! Schema::hasColumn('service_orders', 'is_demo')) {
                    $table->boolean('is_demo')->default(false)->index()->after('status');
                }
            });
        }

        // 9. Add is_demo to tax_rules
        if (Schema::hasTable('tax_rules')) {
            Schema::table('tax_rules', function (Blueprint $table) {
                if (! Schema::hasColumn('tax_rules', 'is_demo')) {
                    $table->boolean('is_demo')->default(false)->index()->after('active');
                }
            });
        }

        // 10. Add is_demo to customer_ledgers
        if (Schema::hasTable('customer_ledgers')) {
            Schema::table('customer_ledgers', function (Blueprint $table) {
                if (! Schema::hasColumn('customer_ledgers', 'is_demo')) {
                    $table->boolean('is_demo')->default(false)->index()->after('description');
                }
            });
        }

        // 11. Add is_demo and shift to users
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'is_demo')) {
                $table->boolean('is_demo')->default(false)->index()->after('status');
            }
            if (! Schema::hasColumn('users', 'shift')) {
                $table->string('shift')->nullable()->after('is_demo');
            }
        });

        // 12. Add is_seeding_complete to companies
        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'is_seeding_complete')) {
                $table->boolean('is_seeding_complete')->default(false)->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (Schema::hasColumn('companies', 'is_seeding_complete')) {
                $table->dropColumn('is_seeding_complete');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $cols = array_filter(['is_demo', 'shift'], fn ($c) => Schema::hasColumn('users', $c));
            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });

        if (Schema::hasTable('customer_ledgers') && Schema::hasColumn('customer_ledgers', 'is_demo')) {
            Schema::table('customer_ledgers', function (Blueprint $table) {
                $table->dropColumn('is_demo');
            });
        }

        if (Schema::hasTable('tax_rules') && Schema::hasColumn('tax_rules', 'is_demo')) {
            Schema::table('tax_rules', function (Blueprint $table) {
                $table->dropColumn('is_demo');
            });
        }

        if (Schema::hasTable('service_orders') && Schema::hasColumn('service_orders', 'is_demo')) {
            Schema::table('service_orders', function (Blueprint $table) {
                $table->dropColumn('is_demo');
            });
        }

        if (Schema::hasTable('kitchen_tickets') && Schema::hasColumn('kitchen_tickets', 'is_demo')) {
            Schema::table('kitchen_tickets', function (Blueprint $table) {
                $table->dropColumn('is_demo');
            });
        }

        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'is_demo')) {
                $table->dropColumn('is_demo');
            }
        });

        if (Schema::hasTable('dining_floors') && Schema::hasColumn('dining_floors', 'is_demo')) {
            Schema::table('dining_floors', function (Blueprint $table) {
                $table->dropColumn('is_demo');
            });
        }

        if (Schema::hasTable('dining_tables') && Schema::hasColumn('dining_tables', 'is_demo')) {
            Schema::table('dining_tables', function (Blueprint $table) {
                $table->dropColumn('is_demo');
            });
        }

        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'is_demo')) {
                $table->dropColumn('is_demo');
            }
        });

        Schema::table('categories', function (Blueprint $table) {
            if (Schema::hasColumn('categories', 'is_demo')) {
                $table->dropColumn('is_demo');
            }
        });

        Schema::table('products', function (Blueprint $table) {
            $cols = array_filter([
                'is_demo', 'batch_number', 'mfg_date', 'expiry_date',
                'requires_prescription', 'duration_minutes',
            ], fn ($c) => Schema::hasColumn('products', $c));
            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
