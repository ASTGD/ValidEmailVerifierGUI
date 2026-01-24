<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Support\AdminAuditLogger;
use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    /**
     * @return array<class-string<Action>>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('enhanced_access')
                ->label('Enhanced Access')
                ->icon('heroicon-m-sparkles')
                ->form([
                    Toggle::make('enhanced_enabled')
                        ->label('Allow Enhanced mode')
                        ->helperText('Enable mailbox-level checks (SG5) for this customer.')
                        ->default(fn () => (bool) $this->record->enhanced_enabled),
                ])
                ->action(function (array $data): void {
                    $record = $this->record;
                    $from = (bool) $record->enhanced_enabled;
                    $to = (bool) ($data['enhanced_enabled'] ?? false);

                    $record->update([
                        'enhanced_enabled' => $to,
                    ]);

                    AdminAuditLogger::log('customer_enhanced_access_updated', $record, [
                        'from' => $from,
                        'to' => $to,
                    ]);

                    Notification::make()
                        ->title('Enhanced access updated.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
