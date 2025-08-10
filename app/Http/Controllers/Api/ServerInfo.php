<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;

class ServerInfo extends Controller
{
    private const BYTES_TO_GB = 1024 * 1024 * 1024;
    private const KB_TO_BYTES = 1024; // /proc/meminfo values are in KB

    public function check(): JsonResponse
    {
        $systemInfo = $this->getSystemInfo();
        
        // Clean up generated files if disk space is low
        if ($systemInfo['percent_free'] < 10) {
            File::deleteDirectory(base_path('generated'));
        }
        
        return $this->successResponse($systemInfo, 'Server information retrieved successfully');
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
        $usedRam = $totalRam - $freeRam;
        $percentRamUsed = $totalRam > 0 ? round($usedRam / $totalRam * 100, 2) : 0.0;
        $percentRamFree = $totalRam > 0 ? round($freeRam / $totalRam * 100, 2) : 0.0;
        
        return [
            'path' => $path,
            'free_gb' => $this->toGigabytes($freeBytes),
            'total_gb' => $this->toGigabytes($totalBytes),
            'percent_free' => $percentFree,
            'cpu_load' => $cpuLoad[0],
            'cpu_cores' => $this->getCpuCores(),
            'cpu_model' => $this->getCpuModel(),
            'total_ram_gb' => $this->toGigabytes($totalRam * self::KB_TO_BYTES),
            'free_ram_gb' => $this->toGigabytes($freeRam * self::KB_TO_BYTES),
            'used_ram_gb' => $this->toGigabytes($usedRam * self::KB_TO_BYTES),
            'percent_ram_used' => $percentRamUsed,
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
        
        // Get MemFree, Buffers, and Cached for more accurate available memory
        preg_match('/MemFree:\s+(\d+)/', $memInfo, $freeMatches);
        preg_match('/Buffers:\s+(\d+)/', $memInfo, $bufferMatches);
        preg_match('/Cached:\s+(\d+)/', $memInfo, $cachedMatches);
        
        $memFree = isset($freeMatches[1]) ? (int) $freeMatches[1] : 0;
        $buffers = isset($bufferMatches[1]) ? (int) $bufferMatches[1] : 0;
        $cached = isset($cachedMatches[1]) ? (int) $cachedMatches[1] : 0;
        
        // Available memory = Free + Buffers + Cached
        return $memFree + $buffers + $cached;
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
}
