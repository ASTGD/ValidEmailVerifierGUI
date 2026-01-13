<?php

namespace App\Filament\Resources\PendingJobResource\Pages;

use App\Filament\Resources\PendingJobResource;
use Filament\Resources\Pages\ListRecords;

class ListPendingJobs extends ListRecords
{
    protected static string $resource = PendingJobResource::class;

    protected function getHeaderHeading(): ?string
    {
        return 'Pending Verification Requests';
    }
}
