<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasLegacyStringId;
use Illuminate\Database\Eloquent\Model;

class TenantNotification extends Model
{
    use BelongsToCompany, HasLegacyStringId;

    protected $fillable = ['company_id', 'category', 'title', 'message', 'read_status', 'cta_label', 'cta_url'];

    protected function casts(): array
    {
        return ['read_status' => 'boolean'];
    }

    public function idPrefix(): string
    {
        return 'notif_';
    }
}
