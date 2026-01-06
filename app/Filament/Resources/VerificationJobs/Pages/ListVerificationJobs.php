<?php

namespace App\Filament\Resources\VerificationJobs\Pages;

use App\Filament\Resources\VerificationJobs\VerificationJobResource;
use Filament\Resources\Pages\ListRecords;

class ListVerificationJobs extends ListRecords
{
    protected static string $resource = VerificationJobResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
