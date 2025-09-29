<?php
declare(strict_types=1);

// report.php — wrapper script that uses DevopsWorkItemReporter with .env settings

// Load .env if present (no extra dependencies)
(function () {
    $envFile = __DIR__ . '/.env';
    if (is_readable($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') { continue; }
            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) { continue; }
            $k = trim($parts[0]);
            $v = trim($parts[1]);
            $v = trim($v, "\"'" );
            if (getenv($k) === false) { putenv("$k=$v"); }
        }
    }
})();

require_once __DIR__ . '/DevopsWorkItemReporter.php';

function parseCsvEnv(string $name, array $default): array {
    $val = getenv($name);
    if ($val === false || trim($val) === '') {
        return $default;
    }
    $parts = array_map('trim', explode(',', $val));
    $parts = array_values(array_filter($parts, fn($s) => $s !== ''));
    return $parts ?: $default;
}

$org = getenv('ORGANIZATION_URL') ?: '';
$pat = getenv('PERSONAL_ACCESS_TOKEN') ?: '';
$project = getenv('ADO_PROJECT') ?: null;
$apiVersion = getenv('ADO_API_VERSION') ?: '7.0';

$areaPaths = parseCsvEnv('ADO_AREA_PATHS', ['iPMC\\Workflow and AI']);
$workItemTypes = parseCsvEnv('ADO_WORK_ITEM_TYPES', ['User Story', 'Bug', 'Improvement']);
$statusBlacklist = parseCsvEnv('ADO_STATUS_BLACKLIST', ['Closed','Closed but not fixed','New','On Hold','Pending','Deferred']);

$isCli = php_sapi_name() === 'cli';

try {
    if ($org === '' || $pat === '') {
        throw new RuntimeException('Missing ORGANIZATION_URL or PERSONAL_ACCESS_TOKEN');
    }

    $reporter = new DevopsWorkItemReporter(
        organizationUrl: $org,
        personalAccessToken: $pat,
        project: $project ?: null,
        apiVersion: $apiVersion,
        areaPaths: $areaPaths,
        workItemTypes: $workItemTypes,
        statusBlacklist: $statusBlacklist,
    );

    // Get filtered results for display
    $filtered = $reporter->fetchFilteredCountsByStatePerArea();

    if ($isCli) {
        foreach ($filtered as $area => $counts) {
            echo "Area Path: {$area}\n";
            if (empty($counts)) {
                echo "  (No non-blacklisted states)\n\n";
                continue;
            }
            $maxStateLen = max(array_map('strlen', array_keys($counts)));
            $maxStateLen = max($maxStateLen, strlen('State'));
            $maxCountLen = max(array_map(fn($c) => strlen((string)$c), array_values($counts)));
            $maxCountLen = max($maxCountLen, strlen('Count'));

            // Header
            printf("  %-" . $maxStateLen . "s | %" . $maxCountLen . "s\n", 'State', 'Count');
            echo "  " . str_repeat('-', $maxStateLen) . "+" . str_repeat('-', $maxCountLen + 2) . "\n";
            // Rows
            foreach ($counts as $state => $count) {
                printf("  %-" . $maxStateLen . "s | %" . $maxCountLen . "d\n", $state, $count);
            }
            echo "\n";
        }
    } else {
        header('Content-Type: text/html; charset=utf-8');
        echo "<!doctype html><html lang=\"en\"><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">";
        echo "<title>Azure DevOps Work Item Report</title>";
        echo "<style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,\"Helvetica Neue\",Arial;line-height:1.4;padding:16px}table{border-collapse:collapse;margin:12px 0;min-width:360px}th,td{border:1px solid #ddd;padding:8px 10px;text-align:left}th{background:#f5f5f5}</style></head><body>";
        echo "<h1>Azure DevOps Work Item Report</h1>";
        foreach ($filtered as $area => $counts) {
            echo '<h3>Area Path: ' . htmlspecialchars($area, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</h3>';
            if (empty($counts)) {
                echo '<p><em>No non-blacklisted states</em></p>';
                continue;
            }
            echo '<table><thead><tr><th>State</th><th>Count</th></tr></thead><tbody>';
            foreach ($counts as $state => $count) {
                echo '<tr><td>' . htmlspecialchars($state, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td><td>' . (int)$count . '</td></tr>';
            }
            echo '</tbody></table>';
        }
        echo "</body></html>";
    }
} catch (Throwable $e) {
    if ($isCli) {
        fwrite(STDERR, $e->getMessage() . "\n");
        exit(1);
    } else {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo $e->getMessage();
    }
}
