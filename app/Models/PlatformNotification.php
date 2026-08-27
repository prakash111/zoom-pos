<?php

namespace App\Models;

use App\Models\Concerns\HasLegacyStringId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlatformNotification extends Model
{
    use HasLegacyStringId, SoftDeletes;

    protected $fillable = [
        'title', 'message', 'target', 'target_plan', 'cta_label', 'cta_url', 'starts_at', 'ends_at',
    ];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function idPrefix(): string
    {
        return 'pnotif_';
    }
}
