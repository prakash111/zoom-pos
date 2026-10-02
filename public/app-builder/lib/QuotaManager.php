<?php

class QuotaManager
{
    /**
     * Default monthly limits map by plan identifier.
     * 1 build limit unit = 1 complete Multi-Platform Suite Pack (includes Android, Web, Windows & iOS).
     * -1 = Unlimited
     */
    public static function defaultLimits(): array
    {
        return [
            'trial'        => 1,
            'free'         => 1,
            'starter'      => 3,
            'core'         => 3,
            'basic'        => 3,
            'regular'      => 3,
            'standard'     => 3,
            'business'     => 30,
            'all-in-one'   => 30,
            'pro'          => 30,
            'professional' => 30,
            'extended'     => 30,
            'enterprise'   => 30,
            'unlimited'    => -1,
        ];
    }

    /**
     * Get monthly build limit for a specific Main Core Script License.
     * Tied strictly to the License Manager (regular vs extended), not SaaS pricing.
     */
    public static function getLimitForLicense(?string $licenseKey = '', ?string $licensePlan = 'regular'): int
    {
        $plan = strtolower(trim((string)$licensePlan));
        $licenseKey = trim((string)$licenseKey);

        // 1. Check license record in License Manager for user-specific limit or bundle limit
        if ($licenseKey !== '') {
            try {
                $pdo = db();
                $stmt = $pdo->prepare('SELECT plan, app_builder_monthly_limit, product_slug, bundle_id FROM licenses WHERE license_key = ? LIMIT 1');
                $stmt->execute([$licenseKey]);
                $lic = $stmt->fetch();
                if ($lic) {
                    $prodSlug = strtolower(trim((string)($lic['product_slug'] ?? '')));
                    // Build limit is applicable for core script only, not for modules or extensions
                    if ($prodSlug !== '' && !in_array($prodSlug, ['core', 'main', 'pos', 'zoom-pos'], true)) {
                        return 0;
                    }

                    if (!empty($lic['plan'])) {
                        $plan = strtolower(trim((string)$lic['plan']));
                    }

                    // Priority 1: User-specific build limit (assigned directly per license / per user)
                    if ($lic['app_builder_monthly_limit'] !== null && $lic['app_builder_monthly_limit'] !== '') {
                        return (int)$lic['app_builder_monthly_limit'];
                    }

                    // Priority 2: Extended licenses without explicit override get Unlimited builds (-1)
                    if ($plan === 'extended' || $plan === 'unlimited' || $plan === 'enterprise') {
                        return -1;
                    }

                    // Priority 3: Bundle limit (kept as is for bundle packages)
                    if (!empty($lic['bundle_id'])) {
                        try {
                            $bStmt = $pdo->prepare('SELECT app_builder_limit FROM bundles WHERE id = ? LIMIT 1');
                            $bStmt->execute([$lic['bundle_id']]);
                            $bLimit = $bStmt->fetchColumn();
                            if ($bLimit !== false && $bLimit !== null && $bLimit !== '') {
                                return (int)$bLimit;
                            }
                        } catch (Throwable $e) {}
                    }
                }
            } catch (Throwable $e) {}
        }

        // Extended licenses get Unlimited builds
        if ($plan === 'extended' || $plan === 'unlimited' || $plan === 'enterprise') {
            return -1;
        }

        // 3. Check License Manager settings table for configured plan tier limits
        try {
            $stored = setting('builder_plan_limits');
            if ($stored) {
                $map = json_decode($stored, true);
                if (is_array($map) && isset($map[$plan])) {
                    return (int)$map[$plan];
                }
            }
        } catch (Throwable $e) {}

        $defaults = self::defaultLimits();
        return $defaults[$plan] ?? (int)setting('builder_default_monthly_limit', 10);
    }

    public static function getLimitForPlan(?string $planName = 'regular'): int
    {
        return self::getLimitForLicense('', $planName);
    }

    /**
     * Get usage and limits for the authenticated license.
     * 
     * ARCHITECTURE RULES:
     * 1. One build limit unit = 1 Multi-Platform Suite Pack (includes all selected platforms: Android, Web, Windows & iOS).
     * 2. Only successfully completed builds count against the limit. Failed or cancelled builds are NEVER counted.
     */
    public static function getStatsForLicense(?string $licenseKey = '', ?string $planName = 'regular'): array
    {
        $licenseKey = trim((string)$licenseKey);
        $planName = strtolower(trim((string)$planName)) ?: 'regular';
        $limit = self::getLimitForLicense($licenseKey, $planName);
        $startOfMonth = gmdate('Y-m-01 00:00:00');

        $totalSuites = 0;
        $usedThisMonth = 0;
        $activeThisMonth = 0;
        $successfulSuites = 0;
        $failedSuites = 0;
        $platforms = ['android' => 0, 'web' => 0, 'windows' => 0, 'ios' => 0];
        $lastBuildDate = null;

        try {
            $pdo = db();

            // Total unique build suites triggered all-time
            try {
                $stTotal = $pdo->prepare('SELECT COUNT(DISTINCT COALESCE(batch_id, build_uid)) FROM app_builds WHERE license_key = ?');
                $stTotal->execute([$licenseKey]);
                $totalSuites = (int)$stTotal->fetchColumn();
            } catch (Throwable $e) {
                try {
                    $stTotal = $pdo->prepare('SELECT COUNT(DISTINCT build_uid) FROM app_builds WHERE license_key = ?');
                    $stTotal->execute([$licenseKey]);
                    $totalSuites = (int)$stTotal->fetchColumn();
                } catch (Throwable $e2) {}
            }

            // Successful build suites this calendar month (ONLY COUNT status = "completed")
            try {
                $stCompletedThisMonth = $pdo->prepare('
                    SELECT COUNT(DISTINCT COALESCE(batch_id, build_uid)) 
                    FROM app_builds 
                    WHERE license_key = ? 
                      AND created_at >= ? 
                      AND status = "completed"
                ');
                $stCompletedThisMonth->execute([$licenseKey, $startOfMonth]);
                $usedThisMonth = (int)$stCompletedThisMonth->fetchColumn();
            } catch (Throwable $e) {
                try {
                    $stCompletedThisMonth = $pdo->prepare('
                        SELECT COUNT(DISTINCT build_uid) 
                        FROM app_builds 
                        WHERE license_key = ? 
                          AND created_at >= ? 
                          AND status = "completed"
                    ');
                    $stCompletedThisMonth->execute([$licenseKey, $startOfMonth]);
                    $usedThisMonth = (int)$stCompletedThisMonth->fetchColumn();
                } catch (Throwable $e2) {}
            }

            // Currently in-progress build suites
            try {
                $stActive = $pdo->prepare('
                    SELECT COUNT(DISTINCT COALESCE(batch_id, build_uid)) 
                    FROM app_builds 
                    WHERE license_key = ? 
                      AND created_at >= ? 
                      AND status IN ("queued", "preparing", "building")
                ');
                $stActive->execute([$licenseKey, $startOfMonth]);
                $activeThisMonth = (int)$stActive->fetchColumn();
            } catch (Throwable $e) {}

            // Successful build suites all-time
            try {
                $stSuccess = $pdo->prepare('SELECT COUNT(DISTINCT COALESCE(batch_id, build_uid)) FROM app_builds WHERE license_key = ? AND status = "completed"');
                $stSuccess->execute([$licenseKey]);
                $successfulSuites = (int)$stSuccess->fetchColumn();
            } catch (Throwable $e) {}

            // Failed build suites all-time (for reporting only, never deducted from quota)
            try {
                $stFailed = $pdo->prepare('
                    SELECT COUNT(DISTINCT COALESCE(batch_id, build_uid)) 
                    FROM app_builds 
                    WHERE license_key = ? 
                      AND status = "failed"
                      AND COALESCE(batch_id, build_uid) NOT IN (
                          SELECT DISTINCT COALESCE(b2.batch_id, b2.build_uid) 
                          FROM app_builds b2 
                          WHERE b2.license_key = ? AND b2.status = "completed"
                      )
                ');
                $stFailed->execute([$licenseKey, $licenseKey]);
                $failedSuites = (int)$stFailed->fetchColumn();
            } catch (Throwable $e) {}

            // Platform individual completed binaries breakdown
            try {
                $stPlatforms = $pdo->prepare('SELECT platform, COUNT(*) as count FROM app_builds WHERE license_key = ? AND status = "completed" GROUP BY platform');
                $stPlatforms->execute([$licenseKey]);
                while ($row = $stPlatforms->fetch()) {
                    $p = strtolower($row['platform']);
                    if (isset($platforms[$p])) {
                        $platforms[$p] = (int)$row['count'];
                    }
                }
            } catch (Throwable $e) {}

            // Last build date
            try {
                $stLast = $pdo->prepare('SELECT created_at FROM app_builds WHERE license_key = ? ORDER BY id DESC LIMIT 1');
                $stLast->execute([$licenseKey]);
                $lastBuildDate = $stLast->fetchColumn() ?: null;
            } catch (Throwable $e) {}
        } catch (Throwable $e) {}

        $isUnlimited = ($limit === -1);
        $remaining = $isUnlimited ? -1 : max(0, $limit - $usedThisMonth);
        $canBuild = $isUnlimited || ($remaining > $activeThisMonth);

        return [
            'plan' => $planName,
            'limit' => $limit,
            'is_unlimited' => $isUnlimited,
            'used_this_month' => $usedThisMonth,
            'active_this_month' => $activeThisMonth,
            'remaining_this_month' => $remaining,
            'can_build' => $canBuild,
            'total_builds' => $totalSuites,
            'successful_builds' => $successfulSuites,
            'failed_builds' => $failedSuites,
            'platforms' => $platforms,
            'last_build_date' => $lastBuildDate,
            'reset_date' => gmdate('Y-m-01 00:00:00', strtotime('first day of next month')),
        ];
    }

    /**
     * Enforce quota server-side before starting a build.
     * 1 build request = 1 Multi-Platform Suite Pack (includes all 4 platforms: Android, Web, Windows & iOS).
     */
    public static function checkQuota(?string $licenseKey = '', ?string $planName = 'regular', int $suiteCount = 1): array
    {
        $stats = self::getStatsForLicense((string)$licenseKey, (string)$planName);
        if (!$stats['is_unlimited']) {
            if ($stats['remaining_this_month'] < $suiteCount || !$stats['can_build']) {
                return [
                    'allowed' => false,
                    'message' => "Monthly build limit reached. You have completed {$stats['used_this_month']}/{$stats['limit']} multi-platform suite packs this month. (Failed builds are never counted against your limit). Your limit resets on " . date('M 01, Y', strtotime($stats['reset_date'])) . ". Upgrade your license plan for additional build suites.",
                    'stats' => $stats,
                ];
            }
        }

        return ['allowed' => true, 'stats' => $stats];
    }
}
