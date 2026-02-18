<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmtpUnknownCluster extends Model
{
    protected $fillable = [
        'provider',
        'cluster_signature',
        'sample_count',
        'feature_tokens',
        'example_messages',
        'recommended_tags',
        'status',
        'last_seen_at',
    ];

    protected $casts = [
        'sample_count' => 'integer',
        'feature_tokens' => 'array',
        'example_messages' => 'array',
        'recommended_tags' => 'array',
        'last_seen_at' => 'datetime',
    ];
}
