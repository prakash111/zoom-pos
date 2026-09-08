<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\PlatformSystem;
use App\Models\SduiModule;
use App\Services\License\LicenseService;
use App\Services\Modular\ModulePackageService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

class CheckLicenseStatusCommand extends Command
{
    protected $signature = 'license:check-status
        {--sync : Run immediately (no-op flag, present for symmetry with scheduled runs)}
        {--module= : Limit the module sweep to a single slug}
        {--core-only : Skip modules, re-check only the core installer license}
        {--grace-days=3 : Days past expiry before a module is auto-deactivated}';

    protected $description = 'Re-verify every licensed module and the core installer license against the active driver.';

    /** Consecutive transport failures before a module is deactivated. */
    private const FAIL_STREAK_LIMIT = 3;

    public function handle(ModulePackageService $packages, LicenseService $licenses): int
    {
        $domain = LicenseService::currentDomain();
        $graceDays = max(0, (int) $this->option('grace-days'));
        $changed = [];

        if (! $this->option('core-only')) {
            $query = SduiModule::query()->licenseManaged();
            if ($slug = $this->option('module')) {
                $query->where('slug', $slug);
            }

            foreach ($query->get() as $module) {
                try {
                    $outcome = $this->checkModule($module, $packages, $licenses, $domain, $graceDays);
                    if ($outcome !== null) {
                        $changed[] = $outcome;
                        $this->warn($outcome);
                    }
                } catch (Throwable $e) {
                    $this->error("License check errored for {$module->slug}: {$e->getMessage()}");
                }
            }
        }

        $this->checkCoreLicense($licenses, $domain);

        if ($changed !== []) {
            AuditLog::record('license.check_status_run', null, null, [
                'deactivated' => $changed,
            ]);
        }

        $this->info('License check complete.'.($changed === [] ? '' : ' '.count($changed).' module(s) changed state.'));

        return self::SUCCESS;
    }

    /**
     * @return string|null a human summary line when the module changed state, else null
     */
    private function checkModule(
        SduiModule $module,
        ModulePackageService $packages,
        LicenseService $licenses,
        string $domain,
        int $graceDays,
    ): ?string {
        $streakKey = 'license_module_'.$module->slug.'_fail_streak';
        $key = (string) $module->license_key_encrypted;

        if ($key === '') {
            if ($module->license_status === 'active' || $module->is_active) {
                $packages->clearLicense($module, 'unlicensed', 'No stored license key.');
                if ($module->is_active) {
                    $packages->deactivate($module, null);
                }

                return "{$module->slug}: no stored key — deactivated";
            }

            return null;
        }

        $res = $licenses->verify($key, $module->slug, $domain);

        // Transport failure: driver is configured but the server did not answer
        // definitively. Fail soft for a few days before pulling the module.
        $transportFailure = ! $res['status']
            && str_contains(strtolower($res['message']), 'unreachable');

        if ($transportFailure) {
            $streak = (int) PlatformSystem::get($streakKey, 0) + 1;
            PlatformSystem::set($streakKey, (string) $streak);

            if ($streak < self::FAIL_STREAK_LIMIT) {
                $this->line("  {$module->slug}: license server unreachable ({$streak}/".self::FAIL_STREAK_LIMIT.') — left active');

                return null;
            }

            PlatformSystem::set($streakKey, '0');
            $packages->clearLicense($module, 'revoked', 'License server unreachable for '.$streak.' consecutive checks.');
            if ($module->is_active) {
                $packages->deactivate($module, null);
            }

            return "{$module->slug}: license server unreachable {$streak}× — deactivated";
        }

        // Any definitive answer resets the streak.
        PlatformSystem::set($streakKey, '0');

        if (! $res['status']) {
            $packages->clearLicense($module, 'revoked', $res['message']);
            if ($module->is_active) {
                $packages->deactivate($module, null);
            }

            return "{$module->slug}: license revoked — deactivated ({$res['message']})";
        }

        // Verified. Refresh expiry, honour the grace window.
        $expiresAt = $res['expires_at'] ? Carbon::parse($res['expires_at']) : null;
        $module->forceFill([
            'license_driver' => $res['driver'],
            'license_verified_at' => now(),
            'license_expires_at' => $expiresAt,
        ])->save();

        if ($expiresAt !== null && $expiresAt->clone()->addDays($graceDays)->isPast()) {
            $packages->clearLicense($module, 'expired', 'License expired on '.$expiresAt->toDateString().'.');
            if ($module->is_active) {
                $packages->deactivate($module, null);
            }

            return "{$module->slug}: license expired {$expiresAt->toDateString()} — deactivated";
        }

        if ($module->license_status !== 'active') {
            $module->forceFill(['license_status' => 'active'])->save();
        }

        return null;
    }

    private function checkCoreLicense(LicenseService $licenses, string $domain): void
    {
        $path = storage_path('installed');
        $now = now()->toIso8601String();

        if (! is_file($path)) {
            PlatformSystem::set('core_license_status', 'unknown');
            PlatformSystem::set('core_license_last_checked', $now);
            PlatformSystem::set('core_license_message', 'storage/installed not found.');

            return;
        }

        $license = json_decode((string) file_get_contents($path), true);
        $code = (string) ($license['license']['purchase_code'] ?? $license['purchase_code'] ?? '');

        $res = $licenses->verify($code, 'core', $domain);

        PlatformSystem::set('core_license_status', $res['status'] ? 'ok' : 'warn');
        PlatformSystem::set('core_license_last_checked', $now);
        PlatformSystem::set('core_license_message', $res['message'] ?: ($res['status'] ? 'Core license verified.' : 'Core license could not be verified.'));
        PlatformSystem::set('core_license_expires_at', $res['expires_at'] ?? '');

        // Never gate, disable, or throw on the core license — warning only.
        $res['status']
            ? $this->info('Core license: OK')
            : $this->warn('Core license: WARNING — '.$res['message']);
    }
}
