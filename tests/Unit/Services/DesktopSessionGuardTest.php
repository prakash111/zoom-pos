<?php

namespace Tests\Unit\Services;

use App\Models\Company;
use App\Models\User;
use App\Services\Auth\DesktopSessionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DesktopSessionGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_clears_file_driver_sessions(): void
    {
        $dir = sys_get_temp_dir().'/desktop-session-guard-test-'.uniqid();
        File::makeDirectory($dir);
        File::put($dir.'/some-session-id', 'serialized-session-data');
        $this->assertCount(1, File::files($dir));

        config(['session.driver' => 'file', 'session.files' => $dir]);

        app(DesktopSessionGuard::class)->clearStaleSessionsOnBoot();

        $this->assertCount(0, File::files($dir));

        File::deleteDirectory($dir);
    }

    public function test_clears_a_genuine_laravel_database_sessions_table(): void
    {
        Schema::create('laravel_style_sessions_test', function ($table) {
            $table->string('id')->primary();
            $table->text('payload');
            $table->integer('last_activity');
        });
        DB::table('laravel_style_sessions_test')->insert([
            'id' => 'sess_1', 'payload' => base64_encode('data'), 'last_activity' => time(),
        ]);

        config(['session.driver' => 'database', 'session.table' => 'laravel_style_sessions_test']);

        app(DesktopSessionGuard::class)->clearStaleSessionsOnBoot();

        $this->assertSame(0, DB::table('laravel_style_sessions_test')->count());

        Schema::dropIfExists('laravel_style_sessions_test');
    }

    public function test_never_touches_this_apps_own_tenant_session_table_even_if_misconfigured_to_the_same_name(): void
    {
        // This app's real `sessions` table is TenantAuthService's bearer-token
        // store (token/user_id/...), not Laravel's session format — if
        // SESSION_DRIVER=database pointed at it (e.g. via .env.example's
        // default), clearing must recognize the schema mismatch and skip it
        // rather than deleting real business data.
        $this->assertTrue(Schema::hasTable('sessions'));
        $this->assertFalse(Schema::hasColumn('sessions', 'payload'));

        $company = Company::create([
            'name' => 'Guard Test Co', 'slug' => 'guard-test-co', 'email' => 'owner@guardtest.com',
            'country' => 'US', 'currency' => 'USD', 'currency_symbol' => '$', 'document' => 'US-1',
        ]);
        $user = User::factory()->create(['company_id' => $company->id, 'password' => Hash::make('secret')]);

        DB::table('sessions')->insert([
            'token' => 'tok_test', 'user_id' => $user->id, 'company_id' => $company->id,
            'expires_at' => now()->addHour(), 'revoked' => false,
        ]);

        config(['session.driver' => 'database', 'session.table' => 'sessions']);

        app(DesktopSessionGuard::class)->clearStaleSessionsOnBoot();

        $this->assertSame(1, DB::table('sessions')->count());
    }
}
