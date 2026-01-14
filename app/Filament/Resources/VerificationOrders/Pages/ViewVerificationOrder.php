<?php

namespace App\Filament\Resources\VerificationOrders\Pages;

use App\Filament\Resources\VerificationOrders\VerificationOrderResource;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewVerificationOrder extends ViewRecord
{
    protected static string $resource = VerificationOrderResource::class;

    public function getTitle(): string | Htmlable
    {
        $record = $this->getRecord();
        $label = $record->order_number ?: ('#' . $record->id);

        return __('Order :number', ['number' => $label]);
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
