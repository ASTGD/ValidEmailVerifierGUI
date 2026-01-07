<?php

namespace App\Filament\Resources\VerificationJobs;

use App\Filament\Resources\VerificationJobs\Pages\ListVerificationJobs;
use App\Filament\Resources\VerificationJobs\RelationManagers\VerificationJobLogsRelationManager;
use App\Filament\Resources\VerificationJobs\Schemas\VerificationJobForm;
use App\Filament\Resources\VerificationJobs\Tables\VerificationJobsTable;
use App\Models\VerificationJob;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class VerificationJobResource extends Resource
{
    protected static ?string $model = VerificationJob::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return VerificationJobForm::configure($schema);
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
        ];
    }
}
