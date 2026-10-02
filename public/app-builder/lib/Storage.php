<?php

class Storage
{
    public static function artifactsDir(): string
    {
        $dir = __DIR__ . '/../storage/artifacts';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        return $dir;
    }

    public static function uploadsDir(): string
    {
        $dir = __DIR__ . '/../storage/uploads';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        return $dir;
    }

    public static function tempDir(): string
    {
        $dir = __DIR__ . '/../storage/temp';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        return $dir;
    }

    /**
     * Authenticated, secure artifact streaming.
     */
    public static function streamArtifact(array $buildRecord, string $requestingLicenseKey): void
    {
        // 1. Verify ownership
        if (strtoupper($buildRecord['license_key'] ?? '') !== strtoupper($requestingLicenseKey)) {
            http_response_code(403);
            die("Access Denied: You do not own this build artifact.");
        }

        $relPath = $buildRecord['artifact_path'] ?? '';
        if (empty($relPath)) {
            http_response_code(404);
            die("Artifact not yet generated or has expired.");
        }

        $fullPath = realpath(__DIR__ . '/../' . $relPath);
        $allowedBase = realpath(self::artifactsDir());

        // 2. Prevent path traversal
        if (!$fullPath || !str_starts_with($fullPath, $allowedBase) || !file_exists($fullPath)) {
            http_response_code(404);
            die("Artifact file not found on build storage.");
        }

        $filename = $buildRecord['artifact_filename'] ?? basename($fullPath);
        $filesize = filesize($fullPath);

        // 3. Clear buffers
        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . $filesize);

        readfile($fullPath);
        exit;
    }

    /**
     * Cleanup old temp workspaces and expired artifacts (> 30 days).
     */
    public static function pruneExpired(): void
    {
        $cutoff = time() - (30 * 86400); // 30 days
        $tempCutoff = time() - (3600 * 6); // 6 hours for temp workspaces

        // Prune temp
        foreach (glob(self::tempDir() . '/*') as $item) {
            if (is_dir($item) && filemtime($item) < $tempCutoff) {
                SourceManager::cleanupDirectory($item);
            }
        }
    }
}
