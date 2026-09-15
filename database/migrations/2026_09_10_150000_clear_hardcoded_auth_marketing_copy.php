<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The previous migration seeded `auth_headline` / `auth_description` with a
     * hardcoded "Run your business smarter." tagline. Auth marketing copy is
     * now purely Superadmin-authored — null it out so the login screen renders
     * nothing until an admin sets it.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('platform_branding', 'auth_headline')) {
            return;
        }

        DB::table('platform_branding')
            ->where(function ($q) {
                $q->where('auth_headline', 'Run your business smarter.')
                    ->orWhere('auth_description', 'Sales, inventory & orders — all in one place.');
            })
            ->update(['auth_headline' => null, 'auth_description' => null]);
    }

    public function down(): void
    {
        // No-op: we never want the hardcoded copy back.
    }
};
