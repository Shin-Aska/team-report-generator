<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use App\Services\DevopsWorkItemsService;
use App\Services\BusProjectService;

class SummarizerService
{
    /**
     * Two-step daily pipeline: daily1 -> daily2, using {concatenated_report_here}
     *
     * @return array{content: string, isFallback: bool, error: string|null}
     */
    public function summarizeStandup(array $entries, string $date, string $daily1Template, string $daily2Template, ?string $user = null, ?string $engine = null): array
    {
        $concatenated = $this->concatenateEntries($entries, $user);
        if (!$this->hasMeaningfulUpdates($entries)) {
            try {
                $adoSummary = (new DevopsWorkItemsService())->getSummary();
                $ticketsTable = $this->buildTicketsMarkdownTable($adoSummary);
            } catch (\Throwable $e) {
                $ticketsTable = 'No ticket counts available.';
            }

            $output = "# Summary\n\n";
            $output .= "No updates were submitted for {$date}.";
            $output .= "\n\n---\n\n";
            $output .= "# Briefdown\n\n";
            $output .= "No updates were submitted for {$date}.";
            $output .= "\n\n---\n\n## Tickets\n\n" . $ticketsTable;
            return ['content' => $output, 'isFallback' => false, 'error' => null];
        }
        // Inject bus projects context into the prompt (only extra block besides concatenated reports)
        $busProjects = '';
        try {
            $base = null;
            try {
                $base = Carbon::parse($date);
            } catch (\Throwable $e) {
                $base = Carbon::now();
            }
            $busProjects = (new BusProjectService())->summarizeForPrompt($base);
        } catch (\Throwable $e) {
            $busProjects = 'No bus projects for this month.';
        }

        $busyEntryProject = '';
        try {
            $base = null;
            try {
                $base = Carbon::parse($date);
            } catch (\Throwable $e) {
                $base = Carbon::now();
            }
            $busyEntryProject = (new BusProjectService())->getPreparedTemplate($base);
        } catch (\Throwable $e) {
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
        $lastError = null;
        $first = $this->callWithPreferredEngines($preferredEngines, $prompt1, $lastError);
        if (!$first) {
            return [
                'content' => $this->fallbackDaily($entries, $date),
                'isFallback' => true,
                'error' => $lastError ?? 'All LLM engines failed for step 1.',
            ];
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
        $second = $this->callWithPreferredEngines($preferredEngines, $prompt2, $lastError);

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
        return ['content' => $output, 'isFallback' => false, 'error' => null];
    }

    /**
     * @return array{content: string, isFallback: bool, error: string|null}
     */
    public function summarizeWeekly(array $entries, string $range, string $weeklyTemplate, ?string $engine = null): array
    {
        if (!$this->hasMeaningfulUpdates($entries)) {
            return [
                'content' => "# Weekly Report\nRange: {$range}\n\nNo updates were submitted for this period.",
                'isFallback' => false,
                'error' => null,
            ];
        }
        $concatenated = $this->concatenateEntries($entries);
        $prompt = str_replace('{concatenated_report_here}', $concatenated, $weeklyTemplate);
        $lastError = null;
        $ai = $this->callWithPreferredEngines($this->resolveEnginePreference($engine), $prompt, $lastError);
        if ($ai) {
            return ['content' => $ai, 'isFallback' => false, 'error' => null];
        }

        // Fallback: naive weekly outline
        $lines = ["# Weekly Report", "Range: {$range}", "", "## Highlights", "- Compiled from team entries.", "", "## Daily Notes"];
        foreach ($entries as $e) {
            $date = $e['date'];
            $name = $e['user'];
            $content = trim(preg_replace('/\s+/', ' ', $e['content']));
            $short = mb_substr($content, 0, 300);
            $lines[] = "- [{$date}] {$name}: {$short}";
        }
        return [
            'content' => implode("\n", $lines),
            'isFallback' => true,
            'error' => $lastError ?? 'All LLM engines failed.',
        ];
    }

    /**
     * Refine the generated standup/weekly report while preserving non-summary sections.
     *
     * @return array{content: string, isFallback: bool, error: string|null}
     */
    public function refineReport(string $markdown, string $mode, ?string $instruction = null, ?string $engine = null): array
    {
        $parts = $this->splitSummarySection($markdown);
        $summary = trim($parts['summary']);
        if ($summary === '') {
            return [
                'content' => $markdown,
                'isFallback' => true,
                'error' => 'Could not find a summary section to refine.',
            ];
        }

        $prompt = $this->buildRefinementPrompt($summary, $mode, $instruction);
        $lastError = null;
        $refined = $this->callWithPreferredEngines($this->resolveEnginePreference($engine), $prompt, $lastError);
        $usedFallback = false;

        if (!$refined) {
            $refined = $this->fallbackRefineSummary($summary, $mode, $instruction);
            if (!$refined) {
                return [
                    'content' => $markdown,
                    'isFallback' => true,
                    'error' => $lastError ?? 'Unable to refine the report.',
                ];
            }
            $usedFallback = true;
        }

        $refined = trim($this->stripMarkdownFences($refined));
        $content = rtrim($parts['prefix']);
        $content .= "\n\n" . $refined;
        if ($parts['suffix'] !== '') {
            $content .= "\n\n" . ltrim($parts['suffix']);
        }

        return [
            'content' => trim($content) . "\n",
            'isFallback' => $usedFallback,
            'error' => $usedFallback ? ($lastError ?? 'AI refinement was unavailable, so a lightweight fallback rewrite was used.') : null,
        ];
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
     * Preserves the first error so the user sees the failure from their chosen engine,
     * not the last fallback in the chain.
     */
    protected function callWithPreferredEngines(array $preferredEngines, string $text, ?string &$lastError = null): ?string
    {
        foreach ($preferredEngines as $engine) {
            $engineError = null;
            $result = match ($engine) {
                'azure' => $this->callAzureFoundryAIText($text, $engineError),
                'openai' => $this->callOpenAIText($text, $engineError),
                'gemini' => $this->callGeminiText($text, $engineError),
                'mistral' => $this->callMistralText($text, $engineError),
                default => null,
            };
            if ($result) {
                return $result;
            }
            if ($engineError && !$lastError) {
                $lastError = $engineError;
            }
        }
        if (!$lastError) {
            $lastError = 'No LLM engine is configured.';
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
                $safeState = str_replace('|', '\\|', (string) $state);
                $lines[] = '| ' . $safeState . ' | ' . (string) $count . ' |';
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
            $date = $e['date'] ?? '';
            $content = trim((string) ($e['content'] ?? ''));
            $parts[] = ($date ? "[{$date}] " : '') . $who . ":\n" . $content;
        }
        return implode("\n\n---\n\n", $parts);
    }

    protected function hasMeaningfulUpdates(array $entries): bool
    {
        foreach ($entries as $e) {
            $content = trim((string) ($e['content'] ?? ''));
            if ($content !== '') {
                return true;
            }
        }
        return false;
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
    protected function llmTimeoutSeconds(): int
    {
        return max(1, (int) env('LLM_TIMEOUT_SECONDS', 600));
    }

    protected function callGeminiText(string $text, ?string &$lastError = null): ?string
    {
        $key = env('GEMINI_API_KEY');
        if (!$key) {
            $lastError = 'Gemini: API key not configured.';
            return null;
        }
        $models = ['gemini-3.1-pro', 'gemini-3-flash-preview'];
        foreach ($models as $model) {
            try {
                $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . urlencode($model) . ':generateContent?key=' . $key;
                $payload = ['contents' => [['parts' => [['text' => $text]]]]];
                $resp = Http::timeout($this->llmTimeoutSeconds())->asJson()->withHeaders(['Accept' => 'application/json'])->post($url, $payload);
                if ($resp->successful()) {
                    $candidates = $resp->json('candidates');
                    if (is_array($candidates) && isset($candidates[0]['content']['parts'][0]['text'])) {
                        return $candidates[0]['content']['parts'][0]['text'];
                    }
                }
                $lastError = 'Gemini: HTTP ' . $resp->status() . ' for model ' . $model . '.';
            } catch (\Throwable $e) {
                $lastError = 'Gemini (' . $model . '): ' . $e->getMessage();
            }
        }
        return null;
    }

    protected function callOpenAIText(string $text, ?string &$lastError = null): ?string
    {
        $key = env('OPENAI_API_KEY');
        if (!$key) {
            $lastError = 'OpenAI: API key not configured.';
            return null;
        }
        try {
            $resp = Http::withToken($key)
                ->timeout($this->llmTimeoutSeconds())
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => env('OPENAI_MODEL', 'gpt-4.1'),
                    'messages' => [['role' => 'user', 'content' => $text]],
                    'temperature' => 0.2,
                ]);
            if ($resp->successful()) {
                return $resp->json('choices.0.message.content');
            }
            $lastError = 'OpenAI: HTTP ' . $resp->status() . '.';
        } catch (\Throwable $e) {
            $lastError = 'OpenAI: ' . $e->getMessage();
        }
        return null;
    }

    protected function callAzureFoundryAIText(string $text, ?string &$lastError = null): ?string
    {
        $endpoint = env('AZURE_ENDPOINT');
        $token = env('AZURE_API_KEY');
        if (!$endpoint || !$token) {
            $lastError = 'Azure: endpoint or API key not configured.';
            return null;
        }

        try {
            $resp = Http::withToken($token)
                ->timeout($this->llmTimeoutSeconds())
                ->asJson()
                ->post($endpoint, [
                    'messages' => [
                        ['role' => 'user', 'content' => $text],
                    ],
                    'max_completion_tokens' => 16384,
                    'reasoning_effort' => 'high',
                    'model' => env('AZURE_MODEL', 'gpt-5-nano'),
                ]);

            if ($resp->successful()) {
                $content = $resp->json('choices.0.message.content');
                if (is_string($content)) {
                    return $content;
                }
            }
            $lastError = 'Azure: HTTP ' . $resp->status() . '.';
        } catch (\Throwable $e) {
            $lastError = 'Azure: ' . $e->getMessage();
        }

        return null;
    }

    protected function callMistralText(string $text, ?string &$lastError = null): ?string
    {
        $key = env('MISTRAL_API_KEY');
        if (!$key) {
            $lastError = 'Mistral: API key not configured.';
            return null;
        }

        try {
            $resp = Http::withToken($key)
                ->timeout($this->llmTimeoutSeconds())
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
            $lastError = 'Mistral: HTTP ' . $resp->status() . '.';
        } catch (\Throwable $e) {
            $lastError = 'Mistral: ' . $e->getMessage();
        }

        return null;
    }

    /**
     * Replace placeholder month in the Briefdown header.
     */
    protected function injectMonth(string $text, string $date): string
    {
        try {
            $base = Carbon::parse($date);
        } catch (\Throwable $e) {
            $base = Carbon::now();
        }
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

    /**
     * @return array{prefix: string, summary: string, suffix: string}
     */
    protected function splitSummarySection(string $markdown): array
    {
        $normalized = str_replace("\r\n", "\n", $markdown);
        if (!preg_match('/^# Summary\s*$/mi', $normalized, $matches, PREG_OFFSET_CAPTURE)) {
            return ['prefix' => trim($normalized), 'summary' => '', 'suffix' => ''];
        }

        $headerPos = $matches[0][1];
        $headerText = $matches[0][0];
        $summaryStart = $headerPos + strlen($headerText);
        $afterHeader = ltrim(substr($normalized, $summaryStart), "\n");

        $boundaryPos = strpos($afterHeader, "\n---\n");
        if ($boundaryPos === false) {
            $boundaryPos = strpos($afterHeader, "\n## ");
        }
        if ($boundaryPos === false) {
            $boundaryPos = strlen($afterHeader);
        }

        return [
            'prefix' => trim(substr($normalized, 0, $summaryStart)),
            'summary' => trim(substr($afterHeader, 0, $boundaryPos)),
            'suffix' => trim(substr($afterHeader, $boundaryPos)),
        ];
    }

    protected function buildRefinementPrompt(string $summary, string $mode, ?string $instruction = null): string
    {
        $base = [
            'You are refining the spoken standup summary section of an internal engineering report.',
            'Rewrite only the provided summary content.',
            'Preserve factual meaning, names, risks, and timeline signal.',
            'Do not invent work, blockers, or stakeholders.',
            'Return plain Markdown only with no surrounding code fences.',
        ];

        $modeInstructions = match ($mode) {
            'shorten' => [
                'Make it shorter and tighter.',
                'Keep it to 2-3 short paragraphs.',
                'Remove repetition and filler.',
            ],
            'bulletize' => [
                'Convert it into concise bullet points.',
                'Use one flat Markdown bullet list.',
                'Keep each bullet to one sentence where possible.',
            ],
            'executive' => [
                'Rewrite it for leadership.',
                'Focus on delivery status, risk, and upcoming milestones.',
                'Keep it crisp and low-noise.',
            ],
            'blockers' => [
                'Emphasize blockers, dependencies, risks, and follow-up owners.',
                'Keep successful work brief and put the risk signal first.',
            ],
            'slack' => [
                'Rewrite it as a Slack-ready team update.',
                'Use a compact, friendly tone.',
                'Prefer bullets and keep it easy to skim.',
            ],
            'custom' => [
                'Apply the user instruction carefully.',
                'If the instruction conflicts with the source facts, preserve the facts.',
                'User instruction: ' . trim((string) $instruction),
            ],
            default => [
                'Make it clearer and easier to skim.',
            ],
        };

        $lines = array_merge($base, $modeInstructions, [
            '',
            'Summary to refine:',
            '```markdown',
            trim($summary),
            '```',
        ]);

        return implode("\n", $lines);
    }

    protected function fallbackRefineSummary(string $summary, string $mode, ?string $instruction = null): ?string
    {
        $clean = preg_replace('/\s+/', ' ', trim($summary));
        if (!$clean) {
            return null;
        }

        return match ($mode) {
            'shorten' => $this->fallbackShorten($summary),
            'bulletize', 'slack' => $this->fallbackBulletize($summary),
            'executive' => $this->fallbackExecutive($summary),
            'blockers' => $this->fallbackBlockers($summary),
            'custom' => $this->fallbackBulletize($summary),
            default => $summary,
        };
    }

    protected function fallbackShorten(string $summary): string
    {
        $paragraphs = preg_split('/\n\s*\n/', trim($summary)) ?: [];
        $paragraphs = array_values(array_filter(array_map('trim', $paragraphs)));
        $paragraphs = array_slice($paragraphs, 0, 2);

        return implode("\n\n", array_map(function (string $paragraph) {
            $sentences = preg_split('/(?<=[.!?])\s+/', preg_replace('/\s+/', ' ', $paragraph)) ?: [];
            return trim(implode(' ', array_slice($sentences, 0, 2)));
        }, $paragraphs));
    }

    protected function fallbackBulletize(string $summary): string
    {
        $paragraphs = preg_split('/\n\s*\n/', trim($summary)) ?: [];
        $bullets = [];

        foreach ($paragraphs as $paragraph) {
            $sentence = trim((string) preg_split('/(?<=[.!?])\s+/', preg_replace('/\s+/', ' ', $paragraph), 2)[0]);
            if ($sentence !== '') {
                $bullets[] = '- ' . $sentence;
            }
        }

        return implode("\n", $bullets);
    }

    protected function fallbackExecutive(string $summary): string
    {
        $bullets = preg_split('/\n/', $this->fallbackBulletize($summary)) ?: [];
        $bullets = array_slice(array_values(array_filter(array_map('trim', $bullets))), 0, 4);
        return implode("\n", $bullets);
    }

    protected function fallbackBlockers(string $summary): string
    {
        $sentences = preg_split('/(?<=[.!?])\s+/', preg_replace('/\s+/', ' ', trim($summary))) ?: [];
        $prioritized = [];

        foreach ($sentences as $sentence) {
            if (preg_match('/block|risk|issue|flag|dependency|staging|failing/i', $sentence)) {
                $prioritized[] = '- ' . trim($sentence);
            }
        }

        if (empty($prioritized) && isset($sentences[0])) {
            $prioritized[] = '- ' . trim($sentences[0]);
        }

        return implode("\n", $prioritized);
    }

    protected function stripMarkdownFences(string $text): string
    {
        $trimmed = trim($text);
        if (str_starts_with($trimmed, '```markdown') && str_ends_with($trimmed, '```')) {
            return trim(substr($trimmed, strlen('```markdown'), -strlen('```')));
        }
        if (str_starts_with($trimmed, '```') && str_ends_with($trimmed, '```')) {
            return trim(substr($trimmed, strlen('```'), -strlen('```')));
        }
        return $trimmed;
    }
}
