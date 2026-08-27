<?php

namespace App\Support;

/**
 * Single source of truth for "is this request running inside the NativePHP
 * desktop app" — there's no config flag for it, so every caller probes the
 * same way: the NativePHP App facade only resolves inside the native runtime.
 */
class Desktop
{
    public static function isRunning(): bool
    {
        if (! class_exists(\Native\Desktop\Facades\App::class)) {
            return false;
        }

        try {
            \Native\Desktop\Facades\App::isHidden();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
