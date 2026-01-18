<?php

namespace App\Filament\Resources\FeedbackImports\Pages;

use App\Filament\Resources\FeedbackImports\FeedbackImportResource;
use App\Jobs\ImportEmailVerificationOutcomesFromCsv;
use App\Models\EmailVerificationOutcomeImport;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateFeedbackImport extends CreateRecord
{
    protected static string $resource = FeedbackImportResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();
        $data['status'] = EmailVerificationOutcomeImport::STATUS_PENDING;
        $data['source'] = $data['source'] ?? 'admin_import';

        return $data;
    }

    protected function afterCreate(): void
    {
        ImportEmailVerificationOutcomesFromCsv::dispatch($this->record->id);

        Notification::make()
            ->title('Feedback import queued.')
            ->success()
            ->send();
    }
}
