<?php

namespace App\Filament\Resources\EngineServerBlacklistEvents\Pages;

use App\Filament\Resources\EngineServerBlacklistEvents\EngineServerBlacklistEventResource;
use Filament\Resources\Pages\ListRecords;

class ListEngineServerBlacklistEvents extends ListRecords
{
    protected static string $resource = EngineServerBlacklistEventResource::class;
}
