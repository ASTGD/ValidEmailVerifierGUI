<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EngineSetting extends Model
{
    protected $fillable = [
        'engine_paused',
        'enhanced_mode_enabled',
        'role_accounts_behavior',
        'role_accounts_list',
        'catch_all_policy',
        'catch_all_promote_threshold',
        'cache_only_mode_enabled',
        'cache_only_miss_status',
        'provider_policies',
        'tempfail_retry_enabled',
        'tempfail_retry_max_attempts',
        'tempfail_retry_backoff_minutes',
        'tempfail_retry_reasons',
        'reputation_window_hours',
        'reputation_min_samples',
        'reputation_tempfail_warn_rate',
        'reputation_tempfail_critical_rate',
        'show_single_checks_in_admin',
        'monitor_enabled',
        'monitor_interval_minutes',
        'monitor_rbl_list',
        'monitor_dns_mode',
        'monitor_dns_server_ip',
        'monitor_dns_server_port',
    ];

    protected $casts = [
        'engine_paused' => 'boolean',
        'enhanced_mode_enabled' => 'boolean',
        'catch_all_promote_threshold' => 'integer',
        'cache_only_mode_enabled' => 'boolean',
        'provider_policies' => 'array',
        'tempfail_retry_enabled' => 'boolean',
        'tempfail_retry_max_attempts' => 'integer',
        'reputation_window_hours' => 'integer',
        'reputation_min_samples' => 'integer',
        'reputation_tempfail_warn_rate' => 'decimal:2',
        'reputation_tempfail_critical_rate' => 'decimal:2',
        'show_single_checks_in_admin' => 'boolean',
        'monitor_enabled' => 'boolean',
        'monitor_interval_minutes' => 'integer',
        'monitor_dns_server_port' => 'integer',
    ];
}
