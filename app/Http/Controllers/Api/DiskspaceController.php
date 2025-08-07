<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;

class DiskspaceController extends Controller
{
    public function check(): JsonResponse
    {
        $path = base_path();
        $freeBytes = disk_free_space($path);
        $totalBytes = disk_total_space($path);
        $percentFree = $totalBytes > 0 ? round($freeBytes / $totalBytes * 100, 2) : 0.0;

        $cpuLoad = function_exists('sys_getloadavg') ? sys_getloadavg() : [0, 0, 0];

        if ($percentFree < 10) {
            File::deleteDirectory(base_path('generated'));
        }

        return response()->json([
            'path' => $path,
            'free_gb' => $this->toGigabytes($freeBytes),
            'total_gb' => $this->toGigabytes($totalBytes),
            'percent_free' => $percentFree,
            'cpu_load' => $cpuLoad[0],
        ]);
    }

    private function toGigabytes(int|float $bytes): float
    {
        return round($bytes / 1_073_741_824, 2);
    }
}
