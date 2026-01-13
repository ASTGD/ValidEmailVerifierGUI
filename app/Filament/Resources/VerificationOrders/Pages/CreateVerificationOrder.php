<?php

namespace App\Filament\Resources\VerificationOrders\Pages;

use App\Enums\VerificationOrderStatus;
use App\Filament\Resources\VerificationOrders\VerificationOrderResource;
use App\Models\VerificationOrder;
use App\Services\OrderStorage;
use App\Support\AdminAuditLogger;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class CreateVerificationOrder extends CreateRecord
{
    protected static string $resource = VerificationOrderResource::class;

    protected ?TemporaryUploadedFile $uploadedFile = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $file = $data['input_file'] ?? null;
        unset($data['input_file']);

        $data['order_number'] = VerificationOrder::generateOrderNumber();
        $data['status'] = VerificationOrderStatus::Pending->value;

        if (is_array($file)) {
            $file = $file[0] ?? null;
        }

        if ($file instanceof TemporaryUploadedFile) {
            $this->uploadedFile = $file;
        }

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        $record = new VerificationOrder($data);
        $record->save();

        return $record;
    }

    protected function afterCreate(): void
    {
        if ($this->uploadedFile) {
            $storage = app(OrderStorage::class);
            [$disk, $key] = $storage->storeInput($this->uploadedFile, $this->record);

            $this->record->update([
                'input_disk' => $disk,
                'input_key' => $key,
                'original_filename' => $this->uploadedFile->getClientOriginalName(),
            ]);
        }

        AdminAuditLogger::log('order_created', $this->record);
    }
}
