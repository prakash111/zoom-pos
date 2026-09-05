<?php

namespace App\Models;

/**
 * Tenant model alias extending Company in multi-tenant contexts.
 *
 * Provides compatibility for tenant-scoped operations, tinker scripts,
 * and navigation menu customizations.
 */
class Tenant extends Company
{
    protected $table = 'companies';
}
