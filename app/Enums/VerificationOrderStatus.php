<?php

namespace App\Enums;

enum VerificationOrderStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Delivered = 'delivered';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Processing => __('Processing'),
            self::Delivered => __('Delivered'),
            self::Failed => __('Failed'),
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pending => 'bg-yellow-100 text-yellow-800',
            self::Processing => 'bg-blue-100 text-blue-800',
            self::Delivered => 'bg-green-100 text-green-800',
            self::Failed => 'bg-red-100 text-red-800',
        };
    }
}
