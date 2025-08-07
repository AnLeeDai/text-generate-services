<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use File;

class ServerInfo extends Controller
{
    public function check()
    {
        $path = base_path();
        $freeBytes = disk_free_space($path) ?: 0;
        $totalBytes = disk_total_space($path) ?: 0;
        $percentFree = $totalBytes > 0 ? round($freeBytes / $totalBytes * 100, 2) : 0.0;

        $cpuLoad = function_exists('sys_getloadavg') ? sys_getloadavg() : [0, 0, 0];
        $cpuCores = $this->getCpuCores() ?: 0;
        $cpuModel = $this->getCpuModel() ?: 'Unknown';

        $totalRam = $this->getTotalRam() ?: 0;
        $freeRam = $this->getFreeRam() ?: 0;
        $percentRamFree = $totalRam > 0 ? round($freeRam / $totalRam * 100, 2) : 0.0;

        $os = $this->getOperatingSystem() ?: 'Unknown';

        $phpVersion = $this->getPhpVersion() ?: 'Unknown';

        $loadAverage = function_exists('sys_getloadavg') ? sys_getloadavg() : [0, 0, 0];

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
        $totalRam = shell_exec("free -b | grep Mem | awk '{print $2}'");
        return $totalRam ? (int) $totalRam : 0;
    }

    private function getFreeRam(): int
    {
        $freeRam = shell_exec("free -b | grep Mem | awk '{print $4}'");
        return $freeRam ? (int) $freeRam : 0;
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
