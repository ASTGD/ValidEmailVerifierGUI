<?php

namespace App\Filament\Pages;

use BackedEnum;
use App\Support\EngineSettings;
use App\Support\Roles;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class QueueEngine extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static ?string $navigationLabel = 'Queue Engine';

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 10;

    protected static ?string $title = 'Queue Engine';

    protected static ?string $slug = 'queue-engine';

    protected string $view = 'filament.pages.queue-engine';

    protected Width|string|null $maxContentWidth = Width::Full;

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user || ! method_exists($user, 'hasRole') || ! $user->hasRole(Roles::ADMIN)) {
            return false;
        }

        try {
            return EngineSettings::horizonEnabled();
        } catch (\Throwable $exception) {
            return false;
        }
    }

    public function horizonUrl(): string
    {
        return url('/' . trim((string) config('horizon.path', 'horizon'), '/'));
    }
}
