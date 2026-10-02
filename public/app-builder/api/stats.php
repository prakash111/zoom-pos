<?php

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/QuotaManager.php';

Auth::requireAuth();

try {
    $stats = QuotaManager::getStatsForLicense(Auth::key(), Auth::plan());
} catch (Throwable $e) {
    $stats = [
        'plan' => Auth::plan() ?: 'regular',
        'limit' => 10,
        'used_this_month' => 0,
        'remaining_this_month' => 10,
        'can_build' => true,
        'is_unlimited' => false,
        'reset_date' => date('Y-m-01 00:00:00', strtotime('+1 month')),
    ];
}
json_out(200, ['success' => true, 'stats' => $stats]);
