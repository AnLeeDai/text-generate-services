<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use File;

class ServerInfo extends Controller
{
    public function requestPerformance()
    {
        $executionTimes = [];
        $cpuUsages = [];
        $ramUsages = [];

        $cpuThreshold = 90.0;
        $ramThreshold = 90.0;
        $maxIterations = 10000; // prevent infinite loop

        $iteration = 0;
        while ($iteration < $maxIterations) {
            $startTime = microtime(true);

            // CPU intensive task
            $cpuTask = 0;
            for ($j = 0; $j < 100000; $j++) {
                $cpuTask += sqrt($j);
            }

            // RAM intensive task
            $ramTask = [];
            for ($k = 0; $k < 1000; $k++) {
                $ramTask[] = str_repeat('x', 1024 * 10);
            }

            // Measure CPU load (first value)
            $cpuLoad = function_exists('sys_getloadavg') ? sys_getloadavg()[0] : 0;
            $cpuCores = $this->getCpuCores() ?: 1;
            $cpuPercent = min(100, round($cpuLoad / $cpuCores * 100, 2));
            $cpuUsages[] = $cpuPercent;

            // Measure RAM usage (in MB and percent)
            $ramUsage = memory_get_usage(true) / 1048576;
            $ramUsages[] = round($ramUsage, 2);

            $totalRam = $this->getTotalRam();
            $freeRam = $this->getFreeRam();
            $usedRam = $totalRam - $freeRam;
            $ramPercent = $totalRam > 0 ? round($usedRam / $totalRam * 100, 2) : 0;

            unset($ramTask);

            $endTime = microtime(true);
            $executionTimes[] = ($endTime - $startTime) * 1000;

            if ($cpuPercent >= $cpuThreshold && $ramPercent >= $ramThreshold) {
                break;
            }

            $iteration++;
        }

        $averageTime = round(array_sum($executionTimes) / count($executionTimes), 2);
        $maxTime = round(max($executionTimes), 2);
        $minTime = round(min($executionTimes), 2);

        $averageCpu = round(array_sum($cpuUsages) / count($cpuUsages), 2);
        $maxCpu = round(max($cpuUsages), 2);
        $minCpu = round(min($cpuUsages), 2);

        $averageRam = round(array_sum($ramUsages) / count($ramUsages), 2);
        $maxRam = round(max($ramUsages), 2);
        $minRam = round(min($ramUsages), 2);

        return response()->json([
            'iterations' => $iteration + 1,
            'average_response_time_ms' => $averageTime,
            'max_response_time_ms' => $maxTime,
            'min_response_time_ms' => $minTime,
            'average_cpu_percent' => $averageCpu,
            'max_cpu_percent' => $maxCpu,
            'min_cpu_percent' => $minCpu,
            'average_ram_usage_mb' => $averageRam,
            'max_ram_usage_mb' => $maxRam,
            'min_ram_usage_mb' => $minRam,
            'note' => 'Dừng lại khi CPU và RAM đạt >= 90% hiệu suất hoặc đạt giới hạn lặp.',
        ]);
    }

    public function check()
    {
        $path = base_path();
        $freeBytes = disk_free_space($path) ?: 0;
        $totalBytes = disk_total_space($path) ?: 0;
        $percentFree = $totalBytes > 0 ? round($freeBytes / $totalBytes * 100, 2) : 0.0;

        $cpuLoad = function_exists('sys_getloadavg') ? sys_getloadavg() : [0];
        $cpuCores = $this->getCpuCores() ?: 0;
        $cpuModel = $this->getCpuModel() ?: 'Unknown';

        $totalRam = $this->getTotalRam() ?: 0;
        $freeRam = $this->getFreeRam() ?: 0;
        $percentRamFree = $totalRam > 0 ? round($freeRam / $totalRam * 100, 2) : 0.0;

        $os = $this->getOperatingSystem() ?: 'Unknown';

        $phpVersion = $this->getPhpVersion() ?: 'Unknown';

        $loadAverage = function_exists('sys_getloadavg') ? sys_getloadavg() : [0];

        if ($percentFree < 10) {
            File::deleteDirectory(base_path('generated'));
        }

        return response()->json([
            'path' => $path,
            'free_gb' => $this->toGigabytes($freeBytes),
            'total_gb' => $this->toGigabytes($totalBytes),
            'percent_free' => $percentFree,
            'cpu_load' => $cpuLoad[0],
            'cpu_cores' => $cpuCores,
            'cpu_model' => $cpuModel,
            'total_ram_gb' => $this->toGigabytes($totalRam),
            'free_ram_gb' => $this->toGigabytes($freeRam),
            'percent_ram_free' => $percentRamFree,
            'os' => $os,
            'php_version' => $phpVersion,
            'load_average' => $loadAverage,
        ]);
    }

    private function toGigabytes(int|float $bytes): float
    {
        return round($bytes / 1_073_741_824, 2);
    }

    private function getTotalRam(): int
    {
        $memInfo = file_get_contents("/proc/meminfo");
        preg_match("/MemTotal:\s+(\d+)/", $memInfo, $matches);
        return isset($matches[1]) ? (int) $matches[1] : 0;
    }

    private function getFreeRam(): int
    {
        $memInfo = file_get_contents("/proc/meminfo");
        preg_match("/MemFree:\s+(\d+)/", $memInfo, $matches);
        return isset($matches[1]) ? (int) $matches[1] : 0;
    }

    private function getCpuCores(): int
    {
        $cpuCores = shell_exec("nproc");
        return $cpuCores ? (int) $cpuCores : 0;
    }

    private function getCpuModel(): string
    {
        $cpuModel = shell_exec("cat /proc/cpuinfo | grep 'model name' | head -n 1 | cut -d: -f2");
        return $cpuModel ? trim($cpuModel) : 'Unknown';
    }

    private function getOperatingSystem(): string
    {
        $os = shell_exec("uname -a");
        return $os ? trim($os) : 'Unknown';
    }

    private function getPhpVersion(): string
    {
        $phpVersion = shell_exec("php -v | head -n 1");
        return $phpVersion ? trim($phpVersion) : 'Unknown';
    }

    private function getLoadAverage(): array
    {
        $loadAverage = sys_getloadavg();
        return $loadAverage ?: [0, 0, 0];
    }
}
