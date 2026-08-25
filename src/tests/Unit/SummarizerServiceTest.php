<?php

namespace Tests\Unit;

use App\Services\SummarizerService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SummarizerServiceTest extends TestCase
{
    public function test_daily_pipeline_uses_low_reasoning_for_spoken_summary(): void
    {
        $service = new SequencedSummarizer([
            "Initiatives:\n- Search\n  - Query caching was validated.",
            'Good morning. Search remains on track after query caching was validated.',
        ]);

        $result = $service->summarizeStandup(
            $this->entries(),
            '2026-08-25',
            '{concatenated_report_here}',
            '{concatenated_report_here}',
            null,
            'azure',
        );

        $this->assertFalse($result['isFallback']);
        $this->assertNull($result['error']);
        $this->assertStringContainsString('# Summary', $result['content']);
        $this->assertStringContainsString('# Briefdown', $result['content']);
        $this->assertSame('low', $service->calls[0]['reasoningEffort']);
        $this->assertSame(16384, $service->calls[0]['maxCompletionTokens']);
        $this->assertSame('low', $service->calls[1]['reasoningEffort']);
        $this->assertSame(8192, $service->calls[1]['maxCompletionTokens']);
    }

    public function test_daily_pipeline_preserves_briefdown_when_spoken_summary_fails(): void
    {
        $structured = "Initiatives:\n- Search\n  - Query caching was validated.";
        $service = new SequencedSummarizer([$structured, null]);
        $service->failure = 'Azure: empty completion (finish_reason=length, reasoning_tokens=16384, completion_tokens=16384).';

        $result = $service->summarizeStandup(
            $this->entries(),
            '2026-08-25',
            '{concatenated_report_here}',
            '{concatenated_report_here}',
            null,
            'azure',
        );

        $this->assertTrue($result['isFallback']);
        $this->assertSame($service->failure, $result['error']);
        $this->assertStringContainsString('Spoken summary generation was unavailable', $result['content']);
        $this->assertStringContainsString('# Briefdown', $result['content']);
        $this->assertStringContainsString($structured, $result['content']);
    }

    public function test_azure_rejects_truncated_content_and_sends_stage_options(): void
    {
        Env::getRepository()->set('AZURE_ENDPOINT', 'https://example.test/openai/v1/chat/completions');
        Env::getRepository()->set('AZURE_API_KEY', 'test-key');
        Env::getRepository()->set('AZURE_AI_MODEL', 'gpt-5-nano-test');

        Http::fake([
            'https://example.test/*' => Http::response([
                'choices' => [[
                    'finish_reason' => 'length',
                    'message' => ['content' => 'Partial spoken summary'],
                ]],
                'usage' => [
                    'completion_tokens' => 8192,
                    'completion_tokens_details' => ['reasoning_tokens' => 8000],
                ],
            ]),
        ]);

        $error = null;
        $result = (new AzureSummarizerProbe)->callAzure('Rewrite this report.', $error, 'low', 8192);

        $this->assertNull($result);
        $this->assertSame(
            'Azure: unusable completion (finish_reason=length, reasoning_tokens=8000, completion_tokens=8192).',
            $error,
        );
        Http::assertSent(fn (Request $request) => $request['model'] === 'gpt-5-nano-test'
            && $request['reasoning_effort'] === 'low'
            && $request['max_completion_tokens'] === 8192);
    }

    public function test_azure_retries_token_exhaustion_with_low_reasoning(): void
    {
        Env::getRepository()->set('AZURE_ENDPOINT', 'https://example.test/openai/v1/chat/completions');
        Env::getRepository()->set('AZURE_API_KEY', 'test-key');
        Env::getRepository()->set('AZURE_AI_MODEL', 'gpt-5-nano-test');

        Http::fakeSequence('https://example.test/*')
            ->push([
                'choices' => [[
                    'finish_reason' => 'length',
                    'message' => ['content' => null],
                ]],
                'usage' => [
                    'completion_tokens' => 16384,
                    'completion_tokens_details' => ['reasoning_tokens' => 16384],
                ],
            ])
            ->push([
                'choices' => [[
                    'finish_reason' => 'stop',
                    'message' => ['content' => 'Complete structured report'],
                ]],
                'usage' => [
                    'completion_tokens' => 1200,
                    'completion_tokens_details' => ['reasoning_tokens' => 800],
                ],
            ]);

        $error = null;
        $result = (new AzureSummarizerProbe)->callAzure('Summarize this report.', $error, 'high', 16384);

        $this->assertSame('Complete structured report', $result);
        $this->assertNull($error);
        Http::assertSentCount(2);
        $efforts = Http::recorded()->map(fn (array $pair) => $pair[0]['reasoning_effort'])->all();
        $this->assertSame(['high', 'low'], $efforts);
    }

    /**
     * @return array<int, array{date: string, user: string, content: string}>
     */
    private function entries(): array
    {
        return [[
            'date' => '2026-08-24',
            'user' => 'Alex',
            'content' => 'Search: query caching was validated in staging.',
        ]];
    }
}

class SequencedSummarizer extends SummarizerService
{
    /** @var array<int, string|null> */
    private array $responses;

    /** @var array<int, array{reasoningEffort: string, maxCompletionTokens: int}> */
    public array $calls = [];

    public ?string $failure = null;

    /**
     * @param  array<int, string|null>  $responses
     */
    public function __construct(array $responses)
    {
        $this->responses = $responses;
    }

    protected function callWithPreferredEngines(
        array $preferredEngines,
        string $text,
        ?string &$lastError = null,
        string $reasoningEffort = 'high',
        int $maxCompletionTokens = 16384,
    ): ?string {
        $this->calls[] = [
            'reasoningEffort' => $reasoningEffort,
            'maxCompletionTokens' => $maxCompletionTokens,
        ];
        $response = array_shift($this->responses);
        if ($response === null) {
            $lastError = $this->failure;
        }

        return $response;
    }
}

class AzureSummarizerProbe extends SummarizerService
{
    public function callAzure(
        string $prompt,
        ?string &$error,
        string $reasoningEffort,
        int $maxCompletionTokens,
    ): ?string {
        return $this->callAzureFoundryAIText($prompt, $error, $reasoningEffort, $maxCompletionTokens);
    }
}
