<?php

namespace App\Services\Installer;

class EnvironmentWriterService
{
    /**
     * Rewrites the given keys in the .env file in place, preserving every
     * other line. Adds the key at the end of the file if it isn't present yet.
     */
    public function write(array $values): void
    {
        $path = base_path('.env');
        $contents = file_exists($path) ? file_get_contents($path) : '';
        $lines = $contents === '' ? [] : explode("\n", $contents);

        foreach ($values as $key => $value) {
            $escaped = $this->escape($value);
            $pattern = '/^'.preg_quote($key, '/').'=.*/';
            $found = false;

            foreach ($lines as $i => $line) {
                if (preg_match($pattern, $line)) {
                    $lines[$i] = "{$key}={$escaped}";
                    $found = true;
                    break;
                }
            }

            if (! $found) {
                $lines[] = "{$key}={$escaped}";
            }
        }

        file_put_contents($path, implode("\n", $lines));
    }

    protected function escape(string $value): string
    {
        if ($value === '' || preg_match('/\s|#|"/', $value)) {
            return '"'.str_replace('"', '\"', $value).'"';
        }

        return $value;
    }
}
