<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class SafeEncryptedString implements CastsAttributes
{
    /**
     * Cast the given value from storage safely catching MAC/decryption failures.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            $decrypted = Crypt::decrypt($value);
            return is_string($decrypted) ? $decrypted : (string) $decrypted;
        } catch (\Throwable) {
            try {
                return Crypt::decryptString($value);
            } catch (\Throwable $e) {
                Log::warning("SafeEncryptedString: Decryption failed for [{$key}] on model [" . get_class($model) . "] ID [{$model->getKey()}]: " . $e->getMessage());
                return null;
            }
        }
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if (is_null($value) || $value === '') {
            return null;
        }

        return Crypt::encrypt((string) $value);
    }
}
