<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class SafeEncryptedArray implements CastsAttributes
{
    /**
     * Cast the given value from storage safely catching MAC/decryption failures.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<mixed>
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        if (empty($value)) {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        try {
            $decrypted = Crypt::decrypt($value);
            if (is_array($decrypted)) {
                return $decrypted;
            }
            if (is_string($decrypted)) {
                $decoded = json_decode($decrypted, true);
                return is_array($decoded) ? $decoded : [];
            }
            return [];
        } catch (\Throwable $e) {
            // Check if stored as raw json
            if (is_string($value)) {
                $decoded = json_decode($value, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
            Log::warning("SafeEncryptedArray: Decryption failed for [{$key}] on model [" . get_class($model) . "] ID [{$model->getKey()}]: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if (is_null($value)) {
            return null;
        }

        return Crypt::encrypt(is_array($value) ? $value : (array) $value);
    }
}
