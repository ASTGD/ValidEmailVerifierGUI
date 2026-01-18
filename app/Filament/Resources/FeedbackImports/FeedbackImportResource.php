<?php

namespace App\Filament\Resources\FeedbackImports;

use App\Filament\Resources\FeedbackImports\Pages\CreateFeedbackImport;
use App\Filament\Resources\FeedbackImports\Pages\ListFeedbackImports;
use App\Filament\Resources\FeedbackImports\Schemas\FeedbackImportForm;
use App\Filament\Resources\FeedbackImports\Tables\FeedbackImportsTable;
use App\Models\EmailVerificationOutcomeImport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class FeedbackImportResource extends Resource
{
    protected static ?string $model = EmailVerificationOutcomeImport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpOnSquare;

    protected static ?string $navigationLabel = 'Feedback Imports';

    protected static string|UnitEnum|null $navigationGroup = 'Engine';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return FeedbackImportForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FeedbackImportsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFeedbackImports::route('/'),
            'create' => CreateFeedbackImport::route('/create'),
        ];
    }
}
