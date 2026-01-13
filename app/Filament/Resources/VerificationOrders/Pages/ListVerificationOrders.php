<?php

namespace App\Filament\Resources\VerificationOrders\Pages;

use App\Enums\VerificationOrderStatus;
use App\Filament\Resources\VerificationOrders\VerificationOrderResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

use function Filament\Support\original_request;

class ListVerificationOrders extends ListRecords
{
    protected static string $resource = VerificationOrderResource::class;

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();
        $status = original_request()->query('status');

        if (! $status) {
            return $query;
        }

        if ($status === 'active') {
            return $query->where('status', VerificationOrderStatus::Processing->value);
        }

        $allowed = array_map(static fn (VerificationOrderStatus $case): string => $case->value, VerificationOrderStatus::cases());

        if (in_array($status, $allowed, true)) {
            $query->where('status', $status);
        }

        return $query;
    }
}
