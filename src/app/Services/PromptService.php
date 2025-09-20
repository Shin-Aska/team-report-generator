<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class PromptService
{
    public function getDaily1Template(): string
    {
        return Storage::disk('local')->exists('prompts/daily1.md')
            ? Storage::disk('local')->get('prompts/daily1.md')
            : '';
    }

    public function getDaily2Template(): string
    {
        return Storage::disk('local')->exists('prompts/daily2.md')
            ? Storage::disk('local')->get('prompts/daily2.md')
            : '';
    }

    public function getWeeklyTemplate(): string
    {
        return Storage::disk('local')->exists('prompts/weekly.md')
            ? Storage::disk('local')->get('prompts/weekly.md')
            : '';
    }

    public function fillConcatenated(string $template, string $concatenated): string
    {
        return str_replace('{concatenated_report_here}', $concatenated, $template);
    }
}
