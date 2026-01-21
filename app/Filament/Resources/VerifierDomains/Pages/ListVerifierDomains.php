<?php

namespace App\Filament\Resources\VerifierDomains\Pages;

use App\Filament\Resources\VerifierDomains\VerifierDomainResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVerifierDomains extends ListRecords
{
    protected static string $resource = VerifierDomainResource::class;

    /**
     * @return array<class-string<CreateAction>>
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Add Verifier Domain'),
        ];
    }
}
