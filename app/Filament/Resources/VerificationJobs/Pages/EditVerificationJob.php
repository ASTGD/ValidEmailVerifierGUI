<?php

namespace App\Filament\Resources\VerificationJobs\Pages;

use App\Filament\Resources\VerificationJobs\VerificationJobResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditVerificationJob extends EditRecord
{
    protected static string $resource = VerificationJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
