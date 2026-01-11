<?php

namespace App\Filament\Resources\VerificationJobs\Pages;

use App\Filament\Resources\VerificationJobs\VerificationJobResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewVerificationJob extends ViewRecord
{
    protected static string $resource = VerificationJobResource::class;

    /**
     * @return array<class-string<Action>>
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
