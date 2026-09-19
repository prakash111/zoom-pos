<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_inquiries', function (Blueprint $table) {
            if (! Schema::hasColumn('contact_inquiries', 'custom_fields')) {
                $table->json('custom_fields')->nullable()->after('message');
            }
            if (! Schema::hasColumn('contact_inquiries', 'ip_address')) {
                $table->string('ip_address', 45)->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('contact_inquiries', function (Blueprint $table) {
            if (Schema::hasColumn('contact_inquiries', 'custom_fields')) {
                $table->dropColumn('custom_fields');
            }
            if (Schema::hasColumn('contact_inquiries', 'ip_address')) {
                $table->dropColumn('ip_address');
            }
        });
    }
};
