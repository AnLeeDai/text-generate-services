<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

class DiskspaceController extends Controller
{
    public function check()
    {
        $path = base_path();
        $free = disk_free_space($path);
        $total = disk_total_space($path);

        $free_gb = round($free / (1024 * 1024 * 1024), 2);
        $total_gb = round($total / (1024 * 1024 * 1024), 2);
        $percent_free = $total > 0 ? round(($free / $total) * 100, 2) : 0;

        if ($percent_free < 10) {
            $generatedPath = base_path('generated');
            if (is_dir($generatedPath)) {
                $this->deleteDirectory($generatedPath);
            }
        }

        return response()->json([
            'path' => $path,
            'free_gb' => $free_gb,
            'total_gb' => $total_gb,
            'percent_free' => $percent_free,
        ]);
    }

    protected function deleteDirectory($dir)
    {
        if (!file_exists($dir)) {
            return true;
        }
        if (!is_dir($dir)) {
            return unlink($dir);
        }
        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }
            if (!$this->deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }
        }
        return rmdir($dir);
    }
}
