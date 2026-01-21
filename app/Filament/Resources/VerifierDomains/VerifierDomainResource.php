<?php

namespace App\Filament\Resources\VerifierDomains;

use App\Filament\Resources\VerifierDomains\Pages\CreateVerifierDomain;
use App\Filament\Resources\VerifierDomains\Pages\EditVerifierDomain;
use App\Filament\Resources\VerifierDomains\Pages\ListVerifierDomains;
use App\Filament\Resources\VerifierDomains\Schemas\VerifierDomainForm;
use App\Filament\Resources\VerifierDomains\Tables\VerifierDomainsTable;
use App\Models\VerifierDomain;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class VerifierDomainResource extends Resource
{
    protected static ?string $model = VerifierDomain::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?string $navigationLabel = 'Verifier Domain';

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'Verifier Domain';

    protected static ?string $pluralModelLabel = 'Verifier Domains';

    public static function form(Schema $schema): Schema
    {
        return VerifierDomainForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VerifierDomainsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVerifierDomains::route('/'),
            'create' => CreateVerifierDomain::route('/create'),
            'edit' => EditVerifierDomain::route('/{record}/edit'),
        ];
    }
}
