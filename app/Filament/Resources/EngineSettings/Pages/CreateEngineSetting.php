<?php

namespace App\Filament\Resources\EngineSettings\Pages;

use App\Filament\Resources\EngineSettings\EngineSettingResource;
use App\Filament\Resources\EngineSettings\Pages\Concerns\HandlesPolicySettings;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateEngineSetting extends CreateRecord
{
    use HandlesPolicySettings;

    protected static string $resource = EngineSettingResource::class;

    public function getTitle(): string
    {
        return 'Settings';
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = $this->normalizeMonitorResolver($data);

        return $this->capturePolicyData($data);
    }

    protected function afterCreate(): void
    {
        $this->persistPolicyData();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeMonitorResolver(array $data): array
    {
        $mode = (string) ($data['monitor_dns_mode'] ?? 'system');
        if ($mode !== 'custom') {
            return $data;
        }

        $ip = trim((string) ($data['monitor_dns_server_ip'] ?? ''));
        $portValue = $data['monitor_dns_server_port'] ?? null;
        $port = is_numeric($portValue) ? (int) $portValue : 0;

        $ipValid = filter_var($ip, FILTER_VALIDATE_IP) !== false;
        $portValid = $port >= 1 && $port <= 65535;

        if ($ipValid && $portValid) {
            return $data;
        }

        $data['monitor_dns_mode'] = 'system';
        $data['monitor_dns_server_ip'] = null;
        $data['monitor_dns_server_port'] = 53;

        Notification::make()
            ->title('Custom DNS settings incomplete')
            ->body('Saved with System DNS (host) instead.')
            ->warning()
            ->send();

        return $data;
    }
}
