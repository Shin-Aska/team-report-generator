<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
