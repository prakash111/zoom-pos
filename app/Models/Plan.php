<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $primaryKey = 'name';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name', 'display_name', 'billing_cycle', 'duration_days',
        'price', 'currency', 'features', 'limits', 'active', 'is_active',
        'invoice_limit', 'products_limit', 'device_limit', 'staff_limit', 'store_limit', 'extensions',
        'has_hrm_module', 'max_staff_limit',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'limits' => 'array',
            'extensions' => 'array',
            'invoice_limit' => 'integer',
            'products_limit' => 'integer',
            'device_limit' => 'integer',
            'staff_limit' => 'integer',
            'store_limit' => 'integer',
            'has_hrm_module' => 'boolean',
            'max_staff_limit' => 'integer',
            'active' => 'boolean',
            'is_active' => 'boolean',
            'price' => 'decimal:2',
        ];
    }

    public const EXTENSION_LABELS = [
        'leadmanagement' => 'CRM & Leads',
        'crm_leads' => 'CRM & Leads',
        'whatsapp_api' => 'WhatsApp API',
        'custom_domain' => 'Custom Domain',
    ];

    public function getProductLimitAttribute(): int
    {
        return (int) ($this->products_limit ?? data_get($this->limits, 'products', -1));
    }

    public function getInvoiceLimitAttribute($value): int
    {
        return (int) ($value ?? data_get($this->limits, 'invoices', -1));
    }

    public function getDeviceLimitAttribute($value): int
    {
        return (int) ($value ?? data_get($this->limits, 'dispositivos', -1));
    }

    public function getStaffLimitAttribute($value): int
    {
        return (int) ($value ?? data_get($this->limits, 'usuarios', -1));
    }

    public function getEnabledExtensionsAttribute(): array
    {
        $exts = is_array($this->extensions) ? $this->extensions : [];
        return array_values($exts);
    }

    public function getFeatureListAttribute(): array
    {
        $rawFeatures = is_array($this->features)
            ? $this->features
            : (is_string($this->features) ? (json_decode($this->features, true) ?: []) : []);

        $featureLabels = [
            'app_builder_access' => 'Cloud App Builder Included',
            'white_label_custom_branding' => '100% White-Label App Branding',
            'android_web_and_windows_builds' => 'Android, Web & Windows App Builds',
            'cloud_build_history_and_alerts' => 'Build History & Email Notifications',
        ];

        $featureList = [];
        foreach ($rawFeatures as $k => $v) {
            if (is_numeric($k) && is_string($v)) {
                $featureList[] = $v;
            } elseif ($v === true || $v === 1 || $v === '1') {
                $featureList[] = $featureLabels[$k] ?? (is_string($k) ? str_replace('_', ' ', ucfirst($k)) : (string) $v);
            } elseif (is_string($v) && ! empty($v)) {
                $featureList[] = "$k: $v";
            }
        }

        return array_values(array_unique($featureList));
    }

    public function getExtensionLabel(string $key): string
    {
        return self::EXTENSION_LABELS[$key] ?? ucwords(str_replace('_', ' ', $key));
    }
}
