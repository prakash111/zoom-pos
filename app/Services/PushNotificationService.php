<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class PushNotificationService
{
    /**
     * Dispatch high-priority push notifications to specified users via FCM.
     */
    public static function sendToUsers(array $userIds, string $title, string $body, array $data = []): void
    {
        $userIds = array_values(array_filter(array_unique($userIds)));
        if (empty($userIds)) {
            return;
        }

        $tokens = [];

        // 1. Fetch direct fcm_token from users table if column exists
        if (Schema::hasColumn('users', 'fcm_token')) {
            $userTokens = User::whereIn('id', $userIds)
                ->whereNotNull('fcm_token')
                ->pluck('fcm_token')
                ->filter()
                ->toArray();
            $tokens = array_merge($tokens, $userTokens);
        }

        // 2. Fetch active tokens from push_devices table
        if (Schema::hasTable('push_devices')) {
            $deviceTokens = DB::table('push_devices')
                ->whereIn('user_id', $userIds)
                ->whereNull('revoked_at')
                ->pluck('token')
                ->filter()
                ->toArray();
            $tokens = array_merge($tokens, $deviceTokens);
        }

        $tokens = array_values(array_filter(array_unique($tokens)));

        if (empty($tokens)) {
            Log::info("PushNotificationService: No FCM tokens found for users: " . implode(',', $userIds));
            return;
        }

        $serverKey = config('services.firebase.server_key') ?? env('FCM_SERVER_KEY');
        if (!$serverKey) {
            Log::info("FCM Server Key not set. Simulating push notification: {$title} - {$body} to " . count($tokens) . " device(s).");
            return;
        }

        try {
            Http::withHeaders([
                'Authorization' => 'key=' . $serverKey,
                'Content-Type'  => 'application/json',
            ])->timeout(5)->post('https://fcm.googleapis.com/fcm/send', [
                'registration_ids' => $tokens,
                'notification' => [
                    'title' => $title,
                    'body'  => $body,
                    'sound' => 'default',
                ],
                'data' => array_merge($data, [
                    'title' => $title,
                    'body'  => $body,
                ]),
                'priority' => 'high',
            ]);
        } catch (\Throwable $e) {
            Log::warning("FCM Push dispatch error: " . $e->getMessage());
        }
    }
}
