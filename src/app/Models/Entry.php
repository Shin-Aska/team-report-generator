<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Represent one team member's Markdown update for a calendar date.
 *
 * Entries are the source records consumed by daily and weekly report generation.
 *
 * @property int $id
 * @property int $user_id
 * @property Carbon $entry_date
 * @property string $content
 * @property-read User $user
 */
class Entry extends Model
{
    protected $fillable = [
        'user_id',
        'entry_date',
        'content',
    ];

    protected $casts = [
        'entry_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
