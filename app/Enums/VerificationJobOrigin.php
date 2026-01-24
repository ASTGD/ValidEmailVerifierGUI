<?php

namespace App\Enums;

enum VerificationJobOrigin: string
{
    case ListUpload = 'list_upload';
    case SingleCheck = 'single_check';

    public function label(): string
    {
        return match ($this) {
            self::ListUpload => 'List upload',
            self::SingleCheck => 'Single check',
        };
    }
}
