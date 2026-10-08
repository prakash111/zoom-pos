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
        Schema::table('plans', function (Blueprint $table) {
            if (! Schema::hasColumn('plans', 'has_hrm_module')) {
                $table->boolean('has_hrm_module')->default(false)->after('is_active');
            }
            if (! Schema::hasColumn('plans', 'max_staff_limit')) {
                $table->integer('max_staff_limit')->default(2)->after('has_hrm_module');
            }
        });

        if (! Schema::hasTable('plan_addons')) {
            Schema::create('plan_addons', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique(); // 'hrm_payroll'
                $table->text('description')->nullable();
                $table->decimal('price_monthly', 10, 2)->default(0.00);
                $table->decimal('price_yearly', 10, 2)->default(0.00);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('company_addons')) {
            Schema::create('company_addons', function (Blueprint $table) {
                $table->id();
                $table->string('company_id');
                $table->string('addon_slug');
                $table->boolean('is_active')->default(true);
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'addon_slug']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_addons');
        Schema::dropIfExists('plan_addons');

        Schema::table('plans', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('plans', 'has_hrm_module')) {
                $cols[] = 'has_hrm_module';
            }
            if (Schema::hasColumn('plans', 'max_staff_limit')) {
                $cols[] = 'max_staff_limit';
            }
            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
