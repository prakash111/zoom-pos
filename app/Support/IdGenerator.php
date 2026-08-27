<?php

namespace App\Support;

class IdGenerator
{
    /**
     * Replicates the legacy PHP id shape: prefix + bin2hex(random_bytes(n)).
     * Existing tokens/URLs/support tooling built around ids like "emp_..." /
     * "usr_..." keep working unchanged against the rebuilt app.
     */
    public static function make(string $prefix, int $bytes = 8): string
    {
        return $prefix.bin2hex(random_bytes($bytes));
    }
}
