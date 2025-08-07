<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use File;

class FileCheckController extends Controller
{
    public function listFiles()
    {
        $generatedPath = public_path('generated');

        if (File::exists($generatedPath)) {
            $files = File::allFiles($generatedPath);

            $fileList = [];
            foreach ($files as $file) {
                $fileList[] = [
                    'name' => $file->getRelativePathname(),
                    'size' => $this->humanFileSize($file->getSize()),
                    'modified' => date('Y-m-d H:i:s', $file->getMTime()),
                ];
            }

            return response()->json([
                'total' => count($fileList),
                'files' => $fileList
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Thư mục public/generated không tồn tại.'
        ]);
    }

    public function deleteAllFiles()
    {
        $generatedPath = public_path('generated');

        if (File::exists($generatedPath)) {
            $files = File::allFiles($generatedPath);

            foreach ($files as $file) {
                File::delete($file);
            }

            return response()->json([
                'message' => 'Tất cả file đã được xóa.'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Thư mục public/generated không tồn tại.'
        ]);
    }

    public function deleteFileByName($fileName)
    {
        $generatedPath = public_path('generated/' . $fileName);

        if (File::exists($generatedPath)) {
            File::delete($generatedPath);

            return response()->json([
                'message' => "File '$fileName' đã được xóa."
            ]);
        }

        return response()->json([
            'message' => "File '$fileName' không tồn tại."
        ]);
    }

    private function humanFileSize($bytes, $decimals = 2)
    {
        $size = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $factor = floor((strlen($bytes) - 1) / 3);
        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . ' ' . $size[$factor];
    }
}
