<?php

namespace App\Filament\Resources\Customers;

use App\Filament\Resources\Customers\Pages\CreateCustomer;
use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Customers\RelationManagers\CustomerVerificationJobsRelationManager;
use App\Filament\Resources\Customers\Schemas\CustomerForm;
use App\Filament\Resources\Customers\Schemas\CustomerInfolist;
use App\Filament\Resources\Customers\Tables\CustomersTable;
use App\Models\User;
use App\Support\Roles;
use BackedEnum;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

use function Filament\Support\original_request;

class CustomerResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Customers';

    protected static string|UnitEnum|null $navigationGroup = 'Customers';

    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->role(Roles::CUSTOMER);
    }

    public static function form(Schema $schema): Schema
    {
        return CustomerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomersTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CustomerInfolist::configure($schema);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\VerificationOrdersRelationManager::class,
            RelationManagers\InvoicesRelationManager::class,
            RelationManagers\SupportTicketsRelationManager::class,
            RelationManagers\CreditsRelationManager::class,
            RelationManagers\AuditLogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'create' => CreateCustomer::route('/create'),
            'view' => ViewCustomer::route('/{record}'),
            'edit' => EditCustomer::route('/{record}/edit'),
        ];
    }

    public static function getNavigationItems(): array
    {
        $parentLabel = static::getNavigationLabel();
        $group = static::getNavigationGroup();
        $baseRoute = static::getRouteBaseName();

        return [
            NavigationItem::make($parentLabel)
                ->group($group)
                ->icon(static::getNavigationIcon())
                ->sort(static::getNavigationSort())
                ->url(static::getUrl('index'))
                ->isActiveWhen(fn(): bool => original_request()->routeIs($baseRoute . '.index')),

            NavigationItem::make('Add new Customers')
                ->group($group)
                ->parentItem($parentLabel)
                ->url(static::getUrl('create'))
                ->isActiveWhen(fn(): bool => original_request()->routeIs($baseRoute . '.create')),

            NavigationItem::make('List of Customers')
                ->group($group)
                ->parentItem($parentLabel)
                ->url(static::getUrl('index'))
                ->isActiveWhen(fn(): bool => original_request()->routeIs($baseRoute . '.index')),
        ];
    }
}
