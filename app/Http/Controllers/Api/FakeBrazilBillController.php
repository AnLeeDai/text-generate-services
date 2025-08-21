<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Carbon;

class FakeBrazilBillController
{
    public function generate(Request $request)
    {
        // ===== 1) Chuẩn bị input & validate theo mảng =====
        $templatePath = $request->query('full_path');
        if (!$templatePath) {
            $name = $request->query('template', 'brazil_bill.xlsx');
            $templatePath = storage_path("app/private/$name");
        }
        if (!is_file($templatePath)) {
            return response()->json([
                'ok' => false,
                'message' => "Không tìm thấy file template.",
                'path' => $templatePath
            ], 404);
        }

        $dataArray = $request->all();
        if (!is_array($dataArray) || (isset($dataArray[0]) && !is_array($dataArray))) {
            $dataArray = [$dataArray];
        }

        foreach ($dataArray as &$row) {
            if (isset($row['fileName']) && empty($row['filename'])) {
                $row['filename'] = $row['fileName'];
            }
        }
        unset($row);

        $validator = Validator::make($dataArray, [
            '*.filename' => 'required|string',
            '*.fullName' => 'required|string',
            '*.addressOne' => 'required|string',
            '*.addressTwo' => 'required|string',
            '*.accountNum' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'message' => 'Dữ liệu không hợp lệ.',
                'errors' => $validator->errors()
            ], 422);
        }

        // ===== 2) Sinh file cho từng record =====
        $outputFilesSuccess = [];
        $outputFilesFailures = [];
        $generatedFilePaths = [];

        foreach ($dataArray as $data) {
            try {
                $spreadsheet = IOFactory::load($templatePath);
            } catch (\Throwable $e) {
                $outputFilesFailures[] = [
                    'error' => 'Không thể tải template: ' . $e->getMessage(),
                    'data' => $data,
                ];
                continue;
            }

            // Lấy sheet
            $sheet = null;
            if (!empty($data['sheet'])) {
                $sheet = $spreadsheet->getSheetByName($data['sheet']);
            } else {
                $idx = isset($data['sheet_index']) ? (int) $data['sheet_index'] : 0;
                $sheet = $spreadsheet->getSheet($idx);
            }
            if (!$sheet) {
                $spreadsheet->disconnectWorksheets();
                $outputFilesFailures[] = [
                    'error' => 'Không tìm thấy sheet cần ghi.',
                    'data' => $data,
                ];
                continue;
            }

            // Gán dữ liệu
            $fullName = mb_strtoupper($data['fullName'], 'UTF-8');
            $addressOne = $data['addressOne'];
            $addressTwo = $data['addressTwo'];
            $accountNum = $data['accountNum'];

            // A8/A9/A10
            $this->writeText($sheet, 'A8', $fullName);
            $this->writeText($sheet, 'A9', $this->addressOneWithComma($addressOne)); // luôn kết thúc bằng ", "
            $this->writeText($sheet, 'A10', $addressTwo);

            // I8: kỳ sao kê ngẫu nhiên trong THÁNG TRƯỚC (dd.mm.Y - dd.mm.Y)
            $period = $this->randomStatementPeriod();
            $this->writeText($sheet, 'I8', $period);

            // Parse khoảng từ I8
            [$periodStart, $periodEnd] = $this->periodToRange($period);

            // A24 = start, A43 = end
            $this->writeText($sheet, 'A24', $periodStart->format('d.m.Y')); // start (đầu kỳ)
            $this->writeText($sheet, 'A43', $periodEnd->format('d.m.Y'));   // end (cuối kỳ)

            // Các ô giữa: random nhưng SẮP TĂNG DẦN
            $middleCoords = ['A28', 'A33', 'A34', 'A36', 'A37', 'A38', 'A40', 'A41'];
            $middleDates = $this->randomSortedInteriorDates($periodStart, $periodEnd, count($middleCoords));
            foreach ($middleCoords as $i => $coord) {
                $this->writeText($sheet, $coord, $middleDates[$i]->format('d.m.Y'));
            }

            // I10: mask tài khoản (8 số đầu + **** + 8 số cuối)
            $masked = $this->maskAccount($accountNum);
            $this->writeText($sheet, 'I10', $masked);

            // Các ô tiền random theo khoảng
            $moneyCells = [
                'I24' => [300, 800],
                'I33' => [200, 300],
                'I34' => [30, 50],
                'I36' => [300, 600],
                'I37' => [200, 550],
                'I38' => [5, 10],
                'I40' => [50, 185],
                'I41' => [2, 5],
                'I43' => [30, 67],
            ];
            foreach ($moneyCells as $coord => [$min, $max]) {
                $this->writeNumber($sheet, $coord, $this->randMoney($min, $max));
            }

            // Lưu từng file
            $safe = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $data['filename']);
            $outputName = "brazil_bill_{$safe}.xlsx";
            $outputPath = public_path("generated/$outputName");
            if (!is_dir(dirname($outputPath))) {
                @mkdir(dirname($outputPath), 0755, true);
            }

            try {
                IOFactory::createWriter($spreadsheet, 'Xlsx')->save($outputPath);
                $generatedFilePaths[] = $outputPath;
                $outputFilesSuccess[] = [
                    'file' => $outputName,
                    'file_url' => url("generated/$outputName"),
                ];
            } catch (\Throwable $e) {
                $outputFilesFailures[] = [
                    'error' => 'Không thể lưu file: ' . $e->getMessage(),
                    'data' => $data,
                ];
            } finally {
                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet);
            }
        }

        // ===== 3) Đóng gói ZIP (nếu có file) =====
        $zipDownloadUrl = null;
        if (count($generatedFilePaths) > 0) {
            $zipName = 'generated/brazil_bills_' . date('Ymd_His') . '.zip';
            $zipPath = public_path($zipName);
            if (!is_dir(dirname($zipPath))) {
                @mkdir(dirname($zipPath), 0755, true);
            }

            $zip = new \ZipArchive();
            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE)) {
                foreach ($generatedFilePaths as $filePath) {
                    $zip->addFile($filePath, basename($filePath));
                }
                $zip->close();
                $zipDownloadUrl = url($zipName);
            }
        }

        // ===== 4) Trả kết quả =====
        return response()->json([
            'ok' => true,
            'message' => 'Các hóa đơn Brazil Bill đã được tạo.',
            'total' => count($outputFilesSuccess),
            'failures' => $outputFilesFailures,
            'zip_download_url' => $zipDownloadUrl,
            'files' => $outputFilesSuccess,
        ], 201);
    }

    public function getCells(Request $request)
    {
        // 1) Xác định đường dẫn file
        $file = $request->query('full_path');
        if (!$file) {
            $name = $request->query('template', 'brazil_bill.xlsx');
            $file = storage_path("app/private/$name");
        }
        if (!is_file($file)) {
            return response()->json(['ok' => false, 'message' => 'Không tìm thấy file.', 'path' => $file], 404);
        }

        // 2) Load workbook
        try {
            $spreadsheet = IOFactory::load($file);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'Không đọc được file: ' . $e->getMessage()], 422);
        }

        // 3) Lấy sheet
        $sheetName = $request->query('sheet');
        $sheetIndex = (int) $request->query('sheet_index', 0);
        $sheet = $sheetName
            ? $spreadsheet->getSheetByName($sheetName)
            : $spreadsheet->getSheet($sheetIndex);

        if (!$sheet) {
            $spreadsheet->disconnectWorksheets();
            return response()->json(['ok' => false, 'message' => 'Không tìm thấy sheet.'], 404);
        }

        // 4) Danh sách ô cần đọc
        $cellsParam = $request->query(
            'cells',
            ['A8', 'A9', 'A10', 'A24', 'A28', 'A33', 'A34', 'A36', 'A37', 'A38', 'A40', 'A41', 'A43', 'I8', 'I10', 'I24', 'I33', 'I34', 'I36', 'I37', 'I38', 'I40', 'I41', 'I43']
        );
        $cells = (is_string($cellsParam)) ? array_filter(array_map('trim', explode(',', $cellsParam))) : (array) $cellsParam;

        // 5) Đọc từng ô (xử lý merged + RichText) -> CHỈ LẤY RAW
        $result = [];
        foreach ($cells as $addr) {
            $coord = strtoupper(trim($addr));
            if ($coord === '')
                continue;

            foreach ($sheet->getMergeCells() as $range) {
                if ($this->coordInRange($coord, $range)) {
                    $coord = $this->topLeftOfRange($range);
                    break;
                }
            }

            $cell = $sheet->getCell($coord);

            $raw = $cell->getValue();
            if ($raw instanceof RichText) {
                $raw = $raw->getPlainText();
            }

            $result[$addr] = $raw;
        }

        // 6) Thu dọn
        $sheetTitle = $sheet->getTitle();
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return response()->json([
            'ok' => true,
            'file' => $file,
            'sheet' => $sheetTitle,
            'data' => $result,
        ]);
    }

    // ===== Helpers =====

    private function coordInRange(string $coord, string $range): bool
    {
        [$start, $end] = Coordinate::rangeBoundaries($range);
        [$colStr, $row] = Coordinate::coordinateFromString($coord);
        $col = Coordinate::columnIndexFromString($colStr);

        return $col >= $start[0] && $col <= $end[0]
            && $row >= $start[1] && $row <= $end[1];
    }

    private function topLeftOfRange(string $range): string
    {
        if (strpos($range, ':') === false)
            return $range;
        [$start] = explode(':', $range, 2);
        return $start;
    }

    private function ensureWritableCoord($sheet, string $coord): string
    {
        foreach ($sheet->getMergeCells() as $range) {
            if ($this->coordInRange($coord, $range)) {
                return $this->topLeftOfRange($range);
            }
        }
        return $coord;
    }

    private function writeText($sheet, string $coord, string $text): void
    {
        $coord = $this->ensureWritableCoord($sheet, strtoupper($coord));
        $sheet->setCellValueExplicit($coord, $text, DataType::TYPE_STRING);
    }

    private function writeNumber($sheet, string $coord, float $value): void
    {
        $coord = $this->ensureWritableCoord($sheet, strtoupper($coord));
        $sheet->setCellValue($coord, $value);
        $sheet->getStyle($coord)->getNumberFormat()->setFormatCode('#,##0.00');
    }

    private function randMoney(float $min, float $max): float
    {
        return round($min + mt_rand() / mt_getrandmax() * ($max - $min), 2);
    }

    private function maskAccount(string $input): string
    {
        // Lấy chuỗi chỉ gồm chữ số
        $digits = preg_replace('/\D+/', '', $input ?? '');
        if ($digits === '') {
            return '****';
        }

        // Nếu chuỗi gốc kết thúc bằng chữ cái + 1 chữ số (vd: "...B8")
        // thì bỏ 1 chữ số cuối (coi như check digit)
        if (preg_match('/[A-Za-z]\d$/', $input) && strlen($digits) > 1) {
            $digits = substr($digits, 0, -1);
        }

        // Lấy 8 số đầu và 8 số cuối (nếu không đủ thì lấy phần có sẵn)
        $first = substr($digits, 0, 8);
        $last = (strlen($digits) > 8) ? substr($digits, -8) : '';

        return "$first****$last";
    }

    private function randomStatementPeriod(): string
    {
        $anchor = now()->subMonthNoOverflow();
        $startOfMonth = $anchor->copy()->startOfMonth();
        $endOfMonth = $anchor->copy()->endOfMonth();

        $lastDay = (int) $endOfMonth->day;
        $startDay = random_int(1, min(5, max(1, $lastDay - 25)));
        $minEnd = min($lastDay, max($startDay + 10, 20));
        $endDay = random_int($minEnd, $lastDay);

        $start = $startOfMonth->copy()->day($startDay);
        $end = $startOfMonth->copy()->day($endDay);

        return $start->format('d.m.Y') . ' - ' . $end->format('d.m.Y');
    }

    private function periodToRange(string $period): array
    {
        $parts = explode('-', $period, 2);
        $startStr = isset($parts[0]) ? trim($parts[0]) : null;
        $endStr = isset($parts[1]) ? trim($parts[1]) : null;

        try {
            $start = Carbon::createFromFormat('d.m.Y', $startStr)->startOfDay();
            $end = Carbon::createFromFormat('d.m.Y', $endStr)->endOfDay();
        } catch (\Throwable $e) {
            $anchor = now()->subMonthNoOverflow();
            $start = $anchor->copy()->startOfMonth();
            $end = $anchor->copy()->endOfMonth();
        }

        if ($end->lessThan($start)) {
            [$start, $end] = [$end, $start];
        }
        return [$start, $end];
    }

    /**
     * Sinh N ngày nội bộ (loại trừ biên) NGẪU NHIÊN và SẮP XẾP TĂNG DẦN.
     * - Nếu số ngày nội bộ >= N => tất cả DISTINCT và STRICTLY ASC.
     * - Nếu không đủ => lấy hết ngày nội bộ rồi PAD bằng ngày cuối (non-decreasing).
     */
    private function randomSortedInteriorDates(Carbon $start, Carbon $end, int $n): array
    {
        $n = max(0, $n);
        if ($n === 0)
            return [];

        $startDay = $start->copy()->startOfDay();
        $endDay = $end->copy()->startOfDay();
        $daysSpan = $startDay->diffInDays($endDay);
        $maxInterior = max(0, $daysSpan - 1); // offset hợp lệ: 1..daysSpan-1

        if ($maxInterior <= 0) {
            // Không có ngày nội bộ -> toàn bộ là start (vẫn không giảm)
            return array_fill(0, $n, $startDay->copy());
        }

        if ($maxInterior >= $n) {
            // đủ ngày để chọn DISTINCT
            $chosen = [];
            while (count($chosen) < $n) {
                $o = random_int(1, $maxInterior);
                $chosen[$o] = true; // set để không trùng
            }
            $offsets = array_keys($chosen);
            sort($offsets); // TĂNG DẦN
        } else {
            // không đủ -> lấy toàn bộ 1..maxInterior rồi pad bằng maxInterior (non-decreasing)
            $offsets = range(1, $maxInterior);
            while (count($offsets) < $n) {
                $offsets[] = $maxInterior;
            }
        }

        $dates = [];
        foreach ($offsets as $o) {
            $dates[] = $startDay->copy()->addDays($o);
        }
        return $dates;
    }

    /**
     * Chuẩn hóa addressOne để luôn kết thúc bằng ", "
     */
    private function addressOneWithComma(?string $s): string
    {
        $s = (string) $s;
        $s = rtrim($s);
        if ($s === '')
            return '';
        // bỏ dấu phẩy/khoảng trắng dư ở cuối, rồi gắn đúng ", "
        $s = rtrim($s, ", \t\n\r\0\x0B");
        return $s . ', ';
    }
}
