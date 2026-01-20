<?php

namespace App\Filament\Resources\EngineVerificationPolicies;

use App\Filament\Resources\EngineVerificationPolicies\Pages\CreateEngineVerificationPolicy;
use App\Filament\Resources\EngineVerificationPolicies\Pages\EditEngineVerificationPolicy;
use App\Filament\Resources\EngineVerificationPolicies\Pages\ListEngineVerificationPolicies;
use App\Filament\Resources\EngineVerificationPolicies\Schemas\EngineVerificationPolicyForm;
use App\Filament\Resources\EngineVerificationPolicies\Tables\EngineVerificationPoliciesTable;
use App\Models\EngineVerificationPolicy;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class EngineVerificationPolicyResource extends Resource
{
    protected static ?string $model = EngineVerificationPolicy::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?string $navigationLabel = 'Verification Policies';

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return EngineVerificationPolicyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EngineVerificationPoliciesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEngineVerificationPolicies::route('/'),
            'create' => CreateEngineVerificationPolicy::route('/create'),
            'edit' => EditEngineVerificationPolicy::route('/{record}/edit'),
        ];
    }
}
