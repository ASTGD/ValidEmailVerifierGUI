<?php

namespace App\Filament\Resources\VerificationOrders\Pages;

use App\Enums\VerificationOrderStatus;
use App\Filament\Resources\VerificationOrders\VerificationOrderResource;
use App\Models\VerificationOrder;
use App\Services\OrderStorage;
use App\Support\AdminAuditLogger;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CreateVerificationOrder extends CreateRecord
{
    protected static string $resource = VerificationOrderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $file = $data['input_file'] ?? null;
        unset($data['input_file']);

        $data['id'] = (string) Str::uuid();
        $data['status'] = VerificationOrderStatus::Pending->value;

        if (is_array($file)) {
            $file = $file[0] ?? null;
        }

        if ($file) {
            $order = new VerificationOrder([
                'id' => $data['id'],
                'user_id' => $data['user_id'],
            ]);

            $storage = app(OrderStorage::class);
            [$disk, $key] = $storage->storeInput($file, $order);

            $data['input_disk'] = $disk;
            $data['input_key'] = $key;
            $data['original_filename'] = $file->getClientOriginalName();
        }

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        $record = new VerificationOrder($data);
        $record->id = $data['id'] ?? (string) Str::uuid();
        $record->save();

        return $record;
    }

    protected function afterCreate(): void
    {
        AdminAuditLogger::log('order_created', $this->record);
    }
}
