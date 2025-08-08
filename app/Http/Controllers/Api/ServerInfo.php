<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use File;

class ServerInfo extends Controller
{
    public function requestPerformance()
    {
        set_time_limit(30);
        $testStartTime = microtime(true);
        $testDuration = 10;
        $pingResults = [];
        $downloadSpeeds = [];
        $uploadSpeeds = [];
        $responseTimes = [];
        $uploadData = request()->all();
        $uploadSize = strlen(json_encode($uploadData));
        $iteration = 0;
        while ((microtime(true) - $testStartTime) < $testDuration) {
            $iterationStart = microtime(true);
            $pingStart = microtime(true);
            usleep(1000);
            $pingEnd = microtime(true);
            $ping = round(($pingEnd - $pingStart) * 1000, 2);
            $pingResults[] = $ping;
            $downloadDataSize = (256 + ($iteration % 4) * 128) * 1024;
            $downloadData = str_repeat('A', $downloadDataSize);
            $iterationEnd = microtime(true);
            $responseTime = ($iterationEnd - $iterationStart) * 1000;
            $responseTimes[] = $responseTime;
            if ($responseTime > 0) {
                $uploadSpeed = round(($uploadSize / 1024) / ($responseTime / 1000), 2);
                $uploadSpeeds[] = $uploadSpeed;
                $downloadSpeed = round(($downloadDataSize / 1024) / ($responseTime / 1000), 2);
                $downloadSpeeds[] = $downloadSpeed;
            }
            $iteration++;
            usleep(100000);
        }
        $totalTestTime = (microtime(true) - $testStartTime) * 1000;
        $avgPing = round(array_sum($pingResults) / count($pingResults), 2);
        $minPing = round(min($pingResults), 2);
        $maxPing = round(max($pingResults), 2);
        $avgDownloadSpeed = round(array_sum($downloadSpeeds) / count($downloadSpeeds), 2);
        $maxDownloadSpeed = round(max($downloadSpeeds), 2);
        $minDownloadSpeed = round(min($downloadSpeeds), 2);
        $avgUploadSpeed = round(array_sum($uploadSpeeds) / count($uploadSpeeds), 2);
        $maxUploadSpeed = round(max($uploadSpeeds), 2);
        $minUploadSpeed = round(min($uploadSpeeds), 2);
        $avgResponseTime = round(array_sum($responseTimes) / count($responseTimes), 2);
        $avgDownloadMbps = round($avgDownloadSpeed * 8 / 1024, 2);
        $avgUploadMbps = round($avgUploadSpeed * 8 / 1024, 2);
        $jitter = 0;
        for ($i = 1; $i < count($pingResults); $i++) {
            $jitter += abs($pingResults[$i] - $pingResults[$i-1]);
        }
        $jitter = count($pingResults) > 1 ? round($jitter / (count($pingResults) - 1), 2) : 0;
        return response()->json([
            'speedtest_results' => [
                'test_duration_seconds' => $testDuration,
                'actual_test_time_ms' => round($totalTestTime, 2),
                'total_iterations' => $iteration,
                'ping' => [
                    'average_ms' => $avgPing,
                    'min_ms' => $minPing,
                    'max_ms' => $maxPing,
                    'jitter_ms' => $jitter,
                    'formatted' => $this->formatResponseTime($avgPing),
                    'quality' => $this->getPingQuality($avgPing)
                ],
                'download' => [
                    'average_speed_kbps' => $avgDownloadSpeed,
                    'average_speed_mbps' => $avgDownloadMbps,
                    'max_speed_kbps' => $maxDownloadSpeed,
                    'min_speed_kbps' => $minDownloadSpeed,
                    'speed_formatted' => $this->formatSpeed($avgDownloadMbps)
                ],
                'upload' => [
                    'average_speed_kbps' => $avgUploadSpeed,
                    'average_speed_mbps' => $avgUploadMbps,
                    'max_speed_kbps' => $maxUploadSpeed,
                    'min_speed_kbps' => $minUploadSpeed,
                    'speed_formatted' => $this->formatSpeed($avgUploadMbps),
                    'data_size_kb' => round($uploadSize / 1024, 2)
                ],
                'response_time' => [
                    'average_ms' => $avgResponseTime,
                    'formatted' => $this->formatResponseTime($avgResponseTime),
                    'quality' => $this->getResponseTimeQuality($avgResponseTime)
                ]
            ],
            'client_info' => [
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ],
            'test_info' => [
                'timestamp' => time(),
                'server_time' => date('Y-m-d H:i:s'),
                'note' => 'Speedtest chạy trong ' . $testDuration . ' giây với ' . $iteration . ' lần đo để có kết quả chính xác nhất'
            ]
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    private function formatResponseTime($timeMs)
    {
        if ($timeMs < 1) {
            return round($timeMs * 1000, 0) . ' μs';
        } elseif ($timeMs < 1000) {
            return round($timeMs, 1) . ' ms';
        } else {
            return round($timeMs / 1000, 2) . ' giây';
        }
    }

    private function formatSpeed($speedMbps)
    {
        if ($speedMbps < 1) {
            return round($speedMbps * 1024, 1) . ' Kbps';
        } elseif ($speedMbps < 1000) {
            return round($speedMbps, 2) . ' Mbps';
        } else {
            return round($speedMbps / 1000, 2) . ' Gbps';
        }
    }

    private function getPingQuality($pingMs)
    {
        if ($pingMs < 20) return 'Xuất sắc';
        if ($pingMs < 50) return 'Tốt';
        if ($pingMs < 100) return 'Trung bình';
        if ($pingMs < 200) return 'Chậm';
        return 'Rất chậm';
    }

    private function getResponseTimeQuality($timeMs)
    {
        if ($timeMs < 100) return 'Rất nhanh';
        if ($timeMs < 300) return 'Nhanh';
        if ($timeMs < 500) return 'Bình thường';
        if ($timeMs < 1000) return 'Chậm';
        return 'Rất chậm';
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
