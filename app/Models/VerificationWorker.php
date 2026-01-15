<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerificationWorker extends Model
{
    protected $fillable = [
        'worker_id',
        'engine_server_id',
        'version',
        'last_seen_at',
        'current_job_chunk_id',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    public function engineServer(): BelongsTo
    {
        return $this->belongsTo(EngineServer::class);
    }

    public function currentChunk(): BelongsTo
    {
        return $this->belongsTo(VerificationJobChunk::class, 'current_job_chunk_id');
    }
}
