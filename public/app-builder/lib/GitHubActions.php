<?php

class GitHubActions
{
    private string $repo;
    private string $branch;
    private string $token;

    public function __construct()
    {
        $this->repo = (string)setting('github_repo', 'prakash111/zoom-pos');
        $this->branch = (string)setting('github_branch', 'feat/windows-offline-sync');
        $this->token = (string)setting('github_token', getenv('GITHUB_TOKEN') ?: '');
    }

    /**
     * Dispatch GitHub Actions build workflow.
     */
    public function dispatchBuild(string $buildUid, string $platform, array $customConfig): array
    {
        // 1. Sanitize payload sizes to strictly respect GitHub Actions 65,536-byte workflow_dispatch inputs limit.
        // Full resolution master logo & icons are safely transferred via branding_bundle_url.
        $logoBase64 = (string)($customConfig['custom_logo_base64'] ?? '');
        if (strlen($logoBase64) > 8192) {
            $logoBase64 = '';
        }

        $brandingJson = (string)($customConfig['branding_json'] ?? '');
        if (strlen($brandingJson) > 3072) {
            $decoded = json_decode($brandingJson, true);
            if (is_array($decoded)) {
                unset($decoded['custom_logo_base64']);
                $brandingJson = json_encode($decoded, JSON_UNESCAPED_SLASHES);
            }
            if (strlen($brandingJson) > 3072) {
                $brandingJson = '';
            }
        }

        // Primary: Unified app-builder.yml workflow with build_id (the process ID!)
        $inputs = [
            'build_id'             => (string)$buildUid,
            'platform'             => (string)$platform,
            'app_name'             => (string)($customConfig['app_name'] ?? 'Zoom Sales POS'),
            'short_name'           => (string)($customConfig['short_name'] ?? 'ZoomPOS'),
            'package_id'           => (string)($customConfig['package_id'] ?? 'com.zoomnearby.zoompos'),
            'server_url'           => (string)($customConfig['server_url'] ?? 'https://saas.zoomnearby.com'),
            'primary_color'        => (string)($customConfig['primary_color'] ?? '#4F46E5'),
            'custom_logo_base64'   => $logoBase64,
            'custom_source_url'    => (string)($customConfig['custom_source_url'] ?? ''),
            'branding_bundle_url'  => (string)($customConfig['branding_bundle_url'] ?? ''),
            'branding_json'        => $brandingJson,
        ];

        $payload = [
            'ref' => $this->branch,
            'inputs' => $inputs,
        ];

        $endpoint = "https://api.github.com/repos/{$this->repo}/actions/workflows/app-builder.yml/dispatches";
        $res = $this->apiCall('POST', $endpoint, $payload);

        // 2. If dispatch failed (e.g. HTTP 422 input validation), retry with minimal essential inputs (under 500 bytes)
        // keeping the build_id process identifier and branding bundle intact.
        if ($res['code'] !== 204) {
            $minimalInputs = [
                'build_id'            => (string)$buildUid,
                'platform'            => (string)$platform,
                'app_name'            => (string)($customConfig['app_name'] ?? 'Zoom Sales POS'),
                'short_name'          => (string)($customConfig['short_name'] ?? 'ZoomPOS'),
                'package_id'          => (string)($customConfig['package_id'] ?? 'com.zoomnearby.zoompos'),
                'server_url'          => (string)($customConfig['server_url'] ?? 'https://saas.zoomnearby.com'),
                'primary_color'       => (string)($customConfig['primary_color'] ?? '#4F46E5'),
                'branding_bundle_url' => (string)($customConfig['branding_bundle_url'] ?? ''),
            ];
            $retryPayload = [
                'ref' => $this->branch,
                'inputs' => $minimalInputs,
            ];
            $res = $this->apiCall('POST', $endpoint, $retryPayload);
        }

        if ($res['code'] === 204) {
            return [
                'success' => true,
                'message' => 'Cloud compilation workflow triggered successfully.',
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to trigger Cloud Build Engine. (HTTP ' . $res['code'] . ': ' . ($res['json']['message'] ?? 'Network error') . ')',
        ];
    }

    /**
     * Locate and fetch the latest workflow run associated with this build.
     */
    public function findWorkflowRun(string $buildUid, ?int $knownRunId = null): ?array
    {
        if ($knownRunId) {
            $res = $this->apiCall('GET', "https://api.github.com/repos/{$this->repo}/actions/runs/{$knownRunId}");
            if ($res['code'] === 200 && is_array($res['json'])) {
                return $res['json'];
            }
        }

        // Query runs for branch
        $res = $this->apiCall('GET', "https://api.github.com/repos/{$this->repo}/actions/runs?branch={$this->branch}&per_page=15");
        if ($res['code'] === 200 && !empty($res['json']['workflow_runs'])) {
            foreach ($res['json']['workflow_runs'] as $run) {
                if (str_contains($run['display_title'] ?? '', $buildUid) || str_contains($run['name'] ?? '', $buildUid)) {
                    return $run;
                }
            }
            // Fallback: only match recent App Builder runs for this branch (within last 5 minutes)
            foreach ($res['json']['workflow_runs'] as $run) {
                $isAppBuilder = (isset($run['path']) && str_ends_with($run['path'], 'app-builder.yml'))
                             || str_contains($run['name'] ?? '', 'App Builder');
                if ($isAppBuilder && strtotime($run['created_at'] ?? 'now') >= (time() - 300)) {
                    return $run;
                }
            }
        }

        return null;
    }

    /**
     * Download the build artifact zip from GitHub Actions.
     */
    public function downloadArtifact(int $runId, string $buildUid, string $platform): ?array
    {
        // 1. Get artifacts list for run
        $res = $this->apiCall('GET', "https://api.github.com/repos/{$this->repo}/actions/runs/{$runId}/artifacts");
        if ($res['code'] !== 200 || empty($res['json']['artifacts'])) {
            return null;
        }

        $artifact = $res['json']['artifacts'][0]; // Pick primary artifact
        $downloadUrl = $artifact['archive_download_url'] ?? null;
        if (!$downloadUrl) {
            return null;
        }

        // 2. Prepare target local storage
        $artifactDir = __DIR__ . '/../storage/artifacts/' . $buildUid;
        if (!is_dir($artifactDir)) {
            mkdir($artifactDir, 0755, true);
        }

        $extensions = [
            'android' => 'zip',
            'web'     => 'zip',
            'windows' => 'zip',
            'ios'     => 'zip',
        ];
        $ext = $extensions[$platform] ?? 'zip';
        $filename = "zoom-sales-pos-{$platform}-{$buildUid}.{$ext}";
        $localPath = $artifactDir . '/' . $filename;

        // 3. Stream download from GitHub
        $ch = curl_init($downloadUrl);
        $fp = fopen($localPath, 'w+');
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 300,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->token,
                'User-Agent: ZoomNearby-AppBuilder',
            ],
        ]);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        if ($code >= 200 && $code < 300 && file_exists($localPath) && filesize($localPath) > 0) {
            return [
                'filename' => $filename,
                'path' => 'storage/artifacts/' . $buildUid . '/' . $filename,
                'full_path' => $localPath,
                'size' => filesize($localPath),
            ];
        }

        @unlink($localPath);
        return null;
    }

    /**
     * Low-level GitHub REST API call.
     */
    private function apiCall(string $method, string $url, ?array $body = null): array
    {
        $ch = curl_init($url);
        $headers = [
            'Authorization: Bearer ' . $this->token,
            'Accept: application/vnd.github+json',
            'User-Agent: ZoomNearby-AppBuilder',
            'X-GitHub-Api-Version: 2022-11-28',
        ];

        $opts = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
        ];

        if ($body !== null) {
            $json = json_encode($body);
            $headers[] = 'Content-Type: application/json';
            $headers[] = 'Content-Length: ' . strlen($json);
            $opts[CURLOPT_POSTFIELDS] = $json;
        }

        $opts[CURLOPT_HTTPHEADER] = $headers;
        curl_setopt_array($ch, $opts);

        $raw = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'code' => $code,
            'body' => (string)$raw,
            'json' => json_decode((string)$raw, true),
        ];
    }
}
