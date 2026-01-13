<?php

namespace App\Enums;

enum VerificationJobStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Spam = 'spam';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending Review',
            self::Processing => 'Verification in Progress',
            self::Completed => 'Completed',
            self::Failed => 'System Failure',
            self::Cancelled => 'Order Cancelled',
            self::Spam => 'Flagged as Spam/Fraud',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pending => 'bg-amber-100 text-amber-700 border border-amber-200',
            self::Processing => 'bg-blue-100 text-blue-700 border border-blue-200',
            self::Completed => 'bg-emerald-100 text-emerald-700 border border-emerald-200',
            self::Failed => 'bg-rose-100 text-rose-700 border border-rose-200',
            self::Cancelled => 'bg-slate-100 text-slate-700 border border-slate-200',
            self::Spam => 'bg-red-600 text-white shadow-sm', // High visibility for Spam
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'heroicon-m-clock',
            self::Processing => 'heroicon-m-arrow-path',
            self::Completed => 'heroicon-m-check-badge',
            self::Failed => 'heroicon-m-x-circle',
            self::Cancelled => 'heroicon-m-minus-circle',
            self::Spam => 'heroicon-m-shield-exclamation',
        };
    }
}
