<?php

namespace App\Enums;

enum SupportTicketPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'Urgent';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::Normal => 'Normal',
            self::High => 'High',
            self::Urgent => 'Urgent',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Low => 'bg-gray-50 text-gray-600 border border-gray-200',
            self::Normal => 'bg-blue-50 text-blue-700 border border-blue-200',
            self::High => 'bg-orange-50 text-orange-700 border border-orange-200',
            self::Urgent => 'bg-red-50 text-red-700 border border-red-200',
        };
    }
}
