<?php
declare(strict_types=1);

/**
 * DevopsWorkItemReporter
 *
 * Reusable PHP class for Azure DevOps Work Item state summaries, suitable for use in Laravel.
 * Settings are injected via the constructor. No global env or echo/exit side-effects.
 *
 * Example (Laravel controller/service):
 *
 *  use DevopsWorkItemReporter;
 *
 *  $reporter = new DevopsWorkItemReporter(
 *      organizationUrl: (string) config('services.azure_devops.organization_url'),
 *      personalAccessToken: (string) config('services.azure_devops.pat'),
 *      project: config('services.azure_devops.project'),              // optional
 *      apiVersion: config('services.azure_devops.api_version', '7.0'),
 *      areaPaths: ['iPMC\\Workflow and AI'],
 *      workItemTypes: ['User Story', 'Bug', 'Improvement'],
 *      statusBlacklist: ['Closed','Closed but not fixed','New','On Hold','Pending','Deferred']
 *  );
 *
 *  // Full counts per area
 *  $counts = $reporter->fetchCountsByStatePerArea();
 *
 *  // Filtered counts per area (excluded states removed)
 *  $filtered = $reporter->fetchFilteredCountsByStatePerArea();
 *
 *  // Optionally write CSV per area path
 *  $files = $reporter->writeCsvPerArea(storage_path('app/reports'), 'work_items_per_status');
 */
class DevopsWorkItemReporter
{
    private string $organizationUrl;
    private string $personalAccessToken;
    private ?string $project;
    private string $apiVersion;

    /** @var string[] */
    private array $areaPaths;
    /** @var string[] */
    private array $workItemTypes;
    /** @var string[] */
    private array $statusBlacklist;

    /**
     * @param string $organizationUrl      e.g. https://dev.azure.com/yourorg
     * @param string $personalAccessToken  PAT with Work Items (Read)
     * @param string|null $project         Optional Azure DevOps project
     * @param string $apiVersion           e.g. '7.0' or '7.1-preview.1'
     * @param string[] $areaPaths          e.g. ['iPMC\\Workflow and AI']
     * @param string[] $workItemTypes      e.g. ['User Story','Bug','Improvement']
     * @param string[] $statusBlacklist    e.g. ['Closed','New', ...]
     */
    public function __construct(
        string $organizationUrl,
        string $personalAccessToken,
        ?string $project = null,
        string $apiVersion = '7.0',
        array $areaPaths = ['iPMC\\Workflow and AI'],
        array $workItemTypes = ['User Story', 'Bug', 'Improvement'],
        array $statusBlacklist = ['Closed','Closed but not fixed','New','On Hold','Pending','Deferred']
    ) {
        $this->organizationUrl = rtrim($organizationUrl, '/');
        $this->personalAccessToken = $personalAccessToken;
        $this->project = $project !== null && $project !== '' ? $project : null;
        $this->apiVersion = $apiVersion;
        $this->areaPaths = $areaPaths;
        $this->workItemTypes = $workItemTypes;
        $this->statusBlacklist = $statusBlacklist;
    }

    /**
     * Fetch counts of work items per state for each configured area path.
     *
     * @return array<string, array<string,int>> [ areaPath => [ state => count ] ]
     * @throws \RuntimeException on request failures
     */
    public function fetchCountsByStatePerArea(): array
    {
        $all = [];

        $wiqlUrl = $this->buildWiqlUrl();
        $batchUrl = $this->buildBatchUrl();

        foreach ($this->areaPaths as $areaPath) {
            $wiql = $this->buildWiql($this->workItemTypes, $areaPath);
            $wiqlBody = ['query' => $wiql];

            $wiqlResult = $this->adoRequest('POST', $wiqlUrl, $wiqlBody);
            $workItemsRef = $wiqlResult['workItems'] ?? [];
            $ids = array_map(static fn($w) => $w['id'], $workItemsRef);

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
                $batchResult = $this->adoRequest('POST', $batchUrl, $batchBody);
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

    /**
     * Same counts as fetchCountsByStatePerArea(), but filtered to remove blacklisted states.
     *
     * @return array<string, array<string,int>>
     */
    public function fetchFilteredCountsByStatePerArea(): array
    {
        $raw = $this->fetchCountsByStatePerArea();
        $filtered = [];
        foreach ($raw as $area => $counts) {
            $filtered[$area] = array_filter(
                $counts,
                fn($count, $state) => !in_array($state, $this->statusBlacklist, true),
                ARRAY_FILTER_USE_BOTH
            );
        }
        return $filtered;
    }

    /**
     * Writes one CSV per area path. Returns the list of written file paths.
     *
     * @param string $directory Directory to write CSV files to (must be writable)
     * @param string $filenamePrefix e.g. 'work_items_per_status'
     * @param bool $filtered If true, write filtered counts; else write raw counts
     * @return string[] Absolute file paths of written CSV files
     */
    public function writeCsvPerArea(string $directory, string $filenamePrefix = 'work_items_per_status', bool $filtered = false): array
    {
        if (!is_dir($directory)) {
            if (!@mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new \RuntimeException("Failed to create directory: {$directory}");
            }
        }
        if (!is_writable($directory)) {
            throw new \RuntimeException("Directory not writable: {$directory}");
        }

        $data = $filtered ? $this->fetchFilteredCountsByStatePerArea() : $this->fetchCountsByStatePerArea();
        $written = [];

        foreach ($data as $area => $rows) {
            $safe = preg_replace('/[^A-Za-z0-9_\-]/', '_', $area);
            $file = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filenamePrefix . '_' . $safe . '.csv';
            $fh = fopen($file, 'w');
            if ($fh === false) {
                throw new \RuntimeException("Failed to open file for writing: {$file}");
            }
            fputcsv($fh, ['state', 'count']);
            foreach ($rows as $state => $count) {
                fputcsv($fh, [$state, $count]);
            }
            fclose($fh);
            $written[] = $file;
        }

        return $written;
    }

    // ----------- Internals -----------

    private function buildWiqlUrl(): string
    {
        $pathPrefix = $this->project ? ('/' . rawurlencode($this->project)) : '';
        return $this->organizationUrl . $pathPrefix . '/_apis/wit/wiql?api-version=' . rawurlencode($this->apiVersion);
    }

    private function buildBatchUrl(): string
    {
        $pathPrefix = $this->project ? ('/' . rawurlencode($this->project)) : '';
        return $this->organizationUrl . $pathPrefix . '/_apis/wit/workitemsbatch?api-version=' . rawurlencode($this->apiVersion);
    }

    /**
     * Build WIQL for the given types and area path.
     */
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

    /**
     * Group and sort counts by state.
     *
     * @param string[] $states
     * @return array<string,int>
     */
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

    /**
     * Execute an Azure DevOps REST request.
     *
     * @param string $method
     * @param string $url
     * @param array|null $body
     * @param array $headers
     * @return array Decoded JSON
     */
    private function adoRequest(string $method, string $url, ?array $body = null, array $headers = []): array
    {
        $ch = curl_init();
        $auth = base64_encode(':' . $this->personalAccessToken);
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
}
