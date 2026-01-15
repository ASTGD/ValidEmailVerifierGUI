<?php

namespace App\Models;

use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    protected $fillable = [
        'user_id',
        'ticket_number',
        'subject',
        'category',
        'status',
        'priority',
        'closed_at',
    ];

    protected $casts = [
        'status' => SupportTicketStatus::class,
        'priority' => SupportTicketPriority::class,
        'closed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Relationship to the chat messages.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class);
    }

    /**
     * Auto-generate a ticket number when creating.
     */
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->ticket_number = 'TK-' . strtoupper(bin2hex(random_bytes(3)));
        });
    }
}
