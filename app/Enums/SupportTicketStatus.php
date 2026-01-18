<?php

namespace App\Enums;

enum SupportTicketStatus: string
{
    case Open = 'open';
    case Pending = 'pending';
    case Closed = 'closed';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Pending => 'Pending',
            self::Closed => 'Closed',
            self::Resolved => 'Resolved',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            // Stronger colors to ensure they show up
            self::Open     => 'bg-blue-100 text-blue-800 border border-blue-300',
            self::Pending  => 'bg-orange-100 text-orange-800 border border-orange-300',
            self::Resolved => 'bg-green-100 text-green-800 border border-green-300',
            self::Closed   => 'bg-gray-200 text-gray-700 border border-gray-400',
        };
    }
}
