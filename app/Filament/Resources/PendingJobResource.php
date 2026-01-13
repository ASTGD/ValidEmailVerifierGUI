<?php

namespace App\Filament\Resources;

use UnitEnum;
use App\Filament\Resources\VerificationJobs\VerificationJobResource;
use App\Models\VerificationJob;
use Illuminate\Database\Eloquent\Builder;


class PendingJobResource extends VerificationJobResource
{
    protected static ?string $model = VerificationJob::class;

    // Update the property inside the class
    protected static string|UnitEnum|null $navigationGroup = 'Customer Jobs';

    protected static ?string $navigationLabel = 'Pending Jobs';

    protected static ?string $modelLabel = 'Pending Job';

    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        // Only show jobs with 'pending' status
        return parent::getEloquentQuery()->pending();
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\PendingJobResource\Pages\ListPendingJobs::route('/'),
        ];
    }
}
