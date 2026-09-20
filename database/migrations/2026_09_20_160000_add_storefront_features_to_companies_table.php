<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'store_banner_tag')) {
                $table->string('store_banner_tag')->nullable()->default('SPECIAL STORE DEALS')->after('receipt_format');
            }
            if (! Schema::hasColumn('companies', 'store_banner_title')) {
                $table->string('store_banner_title')->nullable()->default('Grab Up To 50% Off On Selected Products')->after('store_banner_tag');
            }
            if (! Schema::hasColumn('companies', 'store_banner_subtitle')) {
                $table->text('store_banner_subtitle')->nullable()->after('store_banner_title');
            }
            if (! Schema::hasColumn('companies', 'store_banner_cta_text')) {
                $table->string('store_banner_cta_text')->nullable()->default('Shop Now')->after('store_banner_subtitle');
            }
            if (! Schema::hasColumn('companies', 'store_banner_cta_link')) {
                $table->string('store_banner_cta_link')->nullable()->default('#products-section')->after('store_banner_cta_text');
            }
            if (! Schema::hasColumn('companies', 'store_banner_image_url')) {
                $table->string('store_banner_image_url')->nullable()->after('store_banner_cta_link');
            }
            if (! Schema::hasColumn('companies', 'store_banner_is_active')) {
                $table->boolean('store_banner_is_active')->default(true)->after('store_banner_image_url');
            }
            if (! Schema::hasColumn('companies', 'enable_google_login')) {
                $table->boolean('enable_google_login')->default(false)->after('store_banner_is_active');
            }
            if (! Schema::hasColumn('companies', 'google_client_id')) {
                $table->string('google_client_id')->nullable()->after('enable_google_login');
            }
            if (! Schema::hasColumn('companies', 'google_client_secret')) {
                $table->text('google_client_secret')->nullable()->after('google_client_id');
            }
            if (! Schema::hasColumn('companies', 'storefront_payment_gateways')) {
                $table->json('storefront_payment_gateways')->nullable()->after('google_client_secret');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $cols = [
                'store_banner_tag',
                'store_banner_title',
                'store_banner_subtitle',
                'store_banner_cta_text',
                'store_banner_cta_link',
                'store_banner_image_url',
                'store_banner_is_active',
                'enable_google_login',
                'google_client_id',
                'google_client_secret',
                'storefront_payment_gateways',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('companies', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
