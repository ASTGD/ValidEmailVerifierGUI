<?php

namespace App\Filament\Resources;

use UnitEnum;
use App\Filament\Resources\VerificationJobs\VerificationJobResource;
use App\Models\VerificationJob;
use Illuminate\Database\Eloquent\Builder;

class FinishedJobResource extends VerificationJobResource
{
    protected static ?string $model = VerificationJob::class;

    // Update the property inside the class
    protected static string|UnitEnum|null $navigationGroup = 'Customer Jobs';

    protected static ?string $navigationLabel = 'Finished Jobs';

    protected static ?string $modelLabel = 'Finished Job';

    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        // Only show jobs that are completed, cancelled, or spam
        return parent::getEloquentQuery()->finished();
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\FinishedJobResource\Pages\ListFinishedJobs::route('/'),
        ];
    }
}
