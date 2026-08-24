<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Store a canonical daily or weekly report for a user and engine.
 *
 * The signature supports stale-report detection. Presentation headers are not persisted, while saved variants are exposed through `refinedReports()`.
 *
 * @property int $id
 * @property string $report_type
 * @property string $content
 * @property string|null $engine
 * @property string|null $signature
 */
class GeneratedReport extends Model
{
    protected $fillable = [
        'user_id',
        'report_type',
        'date',
        'start_date',
        'end_date',
        'content',
        'engine',
        'signature',
    ];

    protected $casts = [
        'date' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function refinedReports(): HasMany
    {
        return $this->hasMany(RefinedReport::class);
    }
}
