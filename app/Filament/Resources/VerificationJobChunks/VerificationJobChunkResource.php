<?php

namespace App\Filament\Resources\VerificationJobChunks;

use App\Filament\Resources\VerificationJobChunks\Pages\ListVerificationJobChunks;
use App\Filament\Resources\VerificationJobChunks\Pages\ViewVerificationJobChunk;
use App\Filament\Resources\VerificationJobChunks\Schemas\VerificationJobChunkInfolist;
use App\Filament\Resources\VerificationJobChunks\Tables\VerificationJobChunksTable;
use App\Models\VerificationJobChunk;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class VerificationJobChunkResource extends Resource
{
    protected static ?string $model = VerificationJobChunk::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Job Chunks';

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 2;

    public static function infolist(Schema $schema): Schema
    {
        return VerificationJobChunkInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VerificationJobChunksTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVerificationJobChunks::route('/'),
            'view' => ViewVerificationJobChunk::route('/{record}'),
        ];
    }
}
