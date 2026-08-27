<?php

namespace App\Models;

/**
 * CompanyTranslation model
 * Alias / wrapper around TenantTranslation for backward compatibility.
 */
class CompanyTranslation extends TenantTranslation
{
    // Inherits table 'tenant_translations' and all methods from TenantTranslation
}
