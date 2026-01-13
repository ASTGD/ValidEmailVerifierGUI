<?php

namespace App\Filament\Resources\FinishedJobResource\Pages;

use App\Filament\Resources\FinishedJobResource;
use Filament\Resources\Pages\ListRecords;

class ListFinishedJobs extends ListRecords
{
    protected static string $resource = FinishedJobResource::class;

    protected function getHeaderHeading(): ?string
    {
        return 'Completed Verification Jobs';
    }
}
