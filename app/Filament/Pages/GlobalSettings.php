<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class GlobalSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Global Settings';

    protected static ?int $navigationSort = 999;

    protected static ?string $title = 'Global Settings';

    protected static ?string $slug = 'global-settings';

    protected string $view = 'filament.pages.global-settings';
}
