<?php

namespace App\Filament\Resources\VerificationJobs\Pages;

use App\Enums\VerificationJobStatus;
use App\Filament\Resources\VerificationJobs\VerificationJobResource;
use App\Jobs\FinalizeVerificationJob;
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
        return [
            Action::make('finalize')
                ->label('Finalize')
                ->action(fn () => FinalizeVerificationJob::dispatch($this->record->id))
                ->requiresConfirmation()
                ->successNotificationTitle('Finalization queued')
                ->visible(fn () => $this->record->status !== VerificationJobStatus::Completed),
        ];
    }
}
