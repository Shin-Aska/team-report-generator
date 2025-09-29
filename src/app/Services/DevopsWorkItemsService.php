<?php

namespace App\Services;

class DevopsWorkItemsService
{
    public function getSummary(): array
    {
        $cfg = config('services.azure_devops', []);
        $org = (string)($cfg['organization_url'] ?? env('ORGANIZATION_URL', ''));
        $pat = (string)($cfg['pat'] ?? env('PERSONAL_ACCESS_TOKEN', ''));
        $project = $cfg['project'] ?? env('ADO_PROJECT');
        $apiVersion = (string)($cfg['api_version'] ?? env('ADO_API_VERSION', '7.0'));

        $areaPaths = $this->parseCsv($cfg['area_paths'] ?? env('ADO_AREA_PATHS'), ['iPMC\\Workflow and AI']);
        $workItemTypes = $this->parseCsv($cfg['work_item_types'] ?? env('ADO_WORK_ITEM_TYPES'), ['User Story','Bug','Improvement']);
        $statusBlacklist = $this->parseCsv($cfg['status_blacklist'] ?? env('ADO_STATUS_BLACKLIST'), ['Closed','Closed but not fixed','New','On Hold','Pending','Deferred']);

        if ($org === '' || $pat === '') {
            return [
                'counts' => [],
                'organization_url' => $org,
                'project' => $project,
                'area_paths' => $areaPaths,
                'error' => 'Azure DevOps not configured. Set ORGANIZATION_URL and PERSONAL_ACCESS_TOKEN.'
            ];
        }

        try {
            $counts = $this->fetchFilteredCountsByStatePerArea(
                organizationUrl: $org,
                personalAccessToken: $pat,
                project: ($project !== null && $project !== '') ? (string)$project : null,
                apiVersion: $apiVersion,
                areaPaths: $areaPaths,
                workItemTypes: $workItemTypes,
                statusBlacklist: $statusBlacklist,
            );

            return [
                'counts' => $counts,
                'organization_url' => $org,
                'project' => $project,
                'area_paths' => $areaPaths,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'counts' => [],
                'organization_url' => $org,
                'project' => $project,
                'area_paths' => $areaPaths,
                'error' => $e->getMessage(),
            ];
        }
    }

    // ----------- Internal Azure DevOps helpers (inlined, no external reporter dependency) -----------
    private function fetchCountsByStatePerArea(
        string $organizationUrl,
        string $personalAccessToken,
        ?string $project,
        string $apiVersion,
        array $areaPaths,
        array $workItemTypes
    ): array {
        $all = [];

        $wiqlUrl = $this->buildWiqlUrl($organizationUrl, $project, $apiVersion);
        $batchUrl = $this->buildBatchUrl($organizationUrl, $project, $apiVersion);

        foreach ($areaPaths as $areaPath) {
            $wiql = $this->buildWiql($workItemTypes, $areaPath);
            $wiqlBody = ['query' => $wiql];

            $wiqlResult = $this->adoRequest($personalAccessToken, 'POST', $wiqlUrl, $wiqlBody);
            $workItemsRef = $wiqlResult['workItems'] ?? [];
            $ids = array_values(array_filter(array_map(static fn($w) => $w['id'] ?? null, $workItemsRef), static fn($v) => $v !== null));

            if (empty($ids)) {
                $all[$areaPath] = [];
                continue;
            }

            $states = [];
            $chunkSize = 200;
            for ($i = 0; $i < count($ids); $i += $chunkSize) {
                $chunk = array_slice($ids, $i, $chunkSize);
                $batchBody = [
                    'ids' => $chunk,
                    'fields' => ['System.State']
                ];
                $batchResult = $this->adoRequest($personalAccessToken, 'POST', $batchUrl, $batchBody);
                foreach (($batchResult['value'] ?? []) as $wi) {
                    if (isset($wi['fields']['System.State'])) {
                        $states[] = $wi['fields']['System.State'];
                    }
                }
            }

            $all[$areaPath] = $this->groupCounts($states);
        }

        return $all;
    }

    private function fetchFilteredCountsByStatePerArea(
        string $organizationUrl,
        string $personalAccessToken,
        ?string $project,
        string $apiVersion,
        array $areaPaths,
        array $workItemTypes,
        array $statusBlacklist
    ): array {
        $raw = $this->fetchCountsByStatePerArea($organizationUrl, $personalAccessToken, $project, $apiVersion, $areaPaths, $workItemTypes);
        $filtered = [];
        foreach ($raw as $area => $counts) {
            $filtered[$area] = array_filter(
                $counts,
                static fn($count, $state) => !in_array($state, $statusBlacklist, true),
                ARRAY_FILTER_USE_BOTH
            );
        }
        return $filtered;
    }

    private function buildWiqlUrl(string $organizationUrl, ?string $project, string $apiVersion): string
    {
        $pathPrefix = $project ? ('/' . rawurlencode($project)) : '';
        return rtrim($organizationUrl, '/') . $pathPrefix . '/_apis/wit/wiql?api-version=' . rawurlencode($apiVersion);
    }

    private function buildBatchUrl(string $organizationUrl, ?string $project, string $apiVersion): string
    {
        $pathPrefix = $project ? ('/' . rawurlencode($project)) : '';
        return rtrim($organizationUrl, '/') . $pathPrefix . '/_apis/wit/workitemsbatch?api-version=' . rawurlencode($apiVersion);
    }

    private function buildWiql(array $workItemTypes, string $areaPath): string
    {
        $escapedTypes = array_map(static function ($t) {
            return "'" . str_replace("'", "''", $t) . "'";
        }, $workItemTypes);
        $typesClause = implode(', ', $escapedTypes);
        // Escape quotes and backslashes for WIQL string literal
        $escapedArea = str_replace(["\\", "'"], ["\\\\", "''"], $areaPath);
        return "SELECT [System.Id] FROM WorkItems WHERE [System.WorkItemType] IN ($typesClause) AND [System.AreaPath] = '$escapedArea'";
    }

    private function groupCounts(array $states): array
    {
        $counts = [];
        foreach ($states as $state) {
            if (!isset($counts[$state])) {
                $counts[$state] = 0;
            }
            $counts[$state]++;
        }
        if (!empty($counts)) {
            ksort($counts, SORT_NATURAL | SORT_FLAG_CASE);
        }
        return $counts;
    }

    private function adoRequest(
        string $personalAccessToken,
        string $method,
        string $url,
        ?array $body = null,
        array $headers = []
    ): array {
        $ch = curl_init();
        $auth = base64_encode(':' . $personalAccessToken);
        $defaultHeaders = [
            'Authorization: Basic ' . $auth,
            'Accept: application/json',
        ];
        $json = null;
        if ($body !== null) {
            $json = json_encode($body);
            $defaultHeaders[] = 'Content-Type: application/json';
        }
        $allHeaders = array_merge($defaultHeaders, $headers);

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $allHeaders,
            CURLOPT_TIMEOUT => 60,
        ]);
        if ($json !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($response === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException('cURL error: ' . $err);
        }
        curl_close($ch);
        if ($httpCode >= 400) {
            throw new \RuntimeException("HTTP {$httpCode}: {$response}");
        }
        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Invalid JSON response');
        }
        return $decoded;
    }

    private function parseCsv($value, array $default): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('trim', $value), fn($s) => $s !== ''));
        }
        $s = is_string($value) ? trim($value) : '';
        if ($s === '') {
            return $default;
        }
        $parts = array_map('trim', explode(',', $s));
        $parts = array_values(array_filter($parts, fn($x) => $x !== ''));
        return $parts ?: $default;
    }
}
