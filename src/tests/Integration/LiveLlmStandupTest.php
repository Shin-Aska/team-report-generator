<?php

namespace Tests\Integration;

use App\Services\SummarizerService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Exercise the real daily prompt pipeline against explicitly enabled LLM APIs.
 *
 * These tests are opt-in because they consume paid API quota and require network
 * access. Set RUN_LIVE_LLM_TESTS=1 plus the provider key before running the
 * `live-llm` group. Unlike the production fallback chain, each case calls only
 * the named provider so a passing result proves that provider was exercised.
 */
#[Group('live-llm')]
class LiveLlmStandupTest extends TestCase
{
    /**
     * Return the providers covered by the standup prompt acceptance test.
     *
     * @return array<string, array{string, string}>
     */
    public static function providerCredentials(): array
    {
        return [
            'Gemini 2.5 Pro' => ['gemini', 'GEMINI_API_KEY'],
            'Mistral' => ['mistral', 'MISTRAL_API_KEY'],
        ];
    }

    /**
     * Verify both daily prompt stages using a real response from one provider.
     */
    #[DataProvider('providerCredentials')]
    public function test_live_provider_obeys_daily_prompt_contracts(string $engine, string $credential): void
    {
        if (env('RUN_LIVE_LLM_TESTS') !== '1') {
            $this->markTestSkipped('Set RUN_LIVE_LLM_TESTS=1 to consume live LLM API quota.');
        }

        if (! is_string(env($credential)) || trim((string) env($credential)) === '') {
            $this->markTestSkipped("{$credential} is not configured.");
        }

        $client = new LiveLlmProbe;
        $structuredPrompt = $this->fillPrompt(
            file_get_contents(storage_path('app/private/prompts/daily1.md')),
            $this->sampleEntries(),
        );

        $error = null;
        $structured = $client->callOnly($engine, $structuredPrompt, $error);

        $this->assertNotNull($structured, $error ?? "{$engine} returned no structured report.");
        $this->assertStringContainsString('Goals for [Current Month] Bus:', $structured);
        $this->assertStringContainsString('Initiatives:', $structured);
        $this->assertStringContainsString('Others:', $structured);
        $this->assertStringContainsString('Blockers / FYI:', $structured);
        $this->assertStringNotContainsString('```', $structured);
        $this->assertSame(1, preg_match_all('/^- Agent Search\s*$/mi', $structured));

        $spokenPrompt = str_replace(
            '{concatenated_report_here}',
            $structured,
            file_get_contents(storage_path('app/private/prompts/daily2.md')),
        );
        $error = null;
        $spoken = $client->callOnly($engine, $spokenPrompt, $error);

        $this->assertNotNull($spoken, $error ?? "{$engine} returned no spoken report.");
        $this->assertDoesNotMatchRegularExpression('/^#{1,6}\s/m', $spoken);
        $this->assertStringNotContainsString('```', $spoken);
        $this->assertMatchesRegularExpression('/Agent Search/i', $spoken);
        $this->assertMatchesRegularExpression('/block|risk|flag/i', $spoken);
    }

    /**
     * Fill the stage-one placeholders with deterministic acceptance-test data.
     */
    private function fillPrompt(string $template, string $entries): string
    {
        return str_replace(
            ['{concatenated_report_here}', '{bus_projects}', '{bus_entries}'],
            [$entries, 'No bus projects for this month.', '- No bus projects for this month.'],
            $template,
        );
    }

    /**
     * Provide overlapping contributors, a blocker, and an unrelated FYI.
     */
    private function sampleEntries(): string
    {
        return <<<'MARKDOWN'
            [2026-08-21] Alex:
            Agent Search: implemented query caching and opened the change for review. No blockers.

            ---

            [2026-08-22] Sam:
            Agent Search: validated query caching in staging. Release remains blocked because the production service account has not been approved.
            FYI: investigated ticket #90001 and could not reproduce the reported UI issue.
            MARKDOWN;
    }
}

/**
 * Expose strict single-provider calls solely to the live integration test.
 */
class LiveLlmProbe extends SummarizerService
{
    /**
     * Call exactly one provider without the production fallback sequence.
     */
    public function callOnly(string $engine, string $prompt, ?string &$error): ?string
    {
        return match ($engine) {
            'gemini' => $this->callGeminiText($prompt, $error),
            'mistral' => $this->callMistralText($prompt, $error),
            default => null,
        };
    }
}
