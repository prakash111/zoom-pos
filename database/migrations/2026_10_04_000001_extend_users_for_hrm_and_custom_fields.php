<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'pin_code')) {
                $table->string('pin_code', 4)->nullable()->after('password');
            }
            if (!Schema::hasColumn('users', 'basic_salary')) {
                $table->decimal('basic_salary', 15, 2)->default(0.00)->after('pin_code');
            }
            if (!Schema::hasColumn('users', 'commission_rate')) {
                $table->decimal('commission_rate', 5, 2)->default(0.00)->after('basic_salary');
            }
            if (!Schema::hasColumn('users', 'employee_code')) {
                $table->string('employee_code', 50)->nullable()->after('pin_code');
            }
            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 50)->nullable()->after('email');
            }
            if (!Schema::hasColumn('users', 'custom_fields')) {
                $table->json('custom_fields')->nullable()->after('remember_token');
            }
        });

        // Table for tenants to define custom fields dynamically
        if (!Schema::hasTable('tenant_custom_fields')) {
            Schema::create('tenant_custom_fields', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id', 100)->index();
                $table->string('module', 50)->default('staff'); // 'staff', 'customer', etc.
                $table->string('field_key', 50);           // e.g. 'govt_id'
                $table->string('label', 100);              // e.g. 'Aadhaar / National ID'
                $table->string('field_type', 30)->default('text'); // text, number, date, select
                $table->json('options')->nullable();       // For select type
                $table->boolean('is_required')->default(false);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['tenant_id', 'module', 'field_key']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_custom_fields');
        Schema::table('users', function (Blueprint $table) {
            $drop = [];
            foreach (['pin_code', 'basic_salary', 'employee_code', 'custom_fields', 'phone'] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $drop[] = $col;
                }
            }
            if (!empty($drop)) {
                $table->dropColumn($drop);
            }
        });
    }
};
