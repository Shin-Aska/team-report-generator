<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class SummarizerService
{
    // Two-step daily pipeline: daily1 -> daily2, using {concatenated_report_here}
    public function summarizeDaily(array $entries, string $date, string $daily1Template, string $daily2Template): string
    {
        $concatenated = $this->concatenateEntries($entries);
        // Step 1
        $prompt1 = str_replace('{concatenated_report_here}', $concatenated, $daily1Template);
        $first = $this->callGeminiText($prompt1) ?? $this->callOpenAIText($prompt1);
        if (!$first) {
            return $this->fallbackDaily($entries, $date);
        }
        // Step 2
        $prompt2 = str_replace('{concatenated_report_here}', $first, $daily2Template);
        $second = $this->callGeminiText($prompt2) ?? $this->callOpenAIText($prompt2);
        return $second ?: $first;
    }

    public function summarizeWeekly(array $entries, string $range, string $weeklyTemplate): string
    {
        $concatenated = $this->concatenateEntries($entries);
        $prompt = str_replace('{concatenated_report_here}', $concatenated, $weeklyTemplate);
        $ai = $this->callGeminiText($prompt) ?? $this->callOpenAIText($prompt);
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

    protected function concatenateEntries(array $entries): string
    {
        $parts = [];
        foreach ($entries as $e) {
            $who = $e['user'] ?? 'Unknown';
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

    protected function callOpenAI(string $system, string $user): ?string
    {
        $key = env('OPENAI_API_KEY');
        if (!$key) return null;
        try {
            $resp = Http::withToken($key)
                ->timeout(30)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $user],
                    ],
                    'temperature' => 0.2,
                ]);
            if ($resp->successful()) {
                return $resp->json('choices.0.message.content');
            }
        } catch (\Throwable $e) {
            // Fail silently and fallback
        }
        return null;
    }

    protected function callGemini(string $system, string $user): ?string
    {
        $key = env('GEMINI_API_KEY');
        if (!$key) return null;
        try {
            $model = env('GEMINI_MODEL', 'gemini-2.5-flash-preview-05-20');
            $url = 'https://generativelanguage.googleapis.com/v1beta/models/'.urlencode($model).':generateContent?key='.$key;

            // Compose content. Gemini supports system instruction in v1beta via system_instruction.
            // We will include system and user as a single text for broad compatibility.
            $prompt = $system."\n\n".$user;
            $payload = [
                'contents' => [
                    [ 'parts' => [ [ 'text' => $prompt ] ] ]
                ],
            ];
            $resp = Http::timeout(30)
                ->asJson()
                ->withHeaders(['Accept' => 'application/json'])
                ->post($url, $payload);
            if ($resp->successful()) {
                $candidates = $resp->json('candidates');
                if (is_array($candidates) && isset($candidates[0]['content']['parts'][0]['text'])) {
                    return $candidates[0]['content']['parts'][0]['text'];
                }
            }
        } catch (\Throwable $e) {
            // swallow and fallback
        }
        return null;
    }

    // For template-style prompts that contain all instructions in one text.
    protected function callGeminiText(string $text): ?string
    {
        $key = env('GEMINI_API_KEY');
        if (!$key) return null;
        try {
            $model = env('GEMINI_MODEL', 'gemini-2.5-flash-preview-05-20');
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
                    'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
                    'messages' => [ ['role' => 'user', 'content' => $text] ],
                    'temperature' => 0.2,
                ]);
            if ($resp->successful()) {
                return $resp->json('choices.0.message.content');
            }
        } catch (\Throwable $e) { }
        return null;
    }
}
