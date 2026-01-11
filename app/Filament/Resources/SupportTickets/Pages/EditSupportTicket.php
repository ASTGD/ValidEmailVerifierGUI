<?php

namespace App\Filament\Resources\SupportTickets\Pages;

use App\Enums\SupportTicketStatus;
use App\Filament\Resources\SupportTickets\SupportTicketResource;
use App\Support\AdminAuditLogger;
use Filament\Resources\Pages\EditRecord;

class EditSupportTicket extends EditRecord
{
    protected static string $resource = SupportTicketResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['status'] ?? null) === SupportTicketStatus::Closed->value) {
            $data['closed_at'] = $this->record->closed_at ?? now();
        } else {
            $data['closed_at'] = null;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        AdminAuditLogger::log('support_ticket_updated', $this->record, [
            'status' => $this->record->status?->value,
        ]);
    }
}
