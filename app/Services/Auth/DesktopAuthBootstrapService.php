<?php

namespace App\Services\Auth;

use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use App\Services\Sync\DesktopSyncClient;
use App\Services\Sync\DesktopSyncEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
    public function __construct(protected DesktopSyncEngine $syncEngine)
    {
    }

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

        try {
            return DB::transaction(function () use ($data, $password, $baseUrl) {
                $company = $this->provisionCompany($data['company'], $data['plan'] ?? null);
                $user = $this->provisionUser($data['user'], $company, $password);

                (new DesktopSyncClient($company, $this->syncEngine))->configure($baseUrl, $data['token']);

                return $user;
            });
        } catch (\Throwable $e) {
            Log::error('Desktop first-login bootstrap failed to provision local records.', ['exception' => $e]);

            return null;
        }
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
