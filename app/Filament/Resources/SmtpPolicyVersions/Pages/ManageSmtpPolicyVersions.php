<?php

namespace App\Filament\Resources\SmtpPolicyVersions\Pages;

use App\Filament\Resources\SmtpPolicyVersions\SmtpPolicyVersionResource;
use Filament\Resources\Pages\ManageRecords;

class ManageSmtpPolicyVersions extends ManageRecords
{
    protected static string $resource = SmtpPolicyVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
