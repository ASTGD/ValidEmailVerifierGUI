<?php

namespace App\Filament\Resources\VerificationJobs;

use App\Filament\Resources\VerificationJobs\Pages\ListVerificationJobs;
use App\Filament\Resources\VerificationJobs\Pages\ViewVerificationJob;
use App\Filament\Resources\VerificationJobs\RelationManagers\VerificationJobLogsRelationManager;
use App\Filament\Resources\VerificationJobs\Schemas\VerificationJobForm;
use App\Filament\Resources\VerificationJobs\Schemas\VerificationJobInfolist;
use App\Filament\Resources\VerificationJobs\Tables\VerificationJobsTable;
use App\Models\VerificationJob;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class VerificationJobResource extends Resource
{
    protected static ?string $model = VerificationJob::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return VerificationJobForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return VerificationJobInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VerificationJobsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            VerificationJobLogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVerificationJobs::route('/'),
            'view' => ViewVerificationJob::route('/{record}'),
        ];
    }
}
