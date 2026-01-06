<?php

namespace App\Models;

use App\Enums\VerificationJobStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class VerificationJob extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'status',
        'original_filename',
        'input_disk',
        'input_key',
        'output_disk',
        'output_key',
        'error_message',
        'started_at',
        'finished_at',
        'total_emails',
        'valid_count',
        'invalid_count',
        'risky_count',
        'unknown_count',
    ];

    protected $casts = [
        'status' => VerificationJobStatus::class,
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'total_emails' => 'integer',
        'valid_count' => 'integer',
        'invalid_count' => 'integer',
        'risky_count' => 'integer',
        'unknown_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
