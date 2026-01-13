<?php

namespace App\Filament\Resources\VerificationOrders;

use App\Filament\Resources\VerificationOrders\Pages\CreateVerificationOrder;
use App\Filament\Resources\VerificationOrders\Pages\ListVerificationOrders;
use App\Filament\Resources\VerificationOrders\Pages\ViewVerificationOrder;
use App\Filament\Resources\VerificationOrders\Schemas\VerificationOrderForm;
use App\Filament\Resources\VerificationOrders\Schemas\VerificationOrderInfolist;
use App\Filament\Resources\VerificationOrders\Tables\VerificationOrdersTable;
use App\Models\VerificationOrder;
use BackedEnum;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

use function Filament\Support\original_request;

class VerificationOrderResource extends Resource
{
    protected static ?string $model = VerificationOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'List Orders';

    protected static string|UnitEnum|null $navigationGroup = 'Orders';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return VerificationOrderForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return VerificationOrderInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VerificationOrdersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVerificationOrders::route('/'),
            'create' => CreateVerificationOrder::route('/create'),
            'view' => ViewVerificationOrder::route('/{record}'),
        ];
    }

    public static function getNavigationItems(): array
    {
        $parentLabel = static::getNavigationLabel();
        $group = static::getNavigationGroup();
        $baseRoute = static::getRouteBaseName();

        $makeChild = fn (string $label, string $url, string $status): NavigationItem => NavigationItem::make($label)
            ->group($group)
            ->parentItem($parentLabel)
            ->url($url)
            ->isActiveWhen(fn (): bool => original_request()->routeIs($baseRoute . '.index') && original_request()->query('status') === $status);

        return [
            NavigationItem::make($parentLabel)
                ->group($group)
                ->icon(static::getNavigationIcon())
                ->sort(static::getNavigationSort())
                ->url(static::getUrl('index'))
                ->isActiveWhen(fn (): bool => original_request()->routeIs($baseRoute . '.index') && ! original_request()->query('status')),
            $makeChild(__('Pending Orders'), static::getUrl('index', ['status' => 'pending']), 'pending'),
            $makeChild(__('Active Orders'), static::getUrl('index', ['status' => 'active']), 'active'),
            $makeChild(__('Cancelled Orders'), static::getUrl('index', ['status' => 'cancelled']), 'cancelled'),
            $makeChild(__('Fraud Orders'), static::getUrl('index', ['status' => 'fraud']), 'fraud'),
            NavigationItem::make(__('Add New Order'))
                ->group($group)
                ->parentItem($parentLabel)
                ->url(static::getUrl('create'))
                ->isActiveWhen(fn (): bool => original_request()->routeIs($baseRoute . '.create')),
        ];
    }
}
