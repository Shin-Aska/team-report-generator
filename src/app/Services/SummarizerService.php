<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use App\Services\DevopsWorkItemsService;
use App\Services\BusProjectService;

class SummarizerService
{
    // Two-step daily pipeline: daily1 -> daily2, using {concatenated_report_here}
    public function summarizeStandup(array $entries, string $date, string $daily1Template, string $daily2Template, ?string $user = null, ?string $engine = null): string
    {
        $concatenated = $this->concatenateEntries($entries, $user);
        // Inject bus projects context into the prompt (only extra block besides concatenated reports)
        $busProjects = '';
        try {
            $base = null;
            try { $base = Carbon::parse($date); } catch (\Throwable $e) { $base = Carbon::now(); }
            $busProjects = (new BusProjectService())->summarizeForPrompt($base);
        } catch (\Throwable $e) {
            $busProjects = 'No bus projects for this month.';
        }

        $busyEntryProject = '';
        try {
            $base = null;
            try { $base = Carbon::parse($date); } catch (\Throwable $e) { $base = Carbon::now(); }
            $busyEntryProject = (new BusProjectService())->getPreparedTemplate($base);
        } catch(\Throwable $e) {
            $busyEntryProject = '- No bus projects for this month.';
        }

        // Step 1
        $prompt1 = str_replace('{concatenated_report_here}', $concatenated, $daily1Template);
        if (str_contains($prompt1, '{bus_entries}')) {
            $prompt1 = str_replace('{bus_entries}', $busyEntryProject, $prompt1);
        }
        if (str_contains($prompt1, '{bus_projects}')) {
            $prompt1 = str_replace('{bus_projects}', $busProjects, $prompt1);
        }
        $preferredEngines = $this->resolveEnginePreference($engine);
        $first = $this->callWithPreferredEngines($preferredEngines, $prompt1);
        if (!$first) {
            return $this->fallbackDaily($entries, $date);
        }
        // Post-process Briefdown: inject current month; collect tickets for final output
        $first = $this->injectMonth($first, $date);

        // If LLM output is wrapped in ```markdown, remove it
        if (str_starts_with($first, '```markdown') && str_ends_with($first, '```')) {
            $first = substr($first, strlen('```markdown'), -strlen('```'));
        }
        // Also if it is wrapped by code quotes, remove it
        if (str_starts_with($first, '```') && str_ends_with($first, '```')) {
            $first = substr($first, strlen('```'), -strlen('```'));
        }

        try {
            $adoSummary = (new DevopsWorkItemsService())->getSummary();
            $ticketsTable = $this->buildTicketsMarkdownTable($adoSummary);
        } catch (\Throwable $e) {
            $ticketsTable = 'No ticket counts available.';
        }
        // Step 2
        $prompt2 = str_replace('{concatenated_report_here}', $first, $daily2Template);
        $second = $this->callWithPreferredEngines($preferredEngines, $prompt2);

        // Combine $first and $second where it is formatted where $first is the Summary and $second is the Briefdown
        $output = "# Summary\n\n";
        $output .= $second;
        if ($second) {
            $output .= "\n\n---\n\n";
            $output .= "# Briefdown\n\n";
            $output .= $first;
        }
        // Append tickets table at the end
        $output .= "\n\n---\n\n## Tickets\n\n" . $ticketsTable;
        return $output;
    }

    public function summarizeWeekly(array $entries, string $range, string $weeklyTemplate, ?string $engine = null): string
    {
        $concatenated = $this->concatenateEntries($entries);
        $prompt = str_replace('{concatenated_report_here}', $concatenated, $weeklyTemplate);
        $ai = $this->callWithPreferredEngines($this->resolveEnginePreference($engine), $prompt);
        if ($ai) return $ai;

        // Fallback: naive weekly outline
        $lines = ["# Weekly Report", "Range: {$range}", "", "## Highlights", "- Compiled from team entries.", "", "## Daily Notes"];
        foreach ($entries as $e) {
            $date = $e['date'];
            $name = $e['user'];
            $content = trim(preg_replace('/\s+/', ' ', $e['content']));
            $short = mb_substr($content, 0, 300);
            $lines[] = "- [{$date}] {$name}: {$short}";
        }
        return implode("\n", $lines);
    }

    /**
     * Determine the preferred engine order based on the provided hint.
     *
     * @return array<int, string>
     */
    protected function resolveEnginePreference(?string $engine): array
    {
        $normalized = $engine ? strtolower(trim($engine)) : null;
        $engines = ['azure', 'openai', 'gemini', 'mistral'];
        if ($normalized && in_array($normalized, $engines, true)) {
            $order = array_merge([$normalized], array_values(array_diff($engines, [$normalized])));
        } else {
            $order = $engines;
        }
        return $order;
    }

    /**
     * Attempt to call available LLM engines following the given preference list.
     */
    protected function callWithPreferredEngines(array $preferredEngines, string $text): ?string
    {
        foreach ($preferredEngines as $engine) {
            $result = match ($engine) {
                'azure' => $this->callAzureFoundryAIText($text),
                'openai' => $this->callOpenAIText($text),
                'gemini' => $this->callGeminiText($text),
                'mistral' => $this->callMistralText($text),
                default => null,
            };
            if ($result) {
                return $result;
            }
        }
        return null;
    }

    /**
     * Build a Markdown table per area path for tickets.
     */
    protected function buildTicketsMarkdownTable(array $adoSummary): string
    {
        $counts = $adoSummary['counts'] ?? [];
        if (!is_array($counts) || empty($counts)) {
            return 'No ticket counts available.';
        }
        $lines = [];
        foreach ($counts as $area => $stateCounts) {
            $lines[] = 'Area Path: ' . $area;
            $lines[] = '| State | Count |';
            $lines[] = '|:------|------:|';
            foreach ($stateCounts as $state => $count) {
                $safeState = str_replace('|', '\\|', (string)$state);
                $lines[] = '| ' . $safeState . ' | ' . (string)$count . ' |';
            }
            $lines[] = '';
        }
        return implode("\n", $lines);
    }

    protected function concatenateEntries(array $entries, ?string $user = null): string
    {
        $parts = [];
        foreach ($entries as $e) {
            $who = $e['user'] ?? 'Unknown';
            if ($user !== null && isset($e['user']) && $e['user'] === $user) {
                $who = 'Myself';
            }
            $date = $e['date'] ?? '';
            $content = trim((string)($e['content'] ?? ''));
            $parts[] = ($date ? "[{$date}] " : '') . $who . ":\n" . $content;
        }
        return implode("\n\n---\n\n", $parts);
    }

    protected function fallbackDaily(array $entries, string $date): string
    {
        $lines = ["# Daily Report", "Date: {$date}", ""];
        foreach ($entries as $e) {
            $name = $e['user'];
            $content = trim(preg_replace('/\s+/', ' ', $e['content']));
            $short = mb_substr($content, 0, 240);
            $lines[] = "- {$name}: {$short}";
        }
        if (count($entries) === 0) {
            $lines[] = "No entries found for this date.";
        }
        return implode("\n", $lines);
    }

    // For template-style prompts that contain all instructions in one text.
    protected function callGeminiText(string $text): ?string
    {
        $key = env('GEMINI_API_KEY');
        if (!$key) return null;
        $models = ['gemini-2.5-pro', 'gemini-2.5-flash-preview-05-20', 'gemini-2.5-flash-lite'];
        foreach ($models as $model) {
            try {
                $url = 'https://generativelanguage.googleapis.com/v1beta/models/'.urlencode($model).':generateContent?key='.$key;
                $payload = [ 'contents' => [ [ 'parts' => [ [ 'text' => $text ] ] ] ] ];
                $resp = Http::timeout(30)->asJson()->withHeaders(['Accept'=>'application/json'])->post($url, $payload);
                if ($resp->successful()) {
                    $candidates = $resp->json('candidates');
                    if (is_array($candidates) && isset($candidates[0]['content']['parts'][0]['text'])) {
                        return $candidates[0]['content']['parts'][0]['text'];
                    }
                }
            } catch (\Throwable $e) { }
        }
        return null;
    }

    protected function callOpenAIText(string $text): ?string
    {
        $key = env('OPENAI_API_KEY');
        if (!$key) return null;
        try {
            $resp = Http::withToken($key)
                ->timeout(30)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => env('OPENAI_MODEL', 'gpt-4.1'),
                    'messages' => [ ['role' => 'user', 'content' => $text] ],
                    'temperature' => 0.2,
                ]);
            if ($resp->successful()) {
                return $resp->json('choices.0.message.content');
            }
        } catch (\Throwable $e) { }
        return null;
    }

    protected function callAzureFoundryAIText(string $text): ?string
    {
        $endpoint = env('AZURE_ENDPOINT');
        $token = env('AZURE_API_KEY');
        if (!$endpoint || !$token) {
            return null;
        }

        try {
            $resp = Http::withToken($token)
                ->timeout(30)
                ->asJson()
                ->post($endpoint, [
                    'messages' => [
                        ['role' => 'user', 'content' => $text],
                    ],
                    'model' => env('AZURE_MODEL', 'gpt-4.1'),
                ]);

            if ($resp->successful()) {
                $content = $resp->json('choices.0.message.content');
                if (is_string($content)) {
                    return $content;
                }
            }
        } catch (\Throwable $e) {
            // Handle the exception
        }

        return null;
    }

    protected function callMistralText(string $text): ?string
    {
        $key = env('MISTRAL_API_KEY');
        if (!$key) {
            return null;
        }

        try {
            $resp = Http::withToken($key)
                ->timeout(30)
                ->asJson()
                ->post('https://api.mistral.ai/v1/chat/completions', [
                    'model' => env('MISTRAL_MODEL', 'mistral-large-latest'),
                    'messages' => [
                        ['role' => 'user', 'content' => $text],
                    ],
                ]);

            if ($resp->successful()) {
                $content = $resp->json('choices.0.message.content');
                if (is_string($content)) {
                    return $content;
                }
            }
        } catch (\Throwable $e) {
        }

        return null;
    }

    /**
     * Replace placeholder month in the Briefdown header.
     */
    protected function injectMonth(string $text, string $date): string
    {
        try { $base = Carbon::parse($date); } catch (\Throwable $e) { $base = Carbon::now(); }
        $month = $base->format('F');
        return str_replace('Goals for [Current Month] Bus:', 'Goals for ' . $month . ' Bus:', $text);
    }

    /**
     * Ensure the Briefdown contains a Tickets section with the given content.
     * If a Tickets section exists, replace it from its header to the end.
     * Otherwise, append a new Tickets section at the end.
     */
    protected function injectTickets(string $text, string $tickets): string
    {
        $header = 'Tickets:';
        $pos = mb_stripos($text, $header);
        if ($pos !== false) {
            // Replace from header to end
            $prefix = mb_substr($text, 0, $pos);
            return rtrim($prefix) . "\n\n" . $header . "\n" . trim($tickets) . "\n";
        }
        // Append at the end
        return rtrim($text) . "\n\n" . $header . "\n" . trim($tickets) . "\n";
    }
}
