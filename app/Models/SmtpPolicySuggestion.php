<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmtpPolicySuggestion extends Model
{
    protected $fillable = [
        'provider',
        'status',
        'suggestion_type',
        'source_window',
        'suggestion_payload',
        'supporting_metrics',
        'sample_size',
        'created_by',
        'reviewed_at',
        'reviewed_by',
        'review_notes',
    ];

    protected $casts = [
        'suggestion_payload' => 'array',
        'supporting_metrics' => 'array',
        'sample_size' => 'integer',
        'reviewed_at' => 'datetime',
        'review_notes' => 'array',
    ];
}
