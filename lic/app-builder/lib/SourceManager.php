<?php

class SourceManager
{
    const MAX_UPLOAD_BYTES = 104857600; // 100 MB

    /**
     * Get details for Option A (Internal Cloud Source).
     * Does NOT expose GitHub repo URL to customer.
     */
    public static function getLatestSourceInfo(): array
    {
        return [
            'type' => 'latest_github',
            'title' => 'Official Cloud Release (feat/windows-offline-sync)',
            'description' => 'Latest production branch with Windows Offline Sync, Multi-Store, and Tablet/Mobile layouts.',
            'branch' => setting('github_branch', 'feat/windows-offline-sync'),
        ];
    }

    /**
     * Validate and process an uploaded Flutter project ZIP file.
     * Enforces strict Zip Slip path-traversal protection and Flutter project validation.
     */
    public static function processUploadedZip(array $fileUpload, string $buildUid): array
    {
        if (empty($fileUpload['tmp_name']) || !is_uploaded_file($fileUpload['tmp_name'])) {
            return ['success' => false, 'message' => 'No ZIP file was uploaded or upload was corrupted.'];
        }

        if ($fileUpload['size'] > self::MAX_UPLOAD_BYTES) {
            return ['success' => false, 'message' => 'Uploaded ZIP exceeds maximum allowable limit of 100MB.'];
        }

        $origName = $fileUpload['name'] ?? '';
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if ($ext !== 'zip') {
            return ['success' => false, 'message' => 'Only valid .zip archives are supported.'];
        }

        // Target temp extraction workspace
        $tempDir = __DIR__ . '/../storage/temp/' . $buildUid;
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $zipPath = $tempDir . '/source.zip';
        if (!move_uploaded_file($fileUpload['tmp_name'], $zipPath)) {
            return ['success' => false, 'message' => 'Failed to store uploaded ZIP in workspace.'];
        }

        $zip = new ZipArchive();
        $res = $zip->open($zipPath);
        if ($res !== true) {
            self::cleanupDirectory($tempDir);
            return ['success' => false, 'message' => 'Corrupt or unreadable ZIP archive (Error Code: ' . $res . ').'];
        }

        // 1. Path Traversal & Safety Check (Zip Slip guard)
        $hasPubspec = false;
        $fileCount = $zip->numFiles;

        for ($i = 0; $i < $fileCount; $i++) {
            $entryName = $zip->getNameIndex($i);
            
            // Normalize path separators
            $normalized = str_replace('\\', '/', $entryName);

            // Reject path traversal attempts
            if (str_contains($normalized, '../') || str_starts_with($normalized, '/') || str_starts_with($normalized, '..')) {
                $zip->close();
                self::cleanupDirectory($tempDir);
                return ['success' => false, 'message' => 'Malicious or invalid ZIP archive detected (path traversal attempt).'];
            }

            // Check for pubspec.yaml
            if (basename($normalized) === 'pubspec.yaml') {
                $hasPubspec = true;
            }
        }

        if (!$hasPubspec) {
            $zip->close();
            self::cleanupDirectory($tempDir);
            return ['success' => false, 'message' => 'Uploaded archive is not a valid Flutter project (missing pubspec.yaml).'];
        }

        $zip->close();

        // Copy to uploads for external pipeline consumption
        $uploadDir = __DIR__ . '/../storage/uploads';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $publicZipPath = $uploadDir . '/source_' . $buildUid . '.zip';
        copy($zipPath, $publicZipPath);

        return [
            'success' => true,
            'source_type' => 'uploaded_zip',
            'zip_path' => $publicZipPath,
            'path' => $publicZipPath,
            'workspace_path' => $tempDir,
            'filename' => basename($origName),
        ];
    }

    /**
     * Safe directory recursive deletion.
     */
    public static function cleanupDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = array_diff(scandir($dir), ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                self::cleanupDirectory($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
