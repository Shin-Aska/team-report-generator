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

        // Require reporter one level up from Laravel app base
        $reporterPath = dirname(base_path()) . DIRECTORY_SEPARATOR . 'DevopsWorkItemReporter.php';
        if (!is_readable($reporterPath)) {
            return [
                'counts' => [],
                'organization_url' => $org,
                'project' => $project,
                'area_paths' => $areaPaths,
                'error' => 'Reporter class not found at ' . $reporterPath,
            ];
        }
        require_once $reporterPath;

        try {
            $reporter = new \DevopsWorkItemReporter(
                organizationUrl: $org,
                personalAccessToken: $pat,
                project: ($project !== null && $project !== '') ? (string)$project : null,
                apiVersion: $apiVersion,
                areaPaths: $areaPaths,
                workItemTypes: $workItemTypes,
                statusBlacklist: $statusBlacklist,
            );
            $counts = $reporter->fetchFilteredCountsByStatePerArea();

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
