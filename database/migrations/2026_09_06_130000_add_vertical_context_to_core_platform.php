<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'repair_prefix')) {
                $table->string('repair_prefix', 20)->default('REP-');
            }
            if (! Schema::hasColumn('companies', 'prescription_prefix')) {
                $table->string('prescription_prefix', 20)->default('RX-');
            }
            if (! Schema::hasColumn('companies', 'salon_prefix')) {
                $table->string('salon_prefix', 20)->default('SAL-');
            }
        });

        Schema::table('sales', function (Blueprint $table) {
            if (! Schema::hasColumn('sales', 'module_type')) {
                $table->string('module_type', 30)->default('pos')->index();
            }
            if (! Schema::hasColumn('sales', 'reference_ticket_id')) {
                $table->unsignedBigInteger('reference_ticket_id')->nullable()->index();
            }
            if (! Schema::hasColumn('sales', 'doctor_name')) {
                $table->string('doctor_name', 150)->nullable();
            }
            if (! Schema::hasColumn('sales', 'stylist_ids')) {
                $table->json('stylist_ids')->nullable();
            }
        });

        // The JSON snapshot on sales remains for offline/backward compatibility;
        // this table is the normalized core ledger used by every POS module.
        if (! Schema::hasTable('sale_items')) {
            Schema::create('sale_items', function (Blueprint $table) {
                $table->id();
                $table->string('company_id')->index();
                $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
                $table->unsignedBigInteger('product_id')->nullable()->index();
                $table->unsignedBigInteger('batch_id')->nullable()->index();
                $table->string('staff_id')->nullable()->index();
                $table->string('line_type', 40)->default('product')->index();
                $table->string('name', 255);
                $table->decimal('quantity', 12, 3)->default(1);
                $table->decimal('unit_price', 12, 2)->default(0);
                $table->decimal('subtotal', 12, 2)->default(0);
                $table->decimal('discount_amount', 12, 2)->default(0);
                $table->decimal('taxable_amount', 12, 2)->default(0);
                $table->decimal('tax_amount', 12, 2)->default(0);
                $table->decimal('total', 12, 2)->default(0);
                $table->text('notes')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
                $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
                $table->index(['company_id', 'sale_id', 'line_type']);
            });
        }

        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'age')) {
                $table->unsignedSmallInteger('age')->nullable();
            }
            if (! Schema::hasColumn('customers', 'gender')) {
                $table->string('gender', 30)->nullable();
            }
            if (! Schema::hasColumn('customers', 'allergies')) {
                $table->text('allergies')->nullable();
            }
            if (! Schema::hasColumn('customers', 'prescribing_doctor')) {
                $table->string('prescribing_doctor', 150)->nullable();
            }
            if (! Schema::hasColumn('customers', 'doctor_registration_no')) {
                $table->string('doctor_registration_no', 100)->nullable();
            }
        });

        Schema::table('pharmacy_batches', function (Blueprint $table) {
            if (! Schema::hasColumn('pharmacy_batches', 'rack_location')) {
                $table->string('rack_location', 100)->nullable()->index();
            }
        });

        Schema::table('pharmacy_prescriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('pharmacy_prescriptions', 'rx_image_url')) {
                $table->text('rx_image_url')->nullable();
            }
            if (! Schema::hasColumn('pharmacy_prescriptions', 'dosage_duration_days')) {
                $table->unsignedSmallInteger('dosage_duration_days')->nullable();
            }
            if (! Schema::hasColumn('pharmacy_prescriptions', 'refill_reminder_at')) {
                $table->dateTime('refill_reminder_at')->nullable()->index();
            }
            if (! Schema::hasColumn('pharmacy_prescriptions', 'refill_reminder_sent_at')) {
                $table->dateTime('refill_reminder_sent_at')->nullable();
            }
        });

        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'follow_up_days')) {
                $table->unsignedSmallInteger('follow_up_days')->nullable();
            }
        });

        Schema::table('repair_tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('repair_tickets', 'diagnostic_fee')) {
                $table->decimal('diagnostic_fee', 12, 2)->default(0);
            }
            if (! Schema::hasColumn('repair_tickets', 'total_amount')) {
                $table->decimal('total_amount', 12, 2)->default(0);
            }
        });

        Schema::table('salon_appointments', function (Blueprint $table) {
            if (! Schema::hasColumn('salon_appointments', 'chair_label')) {
                $table->string('chair_label', 80)->nullable()->index();
            }
            if (! Schema::hasColumn('salon_appointments', 'service_items')) {
                $table->json('service_items')->nullable();
            }
        });

        if (! Schema::hasTable('notification_reminders')) {
            Schema::create('notification_reminders', function (Blueprint $table) {
                $table->id();
                $table->string('company_id')->index();
                $table->string('module_type', 30)->index();
                $table->string('event_type', 80)->index();
                $table->string('reference_type', 120)->nullable();
                $table->unsignedBigInteger('reference_id')->nullable()->index();
                $table->unsignedBigInteger('customer_id')->nullable()->index();
                $table->string('customer_name', 150)->nullable();
                $table->string('recipient', 100);
                $table->json('channels')->nullable();
                $table->text('message');
                $table->json('payload')->nullable();
                $table->dateTime('scheduled_at')->index();
                $table->dateTime('sent_at')->nullable();
                $table->string('status', 20)->default('scheduled')->index();
                $table->unsignedSmallInteger('attempts')->default(0);
                $table->text('last_error')->nullable();
                $table->timestamps();

                $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
                $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
                $table->index(['status', 'scheduled_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_reminders');
        Schema::dropIfExists('sale_items');

        $this->dropColumns('salon_appointments', ['chair_label', 'service_items']);
        $this->dropColumns('repair_tickets', ['diagnostic_fee', 'total_amount']);
        $this->dropColumns('products', ['follow_up_days']);
        $this->dropColumns('pharmacy_prescriptions', ['rx_image_url', 'dosage_duration_days', 'refill_reminder_at', 'refill_reminder_sent_at']);
        $this->dropColumns('pharmacy_batches', ['rack_location']);
        $this->dropColumns('customers', ['age', 'gender', 'allergies', 'prescribing_doctor', 'doctor_registration_no']);
        $this->dropColumns('sales', ['module_type', 'reference_ticket_id', 'doctor_name', 'stylist_ids']);
        $this->dropColumns('companies', ['repair_prefix', 'prescription_prefix', 'salon_prefix']);
    }

    /** @param list<string> $columns */
    private function dropColumns(string $tableName, array $columns): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        $existing = array_values(array_filter($columns, fn (string $column) => Schema::hasColumn($tableName, $column)));
        if ($existing !== []) {
            Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn($existing));
        }
    }
};
