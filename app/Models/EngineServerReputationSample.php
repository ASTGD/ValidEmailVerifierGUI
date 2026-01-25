<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EngineServerReputationSample extends Model
{
    protected $fillable = [
        'engine_server_id',
        'verification_job_chunk_id',
        'total_count',
        'tempfail_count',
        'recorded_at',
    ];

    protected $casts = [
        'total_count' => 'integer',
        'tempfail_count' => 'integer',
        'recorded_at' => 'datetime',
    ];

    public function engineServer(): BelongsTo
    {
        return $this->belongsTo(EngineServer::class);
    }
}
