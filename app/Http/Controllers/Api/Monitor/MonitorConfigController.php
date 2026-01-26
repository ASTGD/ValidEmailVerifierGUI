<?php

namespace App\Http\Controllers\Api\Monitor;

use App\Support\EngineSettings;
use Illuminate\Http\JsonResponse;

class MonitorConfigController
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'data' => [
                'enabled' => EngineSettings::monitorEnabled(),
                'interval_minutes' => EngineSettings::monitorIntervalMinutes(),
                'rbl_list' => EngineSettings::monitorRblList(),
                'resolver_mode' => EngineSettings::monitorDnsMode(),
                'resolver_ip' => EngineSettings::monitorDnsServerIp(),
                'resolver_port' => EngineSettings::monitorDnsServerPort(),
            ],
        ]);
    }
}
