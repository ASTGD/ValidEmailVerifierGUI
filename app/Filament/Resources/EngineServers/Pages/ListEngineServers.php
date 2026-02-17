<?php

namespace App\Filament\Resources\EngineServers\Pages;

use App\Filament\Resources\EngineServers\EngineServerResource;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListEngineServers extends ListRecords
{
    protected static string $resource = EngineServerResource::class;

    public function getSubheading(): ?string
    {
        return 'Emergency fallback UI only. Use Go Verifier Engine Room for daily operations.';
    }

    public function mount(): void
    {
        parent::mount();

        Notification::make()
            ->title('Emergency fallback UI')
            ->body('Use this page only when Go Verifier Engine Room is unavailable.')
            ->warning()
            ->send();
    }

    /**
     * @return array<class-string<CreateAction>>
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
