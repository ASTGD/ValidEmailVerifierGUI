<?php

namespace App\Filament\Resources\SupportTickets\Pages;

use App\Enums\SupportTicketStatus;
use App\Filament\Resources\SupportTickets\SupportTicketResource;
use App\Support\AdminAuditLogger;
use Filament\Resources\Pages\CreateRecord;

class CreateSupportTicket extends CreateRecord
{
    protected static string $resource = SupportTicketResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (($data['status'] ?? null) === SupportTicketStatus::Closed->value) {
            $data['closed_at'] = now();
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        AdminAuditLogger::log('support_ticket_created', $this->record, [
            'status' => $this->record->status?->value,
        ]);
    }
}
