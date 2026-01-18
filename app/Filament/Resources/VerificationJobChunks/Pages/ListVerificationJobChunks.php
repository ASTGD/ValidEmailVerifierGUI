<?php

namespace App\Filament\Resources\VerificationJobChunks\Pages;

use App\Filament\Resources\VerificationJobChunks\VerificationJobChunkResource;
use Filament\Resources\Pages\ListRecords;

class ListVerificationJobChunks extends ListRecords
{
    protected static string $resource = VerificationJobChunkResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
