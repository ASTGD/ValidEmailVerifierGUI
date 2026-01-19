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
            self::Open => 'bg-emerald-100 text-emerald-800 border border-emerald-300',
            self::Pending => 'bg-amber-100 text-amber-800 border border-amber-300',
            self::Answered => 'bg-blue-100 text-blue-800 border border-blue-300',
            self::CustomerReply => 'bg-orange-100 text-orange-800 border border-orange-300',
            self::OnHold => 'bg-red-100 text-red-800 border border-red-300',
            self::InProgress => 'bg-purple-100 text-purple-800 border border-purple-300',
            self::Closed => 'bg-gray-200 text-gray-700 border border-gray-400',
            self::Resolved => 'bg-green-100 text-green-800 border border-green-300',
        };
    }
}
