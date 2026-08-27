<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasLegacyStringId;
use Illuminate\Database\Eloquent\Model;

class AiQuery extends Model
{
    use BelongsToCompany, HasLegacyStringId;

    protected $fillable = ['company_id', 'user_id', 'prompt', 'response'];

    public function idPrefix(): string
    {
        return 'aiq_';
    }
}
