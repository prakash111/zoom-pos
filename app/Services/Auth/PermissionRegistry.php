<?php

namespace App\Services\Auth;

use App\Models\User;

class PermissionRegistry
{
    public const STOREFRONT_MANAGE = 'storefront.manage';
    public const STOREFRONT_INQUIRIES_VIEW = 'storefront.inquiries.view';
    public const STOREFRONT_INQUIRIES_ACTION = 'storefront.inquiries.action';
    public const GATEWAYS_MANAGE = 'gateways.manage';

    public const DESCRIPTIONS = [
        self::STOREFRONT_MANAGE => 'Manage storefront settings, domain, banner and theme',
        self::STOREFRONT_INQUIRIES_VIEW => 'View customer inquiries submitted on storefront',
        self::STOREFRONT_INQUIRIES_ACTION => 'Update inquiry status, reply or delete inquiries',
        self::GATEWAYS_MANAGE => 'Configure storefront payment gateways and integrations',
    ];

    /**
     * Check if a given user has the specified permission.
     */
    public static function userHasPermission(User $user, string $permission): bool
    {
        if ($user->isPrivilegedRole()) {
            return true;
        }

        $checker = app(PermissionChecker::class);

        if (str_contains($permission, '.')) {
            [$module, $action] = explode('.', $permission, 2);

            return $checker->allows($user, $module, $action);
        }

        return $checker->allows($user, $permission, 'view');
    }
}
