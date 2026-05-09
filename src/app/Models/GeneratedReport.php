<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
