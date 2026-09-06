<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('email_verifications')) {
            Schema::create('email_verifications', function (Blueprint $table) {
                $table->id();
                $table->string('email')->index();
                $table->string('otp_hash');
                $table->timestamp('expires_at');
                $table->timestamps();
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (! Schema::hasColumn('users', 'verification_code')) {
                    $table->string('verification_code')->nullable()->after('email_verified_at');
                }
                if (! Schema::hasColumn('users', 'verification_code_expires_at')) {
                    $table->timestamp('verification_code_expires_at')->nullable()->after('verification_code');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('email_verifications');

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (Schema::hasColumn('users', 'verification_code_expires_at')) {
                    $table->dropColumn('verification_code_expires_at');
                }
                if (Schema::hasColumn('users', 'verification_code')) {
                    $table->dropColumn('verification_code');
                }
            });
        }
    }
};
