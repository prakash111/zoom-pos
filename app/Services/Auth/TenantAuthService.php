<?php

namespace App\Services\Auth;

use App\Models\Company;
use App\Models\TenantSession;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TenantAuthService
{
    public const SESSION_TTL_HOURS = 12;

    /**
     * Direct port of the legacy login flow: resolve company (by unique account
     * id when given, else search across all tenants by login/email), verify
     * password, enforce company-suspension + device-limit, mint a token, and
     * record a sessions row. Full rate-limiting is deferred to M3's Access
     * Control work; a constant-shape dummy hash check still runs on a miss to
     * avoid trivially timing out valid vs. invalid logins.
     *
     * @return array{user: User, session: TenantSession, token: string}
     *
     * @throws \RuntimeException
     */
    public function login(string $identifier, string $password, ?string $uniqueAccountId = null, array $meta = []): array
    {
        $company = null;
        if ($uniqueAccountId) {
            $company = Company::query()->where('unique_account_id', $uniqueAccountId)->first();
            if (! $company) {
                $this->dummyHashCheck();
                throw new \RuntimeException('Account not found.');
            }
        }

        $query = User::query()->withoutGlobalScope('company')
            ->where(function ($q) use ($identifier) {
                $q->where('login', $identifier)->orWhere('email', $identifier);
            });

        if ($company) {
            $query->where('company_id', $company->id);
        }

        $user = $query->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            $this->dummyHashCheck();
            throw new \RuntimeException('Invalid credentials.');
        }

        $company ??= Company::query()->find($user->company_id);
        if (! $company || $company->isSuspended()) {
            throw new \RuntimeException('This account is suspended.');
        }

        $this->enforceDeviceLimit($company, $user);

        $token = $this->mintToken($company->id);

        $session = TenantSession::create([
            'token' => $token,
            'user_id' => $user->id,
            'company_id' => $company->id,
            'expires_at' => now()->addHours(self::SESSION_TTL_HOURS),
            'ip' => $meta['ip'] ?? request()?->ip(),
            'user_agent' => $meta['user_agent'] ?? request()?->userAgent(),
        ]);

        return ['user' => $user, 'session' => $session, 'token' => $token];
    }

    public function logout(string $token): void
    {
        TenantSession::query()->where('token', $token)->update(['revoked' => true]);
    }

    public function listSessions(string $userId): Collection
    {
        return TenantSession::query()->active()->where('user_id', $userId)->get();
    }

    public function revokeSession(string $userId, string $token): bool
    {
        return (bool) TenantSession::query()->active()
            ->where('user_id', $userId)
            ->where('token', $token)
            ->update(['revoked' => true]);
    }

    /** Admin-only "switch user" — mints a session for $target tagged with who started it. */
    public function impersonate(string $startedByUserId, User $target): array
    {
        $token = $this->mintToken($target->company_id);

        $session = TenantSession::create([
            'token' => $token,
            'user_id' => $target->id,
            'company_id' => $target->company_id,
            'expires_at' => now()->addHours(self::SESSION_TTL_HOURS),
            'impersonated_by' => $startedByUserId,
        ]);

        return ['token' => $token, 'session' => $session];
    }

    /** Can only ever revoke sessions carrying the impersonation tag — never a normal login. */
    public function endImpersonation(string $token): bool
    {
        return (bool) TenantSession::query()
            ->where('token', $token)
            ->whereNotNull('impersonated_by')
            ->update(['revoked' => true]);
    }

    public function mintToken(string $companyId): string
    {
        return $companyId.'.'.Str::random(64);
    }

    protected function enforceDeviceLimit(Company $company, User $user): void
    {
        $limit = $company->max_devices;
        if (! $limit) {
            return;
        }

        $active = TenantSession::query()->active()->where('company_id', $company->id)->count();
        if ($active >= $limit) {
            throw new \RuntimeException('Device/session limit reached for this plan.');
        }
    }

    protected function dummyHashCheck(): void
    {
        // Constant-shape work on a lookup miss, to avoid an easy timing signal
        // for user enumeration (mirrors the legacy dummy-hash verify).
        Hash::check('dummy-password', '$2y$10$abcdefghijklmnopqrstuuVYYQvxeVqhz3rC6xvOVzYQxU0iM3.Xm');
    }
}
