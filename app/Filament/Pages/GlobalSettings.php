<?php

namespace App\Filament\Pages;

use App\Models\GlobalSetting;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

class GlobalSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Global Settings';

    protected static ?int $navigationSort = 999;

    protected static ?string $title = 'Global Settings';

    protected static ?string $slug = 'global-settings';

    protected string $view = 'filament.pages.global-settings';

    protected Width|string|null $maxContentWidth = Width::Full;

    public array $formData = [];

    public function mount(): void
    {
        $settings = GlobalSetting::query()->first();

        if (! $settings) {
            $settings = GlobalSetting::query()->create([
                'devtools_enabled' => (bool) config('devtools.enabled'),
                'devtools_environments' => implode(',', config('devtools.allowed_environments', [])),
            ]);
        }

        $this->form->fill([
            'devtools_enabled' => (bool) $settings->devtools_enabled,
            'devtools_environments' => (string) $settings->devtools_environments,
        ]);
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Developer Tools')
                    ->description('Controls access to temporary developer-only tools and calculators.')
                    ->schema([
                        Toggle::make('devtools_enabled')
                            ->label('Enable developer tools')
                            ->helperText('Keep disabled in production unless explicitly needed.'),
                        TextInput::make('devtools_environments')
                            ->label('Allowed environments')
                            ->helperText('Comma-separated environment names (example: local,staging).')
                            ->placeholder('local,staging'),
                    ])
                    ->columns(2),
            ])
            ->statePath('formData');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $settings = GlobalSetting::query()->first();

        if (! $settings) {
            $settings = new GlobalSetting();
        }

        $settings->devtools_enabled = (bool) ($data['devtools_enabled'] ?? false);
        $settings->devtools_environments = $this->normalizeEnvList((string) ($data['devtools_environments'] ?? ''));
        $settings->save();

        Notification::make()
            ->title('Global settings saved')
            ->success()
            ->send();
    }

    protected function normalizeEnvList(string $value): string
    {
        $parts = array_filter(array_map('trim', explode(',', $value)));

        return implode(',', $parts);
    }
}
