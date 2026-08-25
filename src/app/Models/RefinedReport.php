<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Persist a refinement of a canonical generated report.
 *
 * The prompt hash identifies equivalent requests and the source signature associates a variant with the source version for stale detection.
 *
 * @property int $id
 * @property int $generated_report_id
 * @property string $mode
 * @property string|null $prompt
 * @property string $content
 */
class RefinedReport extends Model
{
    protected $fillable = [
        'generated_report_id',
        'mode',
        'prompt',
        'prompt_hash',
        'content',
        'engine',
        'source_signature',
    ];

    public function generatedReport(): BelongsTo
    {
        return $this->belongsTo(GeneratedReport::class);
    }
}
