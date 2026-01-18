<?php

namespace App\Enums;

enum VerificationMode: string
{
    case Standard = 'standard';
    case Enhanced = 'enhanced';

    public function label(): string
    {
        return match ($this) {
            self::Standard => 'Standard',
            self::Enhanced => 'Enhanced',
        };
    }
}
