<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EngineServerCommand extends Model
{
    use HasUuids;

    protected $fillable = [
        'engine_server_id',
        'action',
        'status',
        'requested_by_user_id',
        'source',
        'request_id',
        'idempotency_key',
        'agent_command_id',
        'reason',
        'agent_response',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'agent_response' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    public function engineServer(): BelongsTo
    {
        return $this->belongsTo(EngineServer::class);
    }
}
