<?php

namespace Tests\Feature\SuperAdmin;

use App\Providers\ModuleServiceProvider;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression coverage for a real fresh-install 500: ModuleServiceProvider
 * boots on EVERY request (HTTP and console), and used to call
 * Schema::hasTable('sdui_modules') unguarded. Schema::hasTable() itself
 * opens a DB connection — on a brand new install, before the /install
 * wizard's migrate step has run (or before the database is even reachable),
 * that throws instead of returning false, which crashed the whole app on
 * every single page, including /install itself.
 */
class ModuleServiceProviderBootResilienceTest extends TestCase
{
    public function test_boot_does_not_crash_when_the_database_is_unreachable(): void
    {
        // Reproduces exactly what a fresh, unmigrated install looks like to
        // every service provider's boot(): a configured connection pointing
        // at a database that doesn't exist yet.
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => '/nonexistent/path/to/a.sqlite']);
        DB::purge('sqlite');

        // Must not throw — a provider boot failure here 500s every request
        // app-wide, before routing even happens.
        (new ModuleServiceProvider($this->app))->boot();

        $this->assertTrue(true);
    }

    public function test_boot_does_not_crash_when_the_sdui_modules_table_does_not_exist_yet(): void
    {
        // A reachable database that simply hasn't been migrated yet (the
        // moment right before MigrateStep runs) must also be a safe no-op,
        // not just an unreachable one.
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');

        (new ModuleServiceProvider($this->app))->boot();

        $this->assertTrue(true);
    }
}
