<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmtpConfidenceCalibration extends Model
{
    protected $fillable = [
        'rollup_date',
        'provider',
        'decision_class',
        'confidence_hint',
        'sample_count',
        'match_count',
        'unknown_count',
        'precision_rate',
        'supporting_metrics',
    ];

    protected $casts = [
        'rollup_date' => 'date',
        'sample_count' => 'integer',
        'match_count' => 'integer',
        'unknown_count' => 'integer',
        'precision_rate' => 'float',
        'supporting_metrics' => 'array',
    ];
}
