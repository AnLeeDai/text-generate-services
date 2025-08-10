<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use File;
use ZipArchive;

class FileCheckController extends Controller
{
    public function listFiles()
    {
        $generatedPath = public_path('generated');

        if (File::exists($generatedPath)) {
            $files = File::allFiles($generatedPath);

            if (count($files) > 0) {
                $totalSize = 0;
                $fileCount = 0;
                foreach ($files as $file) {
                    if ($file->getFilename() !== 'files.zip') {
                        $totalSize += $file->getSize();
                        $fileCount++;
                    }
                }

                $diskLimit = 100 * 1024 * 1024;
                $usedPercentage = round(($totalSize / $diskLimit) * 100, 2);

                $diskTotalSpace = disk_total_space(public_path());
                $diskFreeSpace = disk_free_space(public_path());
                $diskUsedSpace = $diskTotalSpace - $diskFreeSpace;

                $zipPath = public_path('generated/files.zip');
                
                if ($this->shouldUpdateZip($files, $zipPath)) {
                    $this->createZip($files, $zipPath);
                }

                return $this->successResponse([
                    'total' => $fileCount,
                    'total_size' => $this->humanFileSize($totalSize),
                    'used_percentage' => $usedPercentage,
                    'download_link' => url('generated/files.zip'),
                    'disk_info' => [
                        'total_disk_space' => $this->humanFileSize($diskTotalSpace),
                        'free_disk_space' => $this->humanFileSize($diskFreeSpace),
                        'used_disk_space' => $this->humanFileSize($diskUsedSpace),
                        'disk_usage_percentage' => round(($diskUsedSpace / $diskTotalSpace) * 100, 2)
                    ]
                ], 'Files listed successfully');
            } else {
                $diskTotalSpace = disk_total_space(public_path());
                $diskFreeSpace = disk_free_space(public_path());
                $diskUsedSpace = $diskTotalSpace - $diskFreeSpace;

                return $this->successResponse([
                    'total' => 0,
                    'total_size' => $this->humanFileSize(0),
                    'used_percentage' => 0,
                    'disk_info' => [
                        'total_disk_space' => $this->humanFileSize($diskTotalSpace),
                        'free_disk_space' => $this->humanFileSize($diskFreeSpace),
                        'used_disk_space' => $this->humanFileSize($diskUsedSpace),
                        'disk_usage_percentage' => round(($diskUsedSpace / $diskTotalSpace) * 100, 2)
                    ]
                ], 'Không có file nào trong thư mục.');
            }
        }

        return $this->errorResponse('Thư mục public/generated không tồn tại.', null, 404);
    }

    public function deleteAllFiles()
    {
        $generatedPath = public_path('generated');

        if (File::exists($generatedPath)) {
            $files = File::allFiles($generatedPath);

            foreach ($files as $file) {
                File::delete($file);
            }

            return $this->successResponse(null, 'Tất cả file đã được xóa.');
        }

        return $this->errorResponse('Thư mục public/generated không tồn tại.', null, 404);
    }

    public function deleteFileByName($fileName)
    {
        $generatedPath = public_path('generated/' . $fileName);

        if (File::exists($generatedPath)) {
            File::delete($generatedPath);

            return $this->successResponse(null, "File '$fileName' đã được xóa.");
        }

        return $this->notFoundResponse("File '$fileName' không tồn tại.");
    }

    private function shouldUpdateZip($files, $zipPath)
    {
        if (!File::exists($zipPath)) {
            return true;
        }

        $zipModTime = File::lastModified($zipPath);

        foreach ($files as $file) {
            if ($file->getMTime() > $zipModTime) {
                return true;
            }
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) === TRUE) {
            $zipFileCount = $zip->numFiles;
            $zip->close();
            
            $currentFileCount = count($files);
            foreach ($files as $file) {
                if ($file->getFilename() === 'files.zip') {
                    $currentFileCount--;
                    break;
                }
            }
            
            if ($zipFileCount !== $currentFileCount) {
                return true;
            }
        }

        return false;
    }

    private function humanFileSize($bytes, $decimals = 2)
    {
        $size = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $factor = floor((strlen($bytes) - 1) / 3);
        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . ' ' . $size[$factor];
    }

    private function createZip($files, $zipPath)
    {
        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            foreach ($files as $file) {
                if ($file->getFilename() !== 'files.zip') {
                    $zip->addFile($file->getRealPath(), $file->getRelativePathname());
                }
            }
            $zip->close();
        }
    }
}
