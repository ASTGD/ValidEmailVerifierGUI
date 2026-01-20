<?php

namespace App\Enums;

enum SupportTicketStatus: string
{
    case Open = 'open';
    case Pending = 'pending';
    case Answered = 'answered'; // ADD THIS LINE to fix the error
    case Closed = 'closed';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Pending => 'Pending',
            self::Answered => 'Answered', // ADD THIS
            self::Closed => 'Closed',
            self::Resolved => 'Resolved',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Open => 'bg-blue-50 text-blue-700 border border-blue-200',
            self::Pending => 'bg-amber-50 text-amber-700 border border-amber-200',
            self::Answered => 'bg-indigo-50 text-indigo-700 border border-indigo-200', // Color for Answered
            self::Resolved => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
            self::Closed => 'bg-slate-100 text-slate-600 border border-slate-200',
        };
    }

    // Add this helper for Filament Tables/Infolists
    public function color(): string
    {
        return match ($this) {
            self::Open => 'info',
            self::Pending => 'warning',
            self::Answered => 'primary',
            self::Resolved => 'success',
            self::Closed => 'gray',
        };
    }
}
