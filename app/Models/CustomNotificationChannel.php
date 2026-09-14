<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class CustomNotificationChannel extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'name', 'icon', 'url', 'method', 'payload_format', 'headers',
        'auth_type', 'auth_value', 'payload_template', 'event_types', 'is_active',
    ];

    /**
     * Preset icon keys selectable in the UI when no custom icon URL is set.
     */
    public const ICON_PRESETS = ['webhook', 'slack', 'telegram', 'discord', 'sms', 'bell', 'chat', 'megaphone'];

    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'event_types' => 'array',
            'is_active' => 'boolean',
            'auth_value' => 'encrypted',
        ];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function handlesEvent(string $eventType): bool
    {
        return $this->is_active && in_array($eventType, (array) ($this->event_types ?? []), true);
    }

    /**
     * Resolve the channel's icon to something directly renderable in the UI:
     * an absolute/storage URL when a custom icon was uploaded, otherwise the
     * emoji glyph for its preset key (falling back to the generic webhook icon).
     */
    public function iconDisplay(): string
    {
        $icon = (string) ($this->icon ?: 'webhook');
        if (str_starts_with($icon, 'http://') || str_starts_with($icon, 'https://') || str_starts_with($icon, '/storage/')) {
            return $icon;
        }

        return match ($icon) {
            'slack' => '💬',
            'telegram' => '📨',
            'discord' => '🎮',
            'sms' => '📱',
            'bell' => '🔔',
            'chat' => '🗨️',
            'megaphone' => '📣',
            default => '🔗',
        };
    }

    public function isIconUrl(): bool
    {
        $icon = (string) ($this->icon ?: '');

        return str_starts_with($icon, 'http://') || str_starts_with($icon, 'https://') || str_starts_with($icon, '/storage/');
    }
}
