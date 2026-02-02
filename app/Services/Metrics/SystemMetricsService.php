<?php

namespace App\Services\Metrics;

use App\Models\SystemMetric;
use App\Support\EngineSettings;
use Illuminate\Support\Arr;

class SystemMetricsService
{
    public function capture(): ?SystemMetric
    {
        $source = EngineSettings::metricsSource();
        $interval = max(1, (int) config('engine.metrics_sample_interval_seconds', 60));

        $last = SystemMetric::query()
            ->where('source', $source)
            ->latest('captured_at')
            ->first();

        if ($last && $last->captured_at && $last->captured_at->diffInSeconds(now()) < $interval) {
            return null;
        }

        $snapshot = $source === 'host'
            ? $this->captureHostSnapshot()
            : $this->captureContainerSnapshot();

        if ($snapshot === null) {
            return null;
        }

        $capturedAt = now();
        $cpuPercent = $this->computeCpuPercent($snapshot, $last, $capturedAt);
        $ioReadMb = $this->computeDeltaMb($snapshot['io_read_bytes_total'] ?? null, $last?->io_read_bytes_total);
        $ioWriteMb = $this->computeDeltaMb($snapshot['io_write_bytes_total'] ?? null, $last?->io_write_bytes_total);
        $netInMb = $this->computeDeltaMb($snapshot['net_in_bytes_total'] ?? null, $last?->net_in_bytes_total);
        $netOutMb = $this->computeDeltaMb($snapshot['net_out_bytes_total'] ?? null, $last?->net_out_bytes_total);

        return SystemMetric::create([
            'source' => $source,
            'captured_at' => $capturedAt,
            'cpu_percent' => $cpuPercent,
            'cpu_total_ticks' => $snapshot['cpu_total_ticks'] ?? null,
            'cpu_idle_ticks' => $snapshot['cpu_idle_ticks'] ?? null,
            'mem_total_mb' => $snapshot['mem_total_mb'] ?? null,
            'mem_used_mb' => $snapshot['mem_used_mb'] ?? null,
            'disk_total_gb' => $snapshot['disk_total_gb'] ?? null,
            'disk_used_gb' => $snapshot['disk_used_gb'] ?? null,
            'io_read_mb' => $ioReadMb,
            'io_write_mb' => $ioWriteMb,
            'io_read_bytes_total' => $snapshot['io_read_bytes_total'] ?? null,
            'io_write_bytes_total' => $snapshot['io_write_bytes_total'] ?? null,
            'net_in_mb' => $netInMb,
            'net_out_mb' => $netOutMb,
            'net_in_bytes_total' => $snapshot['net_in_bytes_total'] ?? null,
            'net_out_bytes_total' => $snapshot['net_out_bytes_total'] ?? null,
        ]);
    }

    /**
     * @return array<string, int|null>|null
     */
    private function captureHostSnapshot(): ?array
    {
        $cpu = $this->readProcStat();
        $mem = $this->readMemInfo();
        $disk = $this->readDiskStats();
        $net = $this->readNetStats();

        return [
            'cpu_total_ticks' => Arr::get($cpu, 'total'),
            'cpu_idle_ticks' => Arr::get($cpu, 'idle'),
            'mem_total_mb' => Arr::get($mem, 'total_mb'),
            'mem_used_mb' => Arr::get($mem, 'used_mb'),
            'disk_total_gb' => Arr::get($disk, 'total_gb'),
            'disk_used_gb' => Arr::get($disk, 'used_gb'),
            'io_read_bytes_total' => Arr::get($disk, 'read_bytes_total'),
            'io_write_bytes_total' => Arr::get($disk, 'write_bytes_total'),
            'net_in_bytes_total' => Arr::get($net, 'in_bytes_total'),
            'net_out_bytes_total' => Arr::get($net, 'out_bytes_total'),
        ];
    }

    /**
     * @return array<string, int|null>|null
     */
    private function captureContainerSnapshot(): ?array
    {
        $cpu = $this->readCgroupCpu();
        $mem = $this->readCgroupMemory();

        $fallback = $this->captureHostSnapshot();

        return [
            'cpu_total_ticks' => $cpu['usage_ns'] ?? Arr::get($fallback, 'cpu_total_ticks'),
            'cpu_idle_ticks' => $cpu['usage_ns'] !== null ? null : Arr::get($fallback, 'cpu_idle_ticks'),
            'mem_total_mb' => $mem['total_mb'] ?? Arr::get($fallback, 'mem_total_mb'),
            'mem_used_mb' => $mem['used_mb'] ?? Arr::get($fallback, 'mem_used_mb'),
            'disk_total_gb' => Arr::get($fallback, 'disk_total_gb'),
            'disk_used_gb' => Arr::get($fallback, 'disk_used_gb'),
            'io_read_bytes_total' => Arr::get($fallback, 'io_read_bytes_total'),
            'io_write_bytes_total' => Arr::get($fallback, 'io_write_bytes_total'),
            'net_in_bytes_total' => Arr::get($fallback, 'net_in_bytes_total'),
            'net_out_bytes_total' => Arr::get($fallback, 'net_out_bytes_total'),
        ];
    }

    private function computeCpuPercent(array $snapshot, ?SystemMetric $last, $capturedAt): ?float
    {
        $total = $snapshot['cpu_total_ticks'] ?? null;
        $idle = $snapshot['cpu_idle_ticks'] ?? null;

        if (! $last || $total === null) {
            return null;
        }

        if ($idle === null) {
            $deltaUsage = max(0, $total - (int) $last->cpu_total_ticks);
            $elapsed = max(1, $last->captured_at?->diffInSeconds($capturedAt) ?? 1);
            $deltaSeconds = $deltaUsage / 1_000_000_000;

            return round(min(100, max(0, ($deltaSeconds / $elapsed) * 100)), 2);
        }

        if ($last->cpu_total_ticks === null || $last->cpu_idle_ticks === null) {
            return null;
        }

        $deltaTotal = $total - (int) $last->cpu_total_ticks;
        $deltaIdle = $idle - (int) $last->cpu_idle_ticks;

        if ($deltaTotal <= 0) {
            return null;
        }

        return round(min(100, max(0, (1 - ($deltaIdle / $deltaTotal)) * 100)), 2);
    }

    private function computeDeltaMb(?int $current, ?int $previous): ?int
    {
        if ($current === null || $previous === null) {
            return null;
        }

        $delta = max(0, $current - $previous);

        return (int) round($delta / 1024 / 1024);
    }

    /**
     * @return array{total: int, idle: int}|null
     */
    private function readProcStat(): ?array
    {
        $lines = @file('/proc/stat');
        if (! $lines) {
            return null;
        }

        $parts = preg_split('/\s+/', trim($lines[0] ?? ''));
        if (! $parts || $parts[0] !== 'cpu') {
            return null;
        }

        array_shift($parts);
        $values = array_map('intval', $parts);
        $total = array_sum($values);
        $idle = ($values[3] ?? 0) + ($values[4] ?? 0);

        return [
            'total' => $total,
            'idle' => $idle,
        ];
    }

    /**
     * @return array{total_mb: int, used_mb: int}|null
     */
    private function readMemInfo(): ?array
    {
        $lines = @file('/proc/meminfo');
        if (! $lines) {
            return null;
        }

        $data = [];
        foreach ($lines as $line) {
            if (preg_match('/^(\w+):\s+(\d+)/', $line, $matches)) {
                $data[$matches[1]] = (int) $matches[2];
            }
        }

        if (! isset($data['MemTotal'])) {
            return null;
        }

        $totalKb = $data['MemTotal'];
        $availableKb = $data['MemAvailable'] ?? ($data['MemFree'] ?? 0);
        $usedKb = max(0, $totalKb - $availableKb);

        return [
            'total_mb' => (int) round($totalKb / 1024),
            'used_mb' => (int) round($usedKb / 1024),
        ];
    }

    /**
     * @return array{total_gb: int, used_gb: int, read_bytes_total: int|null, write_bytes_total: int|null}|null
     */
    private function readDiskStats(): ?array
    {
        $path = base_path();
        $totalBytes = @disk_total_space($path);
        $freeBytes = @disk_free_space($path);

        if ($totalBytes === false || $freeBytes === false) {
            return null;
        }

        $usedBytes = max(0, $totalBytes - $freeBytes);
        $device = $this->detectDeviceForPath($path);
        $io = $device ? $this->readDiskIoTotals($device) : ['read_bytes_total' => null, 'write_bytes_total' => null];

        return [
            'total_gb' => (int) round($totalBytes / 1024 / 1024 / 1024),
            'used_gb' => (int) round($usedBytes / 1024 / 1024 / 1024),
            'read_bytes_total' => $io['read_bytes_total'] ?? null,
            'write_bytes_total' => $io['write_bytes_total'] ?? null,
        ];
    }

    /**
     * @return array{in_bytes_total: int, out_bytes_total: int}|null
     */
    private function readNetStats(): ?array
    {
        $lines = @file('/proc/net/dev');
        if (! $lines) {
            return null;
        }

        $inBytes = 0;
        $outBytes = 0;

        foreach ($lines as $index => $line) {
            if ($index < 2) {
                continue;
            }

            $parts = preg_split('/\s+/', trim($line));
            if (! $parts || count($parts) < 10) {
                continue;
            }

            $interface = rtrim($parts[0], ':');
            if ($interface === 'lo') {
                continue;
            }

            $inBytes += (int) $parts[1];
            $outBytes += (int) $parts[9];
        }

        return [
            'in_bytes_total' => $inBytes,
            'out_bytes_total' => $outBytes,
        ];
    }

    private function detectDeviceForPath(string $path): ?string
    {
        $command = 'df -P '.escapeshellarg($path).' 2>/dev/null';
        $output = @shell_exec($command);

        if (! $output) {
            return null;
        }

        $lines = preg_split('/\n/', trim($output));
        if (! $lines || count($lines) < 2) {
            return null;
        }

        $columns = preg_split('/\s+/', trim($lines[1]));
        $device = $columns[0] ?? null;

        if (! $device) {
            return null;
        }

        if (str_starts_with($device, '/dev/')) {
            return substr($device, 5);
        }

        return $device;
    }

    /**
     * @return array{read_bytes_total: int|null, write_bytes_total: int|null}
     */
    private function readDiskIoTotals(string $device): array
    {
        $lines = @file('/proc/diskstats');
        if (! $lines) {
            return ['read_bytes_total' => null, 'write_bytes_total' => null];
        }

        foreach ($lines as $line) {
            $parts = preg_split('/\s+/', trim($line));
            if (! $parts || count($parts) < 14) {
                continue;
            }

            $name = $parts[2] ?? '';
            if ($name !== $device) {
                continue;
            }

            $sectorsRead = (int) ($parts[5] ?? 0);
            $sectorsWritten = (int) ($parts[9] ?? 0);
            $sectorSize = 512;

            return [
                'read_bytes_total' => $sectorsRead * $sectorSize,
                'write_bytes_total' => $sectorsWritten * $sectorSize,
            ];
        }

        return ['read_bytes_total' => null, 'write_bytes_total' => null];
    }

    /**
     * @return array{usage_ns: int|null}
     */
    private function readCgroupCpu(): array
    {
        $usage = null;

        $v2Stat = @file('/sys/fs/cgroup/cpu.stat');
        if ($v2Stat) {
            foreach ($v2Stat as $line) {
                if (str_starts_with($line, 'usage_usec')) {
                    $parts = preg_split('/\s+/', trim($line));
                    $usage = isset($parts[1]) ? ((int) $parts[1]) * 1000 : null;
                    break;
                }
            }
        }

        if ($usage === null) {
            $v1Usage = @file_get_contents('/sys/fs/cgroup/cpuacct/cpuacct.usage');
            if ($v1Usage !== false) {
                $usage = (int) trim($v1Usage);
            }
        }

        return ['usage_ns' => $usage];
    }

    /**
     * @return array{total_mb: int|null, used_mb: int|null}
     */
    private function readCgroupMemory(): array
    {
        $limit = null;
        $usage = null;

        $limitV2 = @file_get_contents('/sys/fs/cgroup/memory.max');
        if ($limitV2 !== false) {
            $limit = trim($limitV2) === 'max' ? null : (int) trim($limitV2);
        }

        $usageV2 = @file_get_contents('/sys/fs/cgroup/memory.current');
        if ($usageV2 !== false) {
            $usage = (int) trim($usageV2);
        }

        if ($limit === null) {
            $limitV1 = @file_get_contents('/sys/fs/cgroup/memory/memory.limit_in_bytes');
            if ($limitV1 !== false) {
                $limit = (int) trim($limitV1);
            }
        }

        if ($usage === null) {
            $usageV1 = @file_get_contents('/sys/fs/cgroup/memory/memory.usage_in_bytes');
            if ($usageV1 !== false) {
                $usage = (int) trim($usageV1);
            }
        }

        if ($limit === null && $usage === null) {
            return ['total_mb' => null, 'used_mb' => null];
        }

        return [
            'total_mb' => $limit !== null ? (int) round($limit / 1024 / 1024) : null,
            'used_mb' => $usage !== null ? (int) round($usage / 1024 / 1024) : null,
        ];
    }
}
