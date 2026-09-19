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
        if (Schema::hasTable('reminders')) {
            Schema::table('reminders', function (Blueprint $table) {
                if (! Schema::hasColumn('reminders', 'tenant_id')) {
                    $table->string('tenant_id', 255)->nullable()->after('company_id')->index();
                }
                if (! Schema::hasColumn('reminders', 'user_id')) {
                    $table->string('user_id', 255)->nullable()->after('customer_id')->index();
                }
                if (! Schema::hasColumn('reminders', 'subject')) {
                    $table->string('subject', 255)->nullable()->after('title');
                }
                if (! Schema::hasColumn('reminders', 'description')) {
                    $table->text('description')->nullable()->after('notes');
                }
                if (! Schema::hasColumn('reminders', 'call_script')) {
                    $table->text('call_script')->nullable()->after('description');
                }
                if (! Schema::hasColumn('reminders', 'due_at')) {
                    $table->timestamp('due_at')->nullable()->after('due_date')->index();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('reminders')) {
            Schema::table('reminders', function (Blueprint $table) {
                $columns = ['tenant_id', 'user_id', 'subject', 'description', 'call_script', 'due_at'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('reminders', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
