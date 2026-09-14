<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TenantNotificationGateway extends Model
{
    use BelongsToCompany;

    public const CHANNEL_WHATSAPP = 'whatsapp';
    public const CHANNEL_SMS = 'sms';
    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_WEBHOOK = 'custom_webhook';

    public const PROVIDER_META_CLOUD = 'meta_cloud_api';
    public const PROVIDER_TWILIO = 'twilio';
    public const PROVIDER_MSG91 = 'msg91';
    public const PROVIDER_GENERIC_HTTP = 'generic_http';
    public const PROVIDER_SMTP = 'smtp';
    public const PROVIDER_WEBHOOK = 'generic_webhook';

    protected $fillable = [
        'company_id',
        'tenant_id',
        'channel',
        'provider',
        'is_enabled',
        'credentials',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'credentials' => \App\Casts\SafeEncryptedArray::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $model) {
            if (empty($model->tenant_id) && ! empty($model->company_id)) {
                $model->tenant_id = (string) $model->company_id;
            }
        });
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }

    public function scopeForChannel(Builder $query, string $channel): Builder
    {
        return $query->where('channel', $channel);
    }

    public function getCredential(string $key, mixed $default = null): mixed
    {
        $creds = $this->credentials;
        if (! is_array($creds)) {
            return $default;
        }

        return $creds[$key] ?? $default;
    }

    public function setCredential(string $key, mixed $value): self
    {
        $creds = $this->credentials ?? [];
        $creds[$key] = $value;
        $this->credentials = $creds;

        return $this;
    }

    public function isConfigured(): bool
    {
        if (! $this->is_enabled) {
            return false;
        }

        $creds = $this->credentials;
        if (empty($creds) || ! is_array($creds)) {
            return false;
        }

        return match ($this->channel) {
            self::CHANNEL_WHATSAPP => match ($this->provider) {
                self::PROVIDER_TWILIO => filled($creds['account_sid'] ?? null) && filled($creds['auth_token'] ?? null),
                default => filled($creds['phone_number_id'] ?? null) && filled($creds['access_token'] ?? null),
            },
            self::CHANNEL_SMS => match ($this->provider) {
                self::PROVIDER_TWILIO => filled($creds['account_sid'] ?? null) && filled($creds['auth_token'] ?? null),
                self::PROVIDER_MSG91 => filled($creds['auth_key'] ?? null),
                self::PROVIDER_GENERIC_HTTP => filled($creds['url'] ?? null),
                default => false,
            },
            self::CHANNEL_EMAIL => filled($creds['host'] ?? null) && filled($creds['username'] ?? null),
            self::CHANNEL_WEBHOOK => filled($creds['url'] ?? null),
            default => false,
        };
    }
}
