<?php

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/GitHubActions.php';
require_once __DIR__ . '/Mailer.php';

class BuildManager
{
    /**
     * Create and record a new build in app_builds with complete metadata.
     * Prevents duplicate build records for the same build_uid.
     */
    public static function recordBuild(PDO $pdo, array $data): array
    {
        $buildUid = trim((string)($data['build_uid'] ?? ''));
        if ($buildUid === '') {
            throw new InvalidArgumentException('Build UID is required to record a build.');
        }

        // Prevent duplicate build-history records
        $stmtCheck = $pdo->prepare('SELECT * FROM app_builds WHERE build_uid = ? LIMIT 1');
        $stmtCheck->execute([$buildUid]);
        $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            return $existing;
        }

        $licenseKey = trim((string)($data['license_key'] ?? ''));
        $clientEmail = trim((string)($data['client_email'] ?? ''));
        $platform = strtolower(trim((string)($data['platform'] ?? 'android')));
        $batchId = !empty($data['batch_id']) ? (string)$data['batch_id'] : null;
        $sourceType = !empty($data['source_type']) ? (string)$data['source_type'] : 'latest_github';
        $appName = trim((string)($data['app_name'] ?? 'Zoom Sales POS'));
        $packageId = trim((string)($data['package_id'] ?? 'com.zoomnearby.zoompos'));
        $buildVersion = trim((string)($data['build_version'] ?? '1.0.0')) ?: '1.0.0';
        $serverUrl = trim((string)($data['server_url'] ?? 'https://saas.zoomnearby.com'));
        $primaryColor = trim((string)($data['primary_color'] ?? '#4F46E5'));
        $customLogoPath = !empty($data['custom_logo_path']) ? (string)$data['custom_logo_path'] : null;
        $brandingJson = !empty($data['branding_json']) ? (is_array($data['branding_json']) ? json_encode($data['branding_json']) : (string)$data['branding_json']) : null;
        $artifactFilename = !empty($data['artifact_filename']) ? (string)$data['artifact_filename'] : null;

        // Automatically resolve license_id, order_id, order_reference if not provided
        $licenseId = !empty($data['license_id']) ? (int)$data['license_id'] : null;
        $orderId = !empty($data['order_id']) ? (int)$data['order_id'] : null;
        $orderReference = !empty($data['order_reference']) ? (string)$data['order_reference'] : null;

        if (($licenseId === null || $orderReference === null) && $licenseKey !== '') {
            try {
                $stLic = $pdo->prepare('SELECT id, client_email FROM licenses WHERE UPPER(license_key) = UPPER(?) LIMIT 1');
                $stLic->execute([$licenseKey]);
                $lic = $stLic->fetch(PDO::FETCH_ASSOC);
                if ($lic) {
                    if ($licenseId === null && !empty($lic['id'])) {
                        $licenseId = (int)$lic['id'];
                    }
                    if (empty($clientEmail) && !empty($lic['client_email'])) {
                        $clientEmail = (string)$lic['client_email'];
                    }
                }
            } catch (Throwable $e) {}
        }

        if (($orderId === null || $orderReference === null) && $licenseId !== null) {
            try {
                $stPay = $pdo->prepare('SELECT id, reference, email FROM payments WHERE license_id = ? OR reference = ? LIMIT 1');
                $stPay->execute([$licenseId, $orderReference ?? '']);
                $pay = $stPay->fetch(PDO::FETCH_ASSOC);
                if ($pay) {
                    if ($orderId === null && !empty($pay['id'])) {
                        $orderId = (int)$pay['id'];
                    }
                    if ($orderReference === null && !empty($pay['reference'])) {
                        $orderReference = (string)$pay['reference'];
                    }
                    if (empty($clientEmail) && !empty($pay['email'])) {
                        $clientEmail = (string)$pay['email'];
                    }
                }
            } catch (Throwable $e) {}
        }

        try {
            $insertSql = '
                INSERT INTO app_builds 
                (build_uid, batch_id, license_key, license_id, order_id, order_reference, client_email, platform, source_type, app_name, package_id, build_version, server_url, primary_color, custom_logo_path, branding_json, artifact_filename, status, started_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "queued", UTC_TIMESTAMP())
            ';
            $stmt = $pdo->prepare($insertSql);
            $stmt->execute([
                $buildUid,
                $batchId,
                $licenseKey,
                $licenseId,
                $orderId,
                $orderReference,
                $clientEmail,
                $platform,
                $sourceType,
                $appName,
                $packageId,
                $buildVersion,
                $serverUrl,
                $primaryColor,
                $customLogoPath,
                $brandingJson,
                $artifactFilename,
            ]);
        } catch (Throwable $e) {
            // Fallback for older schemas missing some columns
            try {
                $fallbackSql = '
                    INSERT INTO app_builds 
                    (build_uid, batch_id, license_key, client_email, platform, source_type, app_name, package_id, server_url, primary_color, custom_logo_path, status, started_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "queued", UTC_TIMESTAMP())
                ';
                $pdo->prepare($fallbackSql)->execute([
                    $buildUid,
                    $batchId,
                    $licenseKey,
                    $clientEmail,
                    $platform,
                    $sourceType,
                    $appName,
                    $packageId,
                    $serverUrl,
                    $primaryColor,
                    $customLogoPath,
                ]);
            } catch (Throwable $e2) {
                // If already inserted concurrently, fetch it
                $stFetch = $pdo->prepare('SELECT * FROM app_builds WHERE build_uid = ? LIMIT 1');
                $stFetch->execute([$buildUid]);
                $row = $stFetch->fetch(PDO::FETCH_ASSOC);
                if ($row) return $row;
                throw $e2;
            }
        }

        $stFetch = $pdo->prepare('SELECT * FROM app_builds WHERE build_uid = ? LIMIT 1');
        $stFetch->execute([$buildUid]);
        return $stFetch->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Check status of a single build with Cloud Build Engine (GitHub Actions).
     * Downloads artifacts upon completion and dispatches success/failed email notifications.
     */
    public static function syncSingleBuild(PDO $pdo, string $buildUid, ?string $licenseKey = null): array
    {
        $query = 'SELECT * FROM app_builds WHERE build_uid = ?';
        $params = [$buildUid];
        if (!empty($licenseKey)) {
            $query .= ' AND UPPER(license_key) = UPPER(?)';
            $params[] = $licenseKey;
        }
        $query .= ' LIMIT 1';

        $st = $pdo->prepare($query);
        $st->execute($params);
        $build = $st->fetch(PDO::FETCH_ASSOC);

        if (!$build) {
            return ['success' => false, 'message' => 'Build not found.', 'build' => null, 'updated' => false];
        }

        // If build already completed or failed, return as is
        if (!in_array($build['status'], ['queued', 'preparing', 'building'], true)) {
            return ['success' => true, 'build' => $build, 'updated' => false];
        }

        $gh = new GitHubActions();
        $runId = !empty($build['github_run_id']) ? (int)$build['github_run_id'] : null;
        $run = $gh->findWorkflowRun($buildUid, $runId);

        if (!$run) {
            return ['success' => true, 'build' => $build, 'updated' => false];
        }

        $runId = (int)$run['id'];
        $runStatus = strtolower((string)($run['status'] ?? 'in_progress'));
        $runConclusion = strtolower((string)($run['conclusion'] ?? ''));
        $updated = false;

        // Store github_run_id if missing
        if (empty($build['github_run_id'])) {
            $pdo->prepare('UPDATE app_builds SET github_run_id = ? WHERE id = ?')->execute([$runId, $build['id']]);
            $build['github_run_id'] = $runId;
            $updated = true;
        }

        // Update status to 'building' if run started
        if ($runStatus === 'in_progress' && $build['status'] !== 'building') {
            $pdo->prepare('UPDATE app_builds SET status = "building" WHERE id = ?')->execute([$build['id']]);
            $build['status'] = 'building';
            $updated = true;
        }

        // Handle completed run
        if ($runStatus === 'completed') {
            $duration = !empty($build['started_at']) ? max(1, time() - strtotime($build['started_at'])) : 180;

            if ($runConclusion === 'success') {
                $artifact = $gh->downloadArtifact($runId, $buildUid, $build['platform']);

                $artifactPath = $artifact['path'] ?? null;
                $artifactFilename = $artifact['filename'] ?? ("zoom-sales-pos-{$build['platform']}-{$buildUid}.zip");
                $artifactSize = $artifact['size'] ?? 0;

                $pdo->prepare('
                    UPDATE app_builds 
                    SET status = "completed", 
                        artifact_path = ?, 
                        artifact_filename = ?, 
                        artifact_size_bytes = ?, 
                        build_duration_seconds = ?, 
                        completed_at = UTC_TIMESTAMP()
                    WHERE id = ?
                ')->execute([$artifactPath, $artifactFilename, $artifactSize, $duration, $build['id']]);

                $build['status'] = 'completed';
                $build['artifact_path'] = $artifactPath;
                $build['artifact_filename'] = $artifactFilename;
                $build['artifact_size_bytes'] = $artifactSize;
                $build['build_duration_seconds'] = $duration;
                $build['completed_at'] = gmdate('Y-m-d H:i:s');
                $updated = true;

                // Send build success email (only once per build)
                if (empty($build['email_sent'])) {
                    $host = $_SERVER['HTTP_HOST'] ?? 'saas.zoomnearby.com';
                    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'https';
                    $downloadUrl = "{$scheme}://{$host}/app-builder/download.php?id=" . urlencode($buildUid) . "&key=" . urlencode($build['license_key']);

                    Mailer::sendBuildSuccess($build, $downloadUrl);
                    $pdo->prepare('UPDATE app_builds SET email_sent = 1 WHERE id = ?')->execute([$build['id']]);
                    $build['email_sent'] = 1;
                }
            } else {
                // Compilation failed or was cancelled
                $errMsg = 'Cloud compilation runner finished with conclusion: ' . ($runConclusion ?: 'failed');
                $pdo->prepare('
                    UPDATE app_builds 
                    SET status = "failed", 
                        error_message = ?, 
                        build_duration_seconds = ?, 
                        completed_at = UTC_TIMESTAMP() 
                    WHERE id = ?
                ')->execute([$errMsg, $duration, $build['id']]);

                $build['status'] = 'failed';
                $build['error_message'] = $errMsg;
                $build['build_duration_seconds'] = $duration;
                $build['completed_at'] = gmdate('Y-m-d H:i:s');
                $updated = true;

                // Send build failed email (only once per build)
                if (empty($build['email_sent'])) {
                    Mailer::sendBuildFailed($build, $errMsg);
                    $pdo->prepare('UPDATE app_builds SET email_sent = 1 WHERE id = ?')->execute([$build['id']]);
                    $build['email_sent'] = 1;
                }
            }
        }

        return ['success' => true, 'build' => $build, 'updated' => $updated];
    }

    /**
     * Synchronize all in-progress builds for a given user/license.
     * Prevents builds from getting stuck if the user leaves the page.
     */
    public static function syncBuildsForUser(PDO $pdo, string $licenseKey, ?string $email = null): int
    {
        if (empty($licenseKey) && empty($email)) {
            return 0;
        }

        $where = ['status IN ("queued", "preparing", "building")'];
        $args = [];

        if (!empty($licenseKey) && !empty($email)) {
            $where[] = '(UPPER(license_key) = UPPER(?) OR (client_email = ? AND client_email != ""))';
            $args[] = $licenseKey;
            $args[] = $email;
        } elseif (!empty($licenseKey)) {
            $where[] = 'UPPER(license_key) = UPPER(?)';
            $args[] = $licenseKey;
        } else {
            $where[] = 'client_email = ?';
            $args[] = $email;
        }

        try {
            $sql = 'SELECT build_uid FROM app_builds WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT 10';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($args);
            $activeUids = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

            $syncedCount = 0;
            foreach ($activeUids as $bUid) {
                $res = self::syncSingleBuild($pdo, $bUid, $licenseKey);
                if (!empty($res['updated'])) {
                    $syncedCount++;
                }
            }
            return $syncedCount;
        } catch (Throwable $e) {
            return 0;
        }
    }

    /**
     * Query and enrich build history records for display on builder dashboard & history pages.
     */
    public static function getBuildsForUser(PDO $pdo, string $licenseKey, ?string $email = null, array $filters = [], int $limit = 100): array
    {
        // 1. Sync any active builds in background
        self::syncBuildsForUser($pdo, $licenseKey, $email);

        // 2. Build Query
        $where = [];
        $args = [];

        if (!empty($licenseKey) && !empty($email)) {
            $where[] = '(UPPER(license_key) = UPPER(?) OR (client_email = ? AND client_email != ""))';
            $args[] = $licenseKey;
            $args[] = $email;
        } elseif (!empty($licenseKey)) {
            $where[] = 'UPPER(license_key) = UPPER(?)';
            $args[] = $licenseKey;
        } elseif (!empty($email)) {
            $where[] = 'client_email = ?';
            $args[] = $email;
        } else {
            return [];
        }

        // Filter: Status
        $fStatus = trim((string)($filters['status'] ?? ''));
        if ($fStatus !== '' && in_array($fStatus, ['queued', 'preparing', 'building', 'completed', 'failed', 'cancelled'], true)) {
            $where[] = 'status = ?';
            $args[] = $fStatus;
        }

        // Filter: Platform
        $fPlatform = trim((string)($filters['platform'] ?? ''));
        if ($fPlatform !== '' && in_array($fPlatform, ['android', 'web', 'windows', 'ios'], true)) {
            $where[] = 'platform = ?';
            $args[] = $fPlatform;
        }

        // Filter: Search Query (matches build UID, app name, package, order ref, or version)
        $q = trim((string)($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(build_uid LIKE ? OR app_name LIKE ? OR package_id LIKE ? OR order_reference LIKE ? OR build_version LIKE ?)';
            array_push($args, "%$q%", "%$q%", "%$q%", "%$q%", "%$q%");
        }

        $sql = 'SELECT * FROM app_builds WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT ' . max(1, (int)$limit);
        $st = $pdo->prepare($sql);
        $st->execute($args);
        $builds = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // 3. Enrich records missing license_id, order_reference, or build_version
        $licCache = [];
        $payCache = [];

        foreach ($builds as &$b) {
            $b['build_version'] = !empty($b['build_version']) ? $b['build_version'] : '1.0.0';

            if (empty($b['order_reference']) && !empty($b['license_key'])) {
                $bLicKey = strtoupper($b['license_key']);
                if (!isset($licCache[$bLicKey])) {
                    try {
                        $stL = $pdo->prepare('SELECT id, payment_reference FROM licenses WHERE UPPER(license_key) = ? LIMIT 1');
                        $stL->execute([$bLicKey]);
                        $licCache[$bLicKey] = $stL->fetch(PDO::FETCH_ASSOC) ?: null;
                    } catch (Throwable $e) {
                        $licCache[$bLicKey] = null;
                    }
                }
                if ($licCache[$bLicKey]) {
                    if (empty($b['license_id'])) {
                        $b['license_id'] = $licCache[$bLicKey]['id'] ?? null;
                    }
                    if (empty($b['order_reference']) && !empty($licCache[$bLicKey]['payment_reference'])) {
                        $b['order_reference'] = $licCache[$bLicKey]['payment_reference'];
                    }
                }
            }

            // Fallback display for order / license reference
            if (empty($b['order_reference'])) {
                $b['order_reference'] = !empty($b['order_id']) ? ('ORD-#' . $b['order_id']) : (!empty($b['license_id']) ? ('LIC-#' . $b['license_id']) : 'Standard License');
            }
        }
        unset($b);

        return $builds;
    }
}
