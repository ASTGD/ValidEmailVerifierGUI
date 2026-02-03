<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemMetric extends Model
{
    protected $fillable = [
        'source',
        'captured_at',
        'cpu_percent',
        'cpu_total_ticks',
        'cpu_idle_ticks',
        'mem_total_mb',
        'mem_used_mb',
        'disk_total_gb',
        'disk_used_gb',
        'io_read_mb',
        'io_write_mb',
        'io_read_bytes_total',
        'io_write_bytes_total',
        'net_in_mb',
        'net_out_mb',
        'net_in_bytes_total',
        'net_out_bytes_total',
    ];

    protected $casts = [
        'captured_at' => 'datetime',
        'cpu_percent' => 'decimal:2',
        'cpu_total_ticks' => 'integer',
        'cpu_idle_ticks' => 'integer',
        'mem_total_mb' => 'integer',
        'mem_used_mb' => 'integer',
        'disk_total_gb' => 'integer',
        'disk_used_gb' => 'integer',
        'io_read_mb' => 'integer',
        'io_write_mb' => 'integer',
        'io_read_bytes_total' => 'integer',
        'io_write_bytes_total' => 'integer',
        'net_in_mb' => 'integer',
        'net_out_mb' => 'integer',
        'net_in_bytes_total' => 'integer',
        'net_out_bytes_total' => 'integer',
    ];
}
