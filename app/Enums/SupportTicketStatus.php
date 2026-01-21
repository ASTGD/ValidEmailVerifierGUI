<?php

namespace App\Enums;

enum SupportTicketStatus: string
{
    case Open = 'open';
    case Pending = 'pending';
    case Answered = 'answered';
    case CustomerReply = 'customer_reply';
    case OnHold = 'on_hold';
    case InProgress = 'in_progress';
    case Closed = 'closed';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Pending => 'Pending',
            self::Answered => 'Answered',
            self::CustomerReply => 'Customer-Reply',
            self::OnHold => 'On Hold',
            self::InProgress => 'In Progress',
            self::Closed => 'Closed',
            self::Resolved => 'Resolved',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Open => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
            self::Pending => 'bg-amber-50 text-amber-700 border border-amber-200',
            self::Answered => 'bg-blue-50 text-blue-700 border border-blue-200',
            self::CustomerReply => 'bg-orange-50 text-orange-700 border border-orange-200',
            self::OnHold => 'bg-slate-100 text-slate-700 border border-slate-300',
            self::InProgress => 'bg-indigo-50 text-indigo-700 border border-indigo-200',
            self::Closed => 'bg-gray-100 text-gray-600 border border-gray-200',
            self::Resolved => 'bg-green-50 text-green-700 border border-green-200',
        };
    }
}
