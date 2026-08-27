<?php

namespace App\Models\Concerns;

use App\Support\IdGenerator;

/**
 * For root/platform tables that keep the legacy non-incrementing VARCHAR
 * primary key (e.g. companies.id is embedded directly in every minted
 * tenant bearer token, so it must not become an auto-increment integer).
 */
trait HasLegacyStringId
{
    public function initializeHasLegacyStringId(): void
    {
        $this->incrementing = false;
        $this->keyType = 'string';
    }

    protected static function bootHasLegacyStringId(): void
    {
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = IdGenerator::make($model->idPrefix());
            }
        });
    }

    /**
     * Override in the model, e.g. "emp_", "usr_", "padm_".
     */
    abstract public function idPrefix(): string;
}
