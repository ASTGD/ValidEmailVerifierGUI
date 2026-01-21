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
        'verification_order_id',
    ];

    protected $casts = [
        'status' => \App\Enums\SupportTicketStatus::class,
        'priority' => \App\Enums\SupportTicketPriority::class,
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
    /**
     * Get the color classes based on the department/category.
     */
    public function getCategoryBadgeClasses(): string
    {
        return match ($this->category) {
            'Technical' => 'bg-indigo-50 text-indigo-700 border border-indigo-200',
            'Billing' => 'bg-amber-50 text-amber-700 border border-amber-200',
            'Sales' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
            default => 'bg-slate-50 text-slate-600 border border-slate-200',
        };
    }
    /**
     * Get all orders belonging to the user of this ticket.
     */
    public function userOrders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        // This looks at the user of the ticket and finds their verification orders
        return $this->hasMany(VerificationOrder::class, 'user_id', 'user_id');
    }

    // Relationship method:
    public function order(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(VerificationOrder::class, 'verification_order_id');
    }

    /**
     * Relationship to the specific order linked to this ticket.
     */
    public function linkedOrder(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(VerificationOrder::class, 'verification_order_id');
    }
}
