<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailVerificationOutcomeImport extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'file_disk',
        'file_key',
        'status',
        'source',
        'imported_count',
        'skipped_count',
        'error_sample',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'error_sample' => 'array',
        'imported_count' => 'integer',
        'skipped_count' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
