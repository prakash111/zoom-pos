<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sdui_modules', function (Blueprint $table) {
            $table->boolean('requires_license')->default(false)->after('installed_at');
            $table->string('license_status', 20)->default('unlicensed')->after('requires_license');
            $table->string('license_key_hash', 255)->nullable()->after('license_status');
            $table->string('license_key_prefix', 16)->nullable()->after('license_key_hash');
            $table->text('license_key_encrypted')->nullable()->after('license_key_prefix');
            $table->string('license_driver', 20)->nullable()->after('license_key_encrypted');
            $table->string('license_buyer', 160)->nullable()->after('license_driver');
            $table->timestamp('license_verified_at')->nullable()->after('license_buyer');
            $table->timestamp('license_expires_at')->nullable()->after('license_verified_at');

            $table->index(['source_type', 'requires_license']);
        });

        // Grandfather every package module already installed on this box: it was
        // activated before licensing existed, so keep it usable on deploy. Only
        // *new* installs (manifest requires_license default true) are gated.
        DB::table('sdui_modules')
            ->where('source_type', 'package')
            ->update([
                'requires_license' => false,
                'license_status' => 'active',
                'license_verified_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('sdui_modules', function (Blueprint $table) {
            $table->dropIndex(['source_type', 'requires_license']);
            $table->dropColumn([
                'requires_license',
                'license_status',
                'license_key_hash',
                'license_key_prefix',
                'license_key_encrypted',
                'license_driver',
                'license_buyer',
                'license_verified_at',
                'license_expires_at',
            ]);
        });
    }
};
