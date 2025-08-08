<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;

class ServerInfo extends Controller
{
    private const TEST_DURATION = 10; // seconds
    private const PING_LATENCY = 1000; // microseconds
    private const ITERATION_SLEEP = 100000; // microseconds
    private const BASE_DOWNLOAD_SIZE = 256 * 1024; // 256KB
    private const DOWNLOAD_SIZE_VARIATION = 128 * 1024; // 128KB
    private const BYTES_TO_KB = 1024;
    private const BYTES_TO_MB = 1024 * 1024;
    private const BYTES_TO_GB = 1024 * 1024 * 1024;
    
    public function requestPerformance(): JsonResponse
    {
        set_time_limit(30);
        $testStartTime = microtime(true);
        
        $metrics = $this->initializeMetrics();
        $uploadSize = $this->getUploadSize();
        $iteration = 0;

        // Run performance test loop
        while ((microtime(true) - $testStartTime) < self::TEST_DURATION) {
            $iterationMetrics = $this->runIterationTest($iteration, $uploadSize);
            $this->collectMetrics($metrics, $iterationMetrics);
            $iteration++;
            usleep(self::ITERATION_SLEEP);
        }

        $statistics = $this->calculateStatistics($metrics);
        
        return response()->json([
            'speedtest_results' => $this->formatSpeedTestResults($statistics, $iteration),
            'client_info' => $this->getClientInfo(),
            'test_info' => $this->getTestInfo($iteration)
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    private function initializeMetrics(): array
    {
        return [
            'ping' => [],
            'download_speeds' => [],
            'upload_speeds' => [],
            'response_times' => []
        ];
    }

    private function getUploadSize(): int
    {
        $uploadData = request()->all();
        return strlen(json_encode($uploadData));
    }

    private function runIterationTest(int $iteration, int $uploadSize): array
    {
        $iterationStart = microtime(true);
        
        // Ping simulation
        $ping = $this->simulatePing();
        
        // Download simulation
        $downloadDataSize = self::BASE_DOWNLOAD_SIZE + ($iteration % 4) * self::DOWNLOAD_SIZE_VARIATION;
        $downloadData = str_repeat('A', $downloadDataSize); // Simulated data
        
        // Calculate response time
        $responseTime = (microtime(true) - $iterationStart) * 1000; // ms
        
        // Calculate speeds
        $uploadSpeed = $responseTime > 0 ? ($uploadSize / self::BYTES_TO_KB) / ($responseTime / 1000) : 0;
        $downloadSpeed = $responseTime > 0 ? ($downloadDataSize / self::BYTES_TO_KB) / ($responseTime / 1000) : 0;
        
        return [
            'ping' => $ping,
            'upload_speed' => round($uploadSpeed, 2),
            'download_speed' => round($downloadSpeed, 2),
            'response_time' => $responseTime
        ];
    }

    private function simulatePing(): float
    {
        $pingStart = microtime(true);
        usleep(self::PING_LATENCY);
        $pingEnd = microtime(true);
        return round(($pingEnd - $pingStart) * 1000, 2); // Convert to ms
    }

    private function collectMetrics(array &$metrics, array $iterationMetrics): void
    {
        $metrics['ping'][] = $iterationMetrics['ping'];
        $metrics['upload_speeds'][] = $iterationMetrics['upload_speed'];
        $metrics['download_speeds'][] = $iterationMetrics['download_speed'];
        $metrics['response_times'][] = $iterationMetrics['response_time'];
    }

    private function calculateStatistics(array $metrics): array
    {
        return [
            'ping' => $this->calculatePingStatistics($metrics['ping']),
            'download' => $this->calculateSpeedStatistics($metrics['download_speeds']),
            'upload' => $this->calculateSpeedStatistics($metrics['upload_speeds']),
            'response_time' => $this->calculateResponseTimeStatistics($metrics['response_times'])
        ];
    }

    private function calculatePingStatistics(array $pingResults): array
    {
        $avg = round(array_sum($pingResults) / count($pingResults), 2);
        $min = round(min($pingResults), 2);
        $max = round(max($pingResults), 2);
        $jitter = $this->calculateJitter($pingResults);
        
        return compact('avg', 'min', 'max', 'jitter');
    }

    private function calculateSpeedStatistics(array $speeds): array
    {
        $avg = round(array_sum($speeds) / count($speeds), 2);
        $min = round(min($speeds), 2);
        $max = round(max($speeds), 2);
        
        return compact('avg', 'min', 'max');
    }

    private function calculateResponseTimeStatistics(array $responseTimes): array
    {
        $avg = round(array_sum($responseTimes) / count($responseTimes), 2);
        return compact('avg');
    }

    private function calculateJitter(array $pingResults): float
    {
        if (count($pingResults) <= 1) {
            return 0;
        }
        
        $jitter = 0;
        for ($i = 1; $i < count($pingResults); $i++) {
            $jitter += abs($pingResults[$i] - $pingResults[$i - 1]);
        }
        
        return round($jitter / (count($pingResults) - 1), 2);
    }

    private function formatSpeedTestResults(array $statistics, int $iteration): array
    {
        $uploadSize = $this->getUploadSize();
        
        return [
            'test_duration' => "Test duration: " . self::TEST_DURATION . " seconds",
            'total_iterations' => "Total iterations: {$iteration}",
            'ping' => [
                'average' => "Average ping: {$statistics['ping']['avg']} ms",
                'min' => "Min ping: {$statistics['ping']['min']} ms",
                'max' => "Max ping: {$statistics['ping']['max']} ms",
                'jitter' => "Jitter: {$statistics['ping']['jitter']} ms"
            ],
            'download' => [
                'average_speed' => "Average download speed: " . $this->convertSpeedToMbps($statistics['download']['avg']),
                'max_speed' => "Max download speed: " . $this->convertSpeedToKbpsOrMbps($statistics['download']['max']),
                'min_speed' => "Min download speed: " . $this->convertSpeedToKbpsOrMbps($statistics['download']['min'])
            ],
            'upload' => [
                'average_speed' => "Average upload speed: " . $this->convertSpeedToMbps($statistics['upload']['avg']),
                'max_speed' => "Max upload speed: " . $this->convertSpeedToKbpsOrMbps($statistics['upload']['max']),
                'min_speed' => "Min upload speed: " . $this->convertSpeedToKbpsOrMbps($statistics['upload']['min']),
                'data_size' => "Upload data size: " . round($uploadSize / self::BYTES_TO_KB, 2) . " KB"
            ],
            'response_time' => [
                'average' => "Average response time: {$statistics['response_time']['avg']} ms"
            ]
        ];
    }

    private function getClientInfo(): array
    {
        return [
            'ip' => "Client IP: " . request()->ip(),
            'user_agent' => "User agent: " . request()->userAgent(),
        ];
    }

    private function getTestInfo(int $iteration): array
    {
        return [
            'timestamp' => "Timestamp: " . time(),
            'server_time' => "Server time: " . date('Y-m-d H:i:s'),
            'note' => 'Speedtest performed for ' . self::TEST_DURATION . ' seconds with ' . $iteration . ' iterations to ensure accurate results.'
        ];
    }

    // Convert speed to Mbps if the value is large, otherwise keep it in Kbps
    private function convertSpeedToMbps(float $speed): string
    {
        return $speed >= self::BYTES_TO_KB ? 
            round($speed / self::BYTES_TO_KB, 2) . ' Mbps' : 
            round($speed, 2) . ' Kbps';
    }

    // Convert speed to the appropriate unit: Kbps or Mbps
    private function convertSpeedToKbpsOrMbps(float $speed): string
    {
        return $speed >= 1000 ? 
            round($speed / self::BYTES_TO_KB, 2) . ' Mbps' : 
            round($speed, 2) . ' Kbps';
    }

    private function formatResponseTime(float $timeMs): string
    {
        return match(true) {
            $timeMs < 1 => round($timeMs * 1000, 0) . ' μs',
            $timeMs < 1000 => round($timeMs, 1) . ' ms',
            default => round($timeMs / 1000, 2) . ' giây'
        };
    }

    private function formatSpeed(float $speedMbps): string
    {
        return match(true) {
            $speedMbps < 1 => round($speedMbps * self::BYTES_TO_KB, 1) . ' Kbps',
            $speedMbps < 1000 => round($speedMbps, 2) . ' Mbps',
            default => round($speedMbps / 1000, 2) . ' Gbps'
        };
    }

    private function getPingQuality(float $pingMs): string
    {
        return match(true) {
            $pingMs < 20 => 'Xuất sắc',
            $pingMs < 50 => 'Tốt',
            $pingMs < 100 => 'Trung bình',
            $pingMs < 200 => 'Chậm',
            default => 'Rất chậm'
        };
    }

    private function getResponseTimeQuality(float $timeMs): string
    {
        return match(true) {
            $timeMs < 100 => 'Rất nhanh',
            $timeMs < 300 => 'Nhanh',
            $timeMs < 500 => 'Bình thường',
            $timeMs < 1000 => 'Chậm',
            default => 'Rất chậm'
        };
    }

    public function check(): JsonResponse
    {
        $systemInfo = $this->getSystemInfo();
        
        // Clean up generated files if disk space is low
        if ($systemInfo['percent_free'] < 10) {
            File::deleteDirectory(base_path('generated'));
        }
        
        return response()->json($systemInfo);
    }

    private function getSystemInfo(): array
    {
        $path = base_path();
        $freeBytes = disk_free_space($path) ?: 0;
        $totalBytes = disk_total_space($path) ?: 0;
        $percentFree = $totalBytes > 0 ? round($freeBytes / $totalBytes * 100, 2) : 0.0;
        
        $cpuLoad = function_exists('sys_getloadavg') ? sys_getloadavg() : [0];
        $loadAverage = function_exists('sys_getloadavg') ? sys_getloadavg() : [0];
        
        $totalRam = $this->getTotalRam();
        $freeRam = $this->getFreeRam();
        $percentRamFree = $totalRam > 0 ? round($freeRam / $totalRam * 100, 2) : 0.0;
        
        return [
            'path' => $path,
            'free_gb' => $this->toGigabytes($freeBytes),
            'total_gb' => $this->toGigabytes($totalBytes),
            'percent_free' => $percentFree,
            'cpu_load' => $cpuLoad[0],
            'cpu_cores' => $this->getCpuCores(),
            'cpu_model' => $this->getCpuModel(),
            'total_ram_gb' => $this->toGigabytes($totalRam),
            'free_ram_gb' => $this->toGigabytes($freeRam),
            'percent_ram_free' => $percentRamFree,
            'os' => $this->getOperatingSystem(),
            'php_version' => $this->getPhpVersion(),
            'load_average' => $loadAverage,
        ];
    }

    private function toGigabytes(int|float $bytes): float
    {
        return round($bytes / self::BYTES_TO_GB, 2);
    }

    private function getTotalRam(): int
    {
        if (!file_exists('/proc/meminfo')) {
            return 0;
        }
        
        $memInfo = file_get_contents('/proc/meminfo');
        if (!$memInfo) {
            return 0;
        }
        
        preg_match('/MemTotal:\s+(\d+)/', $memInfo, $matches);
        return isset($matches[1]) ? (int) $matches[1] : 0;
    }

    private function getFreeRam(): int
    {
        if (!file_exists('/proc/meminfo')) {
            return 0;
        }
        
        $memInfo = file_get_contents('/proc/meminfo');
        if (!$memInfo) {
            return 0;
        }
        
        preg_match('/MemFree:\s+(\d+)/', $memInfo, $matches);
        return isset($matches[1]) ? (int) $matches[1] : 0;
    }

    private function getCpuCores(): int
    {
        $cpuCores = shell_exec('nproc');
        return $cpuCores ? (int) trim($cpuCores) : 0;
    }

    private function getCpuModel(): string
    {
        $cpuModel = shell_exec("cat /proc/cpuinfo | grep 'model name' | head -n 1 | cut -d: -f2");
        return $cpuModel ? trim($cpuModel) : 'Unknown';
    }

    private function getOperatingSystem(): string
    {
        $os = shell_exec('uname -a');
        return $os ? trim($os) : 'Unknown';
    }

    private function getPhpVersion(): string
    {
        $phpVersion = shell_exec('php -v | head -n 1');
        return $phpVersion ? trim($phpVersion) : 'Unknown';
    }

    private function getLoadAverage(): array
    {
        return function_exists('sys_getloadavg') ? sys_getloadavg() : [0, 0, 0];
    }
}
