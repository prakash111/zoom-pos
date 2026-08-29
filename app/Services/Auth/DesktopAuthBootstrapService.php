<?php

namespace App\Services\Auth;

use App\Exceptions\DesktopLocalProvisioningException;
use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use App\Services\Sync\DesktopSyncClient;
use App\Services\Sync\DesktopSyncEngine;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * First-login bootstrap for a fresh desktop install. Its local SQLite
 * database starts empty — TenantAuthService::login() can never succeed there
 * until a local copy of the company/plan/user rows exists. This is that
 * one-time (per device) provisioning step: verify credentials against the
 * real server, then hydrate exactly enough locally to let the normal local
 * login flow (and everything downstream that assumes a real Company/User
 * row) work offline from then on — including a locally-hashed password, so
 * subsequent logins on this device never need the network again.
 *
 * Only ever called when App\Support\Desktop::isRunning() and the normal
 * local login attempt has already failed — never touches the web app.
 */
class DesktopAuthBootstrapService
{
    public function __construct(protected DesktopSyncEngine $syncEngine) {}

    public function attemptOnlineBootstrap(string $identifier, string $password): ?User
    {
        $baseUrl = rtrim(config('nativephp.website') ?: 'https://saas.zoomnearby.com', '/');

        try {
            $response = Http::baseUrl($baseUrl)
                ->timeout(10)
                ->acceptJson()
                ->post('/api/v1/pos/auth/login', [
                    'email' => $identifier,
                    'password' => $password,
                ]);
        } catch (\Throwable $e) {
            Log::info('Desktop first-login bootstrap could not reach the server.', ['exception' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful() || ! $response->json('success')) {
            return null;
        }

        $data = $response->json();

        $this->ensureLocalSchemaReady();

        try {
            $user = DB::transaction(function () use ($data, $password, $baseUrl) {
                $company = $this->provisionCompany($data['company'], $data['plan'] ?? null);
                $user = $this->provisionUser($data['user'], $company, $password);

                (new DesktopSyncClient($company, $this->syncEngine))->configure($baseUrl, $data['token']);

                return $user;
            });
        } catch (\Throwable $e) {
            Log::error('Desktop first-login bootstrap failed to provision local records.', ['exception' => $e]);

            // The server just confirmed these credentials are correct — the
            // caller must not report this as "invalid credentials". It's a
            // distinct, locally-fixable problem.
            throw new DesktopLocalProvisioningException(
                'Your credentials were verified with the server, but this device could not finish setting up local data.',
                previous: $e,
            );
        }

        $this->syncNewlyBootstrappedDevice($user);

        return $user;
    }

    /**
     * A brand-new device linked to an EXISTING cloud account has an empty
     * local catalog at this point — provisioning above only ever creates the
     * Company/Plan/User rows, never products/categories/customers/etc. Without
     * this, the user would land on the dashboard and see nothing until the
     * self-rescheduling RunDesktopSyncCycle background job happens to catch up
     * (up to ~30s, and only if the queue worker is already running) — which
     * reads as "my existing products aren't syncing" even though nothing is
     * actually broken, just slow and invisible. Run one cycle synchronously,
     * right here, so the catalog is already local by the time the login
     * request returns. Deliberately best-effort: a slow/flaky network here
     * must not turn a successful credential check into a failed login — the
     * background job will pick up the slack on its own next pass regardless.
     */
    protected function syncNewlyBootstrappedDevice(User $user): void
    {
        $company = Company::withoutGlobalScopes()->find($user->company_id);

        if (! $company) {
            return;
        }

        try {
            (new DesktopSyncClient($company, $this->syncEngine))->runCycle();
        } catch (\Throwable $e) {
            Log::warning('Initial post-bootstrap catalog sync failed — the background sync cycle will retry.', [
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Register a new store on the canonical server, then create the local
     * offline mirror. Desktop registration must never provision only into
     * the per-device SQLite database.
     */
    public function registerOnline(array $payload): User
    {
        $baseUrl = rtrim(config('nativephp.website') ?: 'https://saas.zoomnearby.com', '/');

        try {
            $response = Http::baseUrl($baseUrl)
                ->timeout(30)
                ->acceptJson()
                ->post('/api/v1/pos/auth/register', [
                    'store_name' => $payload['store_name'],
                    'name' => $payload['owner_name'],
                    'slug' => $payload['slug'] ?? null,
                    'custom_domain' => $payload['custom_domain'] ?? null,
                    'email' => $payload['email'],
                    'password' => $payload['password'],
                    'phone' => $payload['phone'] ?? null,
                    'currency' => $payload['currency'] ?? 'USD',
                    'pos_mode' => $payload['pos_mode'] ?? 'general',
                    'plan_name' => $payload['plan_name'] ?? 'trial',
                    'activation_code' => $payload['activation_code'] ?? null,
                ]);
        } catch (\Throwable $e) {
            Log::info('Desktop registration could not reach the server.', ['exception' => $e->getMessage()]);

            throw new \RuntimeException('Could not reach the registration server. Check your internet connection and try again.');
        }

        if (! $response->successful() || ! $response->json('success')) {
            $message = $response->json('error') ?: 'Registration failed. Please try again.';
            $details = collect($response->json('details', []))->flatten()->first();

            throw new \RuntimeException($details ?: $message);
        }

        $data = $response->json();

        $this->ensureLocalSchemaReady();

        try {
            $user = DB::transaction(function () use ($data, $payload, $baseUrl) {
                $company = $this->provisionCompany($data['company'], $data['plan'] ?? null);
                $user = $this->provisionUser($data['user'], $company, $payload['password']);

                (new DesktopSyncClient($company, $this->syncEngine))->configure($baseUrl, $data['token']);

                return $user;
            });
        } catch (\Throwable $e) {
            Log::error('Desktop registration succeeded remotely but local bootstrap failed.', ['exception' => $e]);

            throw new \RuntimeException('Your store was created online, but this device could not finish setup. Please return to Sign In and use the same credentials.');
        }

        $this->syncNewlyBootstrappedDevice($user);

        return $user;
    }

    /**
     * NativeAppServiceProvider::ensureApplicationInitialized() runs
     * `migrate` once on cold start, but that step is best-effort (wrapped in
     * a broad catch so a bad first boot never blocks the app from opening at
     * all) — so it's possible to reach this service with an empty or
     * partially-migrated local database. Provisioning the local mirror is
     * the one place that absolutely depends on that schema existing, so
     * double-check it here and self-heal rather than fail with a confusing
     * "could not finish setup" error that a normal migrate would have
     * avoided entirely.
     */
    protected function ensureLocalSchemaReady(): void
    {
        if (Schema::hasTable('companies') && Schema::hasTable('users') && Schema::hasTable('configurations')) {
            return;
        }

        Log::warning('Desktop local schema was missing required tables at bootstrap time — re-running migrations.');

        Artisan::call('migrate', ['--force' => true]);
    }

    protected function provisionCompany(array $c, ?array $plan): Company
    {
        if ($plan && ! empty($c['plan_name'])) {
            $planRow = Plan::withoutGlobalScopes()->find($c['plan_name']) ?? new Plan;
            $planRow->forceFill($plan)->save();
        }

        $company = Company::withoutGlobalScopes()->find($c['id']) ?? new Company;
        $company->forceFill([
            'id' => $c['id'],
            'name' => $c['name'],
            'trade_name' => $c['trade_name'] ?? $c['name'],
            'slug' => $c['slug'] ?? $company->slug,
            'currency' => $c['currency'] ?? 'USD',
            'currency_symbol' => $c['currency_symbol'] ?? '$',
            'address' => $c['address'] ?? null,
            'phone' => $c['phone'] ?? null,
            // Only reference a plan_name we actually have a local row for —
            // it's a foreign key, and a dangling reference would fail the save.
            'plan_name' => ($plan && ! empty($c['plan_name'])) ? $c['plan_name'] : null,
            'expires_at' => $c['expires_at'] ?? null,
        ])->save();

        return $company;
    }

    protected function provisionUser(array $u, Company $company, string $plainPassword): User
    {
        $user = User::withoutGlobalScopes()->find($u['id']) ?? new User;
        $user->forceFill([
            'id' => $u['id'],
            'company_id' => $company->id,
            'name' => $u['name'],
            'login' => $u['login'] ?: $u['email'],
            'email' => $u['email'],
            'role' => $u['role'] ?? 'cashier',
            // A local hash of the password just verified server-side — this is
            // what makes every login after this one work fully offline.
            'password' => Hash::make($plainPassword),
        ])->save();

        return $user;
    }
}
