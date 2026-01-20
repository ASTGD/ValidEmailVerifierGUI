<?php

namespace App\Filament\Pages;

use App\Models\PortalSetting;
use Filament\Actions\Action;
use Filament\Schemas\Schema; // USE THIS
use Filament\Schemas\Components\Section; // USE THIS
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use BackedEnum;
use UnitEnum;

class CustomerPortalSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-8-tooth';

    protected static ?string $navigationLabel = 'Portal Settings';

    protected static ?string $title = 'Customer Portal Settings';

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 1000;

    protected string $view = 'filament.pages.customer-portal-settings';

    public ?array $data = [];

    public function mount(): void
    {
        // Load existing settings from DB into the form
        $settings = PortalSetting::pluck('value', 'key')->toArray();
        $this->form->fill($settings);
    }

    /**
     * THE FIX: Changed Argument type to Schema and return type to Schema
     */
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General Branding')
                    ->description('Manage how your portal looks to the customer.')
                    ->schema([
                        TextInput::make('verifier_brand_name')
                            ->label('Portal Title')
                            ->placeholder('ValidEmail Verifier'),
                        Toggle::make('verifier_require_subscription')
                            ->label('Maintenance Mode')
                            ->helperText('Enable this to temporarily block customer access.'),
                    ])->columns(2),

                Section::make('Usage Limits')
                    ->schema([
                        TextInput::make('verifier_checkout_upload_max_mb')
                            ->label('Max File Size (MB)')
                            ->numeric(),
                        TextInput::make('verifier_api_rate_per_minute')
                            ->label('API Rate Limit')
                            ->numeric(),
                    ])->columns(2),

                Section::make('Portal Announcements')
                    ->schema([
                        Textarea::make('portal_welcome_message')
                            ->label('Dashboard Message')
                            ->placeholder('Type a message to show on the customer dashboard...')
                            ->rows(4),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save All Settings')
                ->color('primary')
                ->action('saveSettings'),
        ];
    }

    public function saveSettings(): void
    {
        $state = $this->form->getState();

        foreach ($state as $key => $value) {
            PortalSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        Notification::make()
            ->title('Settings Saved')
            ->success()
            ->send();
    }
}
