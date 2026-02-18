<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\EngineServers\EngineServerResource;
use App\Models\User;
use App\Support\EngineServerFallbackAccess;
use Filament\Widgets\Widget;

class OpsVerifierEngineLinks extends Widget
{
    protected string $view = 'filament.widgets.ops-verifier-engine-links';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 5;

    protected function getViewData(): array
    {
        $goWorkersUrl = $this->resolveGoWorkersUrl();

        $user = auth()->user();
        $fallbackUrl = null;
        if ($user instanceof User && EngineServerFallbackAccess::userCanAccess($user)) {
            $fallbackUrl = EngineServerResource::getUrl('index');
        }

        return [
            'goWorkersUrl' => $goWorkersUrl,
            'fallbackUrl' => $fallbackUrl,
        ];
    }

    private function resolveGoWorkersUrl(): ?string
    {
        $configuredUrl = rtrim((string) config('services.go_control_plane.base_url', ''), '/');
        if ($configuredUrl === '') {
            return null;
        }

        if (! str_contains($configuredUrl, '/verifier-engine-room')) {
            return $configuredUrl.'/verifier-engine-room/workers';
        }

        $normalized = preg_replace('#/verifier-engine-room(?:/(?:overview|alerts|pools|settings|workers))?$#', '/verifier-engine-room', $configuredUrl);
        $normalized = is_string($normalized) ? rtrim($normalized, '/') : $configuredUrl;

        return $normalized.'/workers';
    }
}
