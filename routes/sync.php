<?php

use App\Http\Controllers\Sync\HealthController;
use App\Http\Controllers\Sync\MysqlSyncController;
use App\Http\Controllers\Sync\PlatformAdminSyncController;
use App\Http\Controllers\Sync\SubscriptionSyncController;
use App\Http\Controllers\Sync\TenantAuthSyncController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Legacy desktop/browser client compatibility layer
|--------------------------------------------------------------------------
| Literal legacy URL paths and `action=` request shapes so the already
| shipped compiled client needs zero changes against this rebuild.
|
| Note: the public "/c/{id}" catalog-share-link route lives in
| routes/tenant.php (alongside the Catalog feature that creates the links),
| not here — it needs the 'web' session middleware group, unlike everything
| else in this file.
*/

Route::any('/api/mysql.php', [MysqlSyncController::class, 'dispatch']);
Route::any('/api/auth.php', [TenantAuthSyncController::class, 'dispatch']);
Route::any('/api/subscription.php', [SubscriptionSyncController::class, 'dispatch']);
Route::any('/api/platform_admin.php', [PlatformAdminSyncController::class, 'dispatch']);
Route::any('/api/health', [HealthController::class, 'index']);
