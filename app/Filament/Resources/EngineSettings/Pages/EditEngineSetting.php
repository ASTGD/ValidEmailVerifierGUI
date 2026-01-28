<?php

namespace App\Filament\Resources\EngineSettings\Pages;

use App\Filament\Resources\EngineSettings\EngineSettingResource;
use App\Filament\Resources\EngineSettings\Pages\Concerns\HandlesPolicySettings;
use App\Services\EmailVerificationCache\CacheHealthCheckService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Carbon;

class EditEngineSetting extends EditRecord
{
    use HandlesPolicySettings;

    protected static string $resource = EngineSettingResource::class;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $cacheHealthCheck = null;

    public string $cacheHealthCheckEmails = '';

    public function getTitle(): string
    {
        return 'Settings';
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $this->fillPolicyFormData($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = $this->normalizeMonitorResolver($data);

        return $this->capturePolicyData($data);
    }

    protected function afterSave(): void
    {
        $this->persistPolicyData();
    }

    public function runCacheHealthCheck(CacheHealthCheckService $service): void
    {
        $emails = collect(preg_split('/[\r\n,]+/', $this->cacheHealthCheckEmails))
            ->map(fn ($email) => trim((string) $email))
            ->filter()
            ->values()
            ->all();

        $result = $service->check($emails);
        $result['checked_at'] = Carbon::now()->toDateTimeString();
        $this->cacheHealthCheck = $result;

        $notification = Notification::make()
            ->title($result['ok'] ? 'Cache health check passed' : 'Cache health check failed')
            ->body($result['message'] ?? null);

        if ($result['ok']) {
            $notification->success();
        } else {
            $notification->danger();
        }

        $notification->send();
    }

    /**
     * @return array<string, mixed>
     */
    public function cacheHealthCheckViewData(): array
    {
        return [
            'healthCheck' => $this->cacheHealthCheck,
            'healthCheckEmails' => $this->cacheHealthCheckEmails,
        ];
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
