<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'require_customer_verification')) {
                $table->boolean('require_customer_verification')->default(false)->after('storefront_payment_gateways');
            }
            if (! Schema::hasColumn('companies', 'verification_channels')) {
                $table->json('verification_channels')->nullable()->after('require_customer_verification');
            }
            if (! Schema::hasColumn('companies', 'enable_order_notifications')) {
                $table->boolean('enable_order_notifications')->default(false)->after('verification_channels');
            }
            if (! Schema::hasColumn('companies', 'order_notification_channels')) {
                $table->json('order_notification_channels')->nullable()->after('enable_order_notifications');
            }
            if (! Schema::hasColumn('companies', 'order_notification_events')) {
                $table->json('order_notification_events')->nullable()->after('order_notification_channels');
            }
        });

        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'is_verified')) {
                $table->boolean('is_verified')->default(false)->after('auth_token');
            }
            if (! Schema::hasColumn('customers', 'verification_code')) {
                $table->string('verification_code', 20)->nullable()->after('is_verified');
            }
            if (! Schema::hasColumn('customers', 'verification_code_expires_at')) {
                $table->dateTime('verification_code_expires_at')->nullable()->after('verification_code');
            }
            if (! Schema::hasColumn('customers', 'verified_at')) {
                $table->dateTime('verified_at')->nullable()->after('verification_code_expires_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'require_customer_verification',
                'verification_channels',
                'enable_order_notifications',
                'order_notification_channels',
                'order_notification_events',
            ]);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'is_verified',
                'verification_code',
                'verification_code_expires_at',
                'verified_at',
            ]);
        });
    }
};
