<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PhpOffice\PhpWord\TemplateProcessor;
use Validator;
use Carbon\Carbon;

class BankBillController extends Controller
{
    private $templatePath = [
        'btg_pactual' => 'btg_pactual_bank_bill_template.docx',
    ];

    public function __construct()
    {
        $this->templatePath = array_map(fn($template) => storage_path("app/private/$template"), $this->templatePath);
    }

    public function generateBankBillBTGPactualGenerate(Request $request)
    {
        $fileName = $this->templatePath['btg_pactual'];
        $dataArray = $request->all();

        // Nếu statementPeriod thiếu thì tự động gán ngẫu nhiên 2 tháng gần nhất TRƯỚC validate
        foreach ($dataArray as &$data) {
            if (empty($data['statementPeriod'])) {
                $now = Carbon::now();
                $months = [
                    $now->copy()->subMonths(1),
                    $now
                ];
                $chosenMonth = $months[rand(0, 1)];

                if ($chosenMonth->isSameMonth($now)) {
                    $startDay = rand(1, $now->day - 10 > 1 ? $now->day - 10 : 1);
                    $endDay = rand($startDay + 1, $now->day);
                } else {
                    $daysInMonth = $chosenMonth->daysInMonth;
                    $startDay = rand(1, $daysInMonth - 10 > 1 ? $daysInMonth - 10 : 1);
                    $endDay = rand($startDay + 1, $daysInMonth);
                }
                $start = Carbon::create($chosenMonth->year, $chosenMonth->month, $startDay);
                $end = Carbon::create($chosenMonth->year, $chosenMonth->month, $endDay);

                $data['statementPeriod'] = $start->format('d/M/Y') . ' to ' . $end->format('d/M/Y');
            }
        }
        unset($data);

        // Validate đầu vào như yêu cầu KHÔNG thay đổi
        $validator = Validator::make($dataArray, [
            '*.filename' => 'required|string',
            '*.fullname' => 'required|string',
            '*.addressOne' => 'required|string',
            '*.addressTwo' => 'required|string',
            '*.accountName' => 'string',
            '*.accountNumber' => 'required|string',
            '*.statementPeriod' => 'required|string',
        ], [
            '*.filename.required' => 'Tên file là bắt buộc',
            '*.fullname.required' => 'Họ và tên là bắt buộc',
            '*.addressOne.required' => 'Địa chỉ dòng một là bắt buộc',
            '*.addressTwo.required' => 'Địa chỉ dòng hai là bắt buộc',
            '*.accountNumber.required' => 'Số tài khoản là bắt buộc',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $outputFilesSuccess = [];
        $outputFilesFailures = [];
        $generatedFilePaths = [];

        foreach ($dataArray as $data) {
            // Tên accountName lấy từ fullname nếu chưa có
            $data['accountName'] = $data['accountName'] ?? $data['fullname'];

            // Xử lý số tài khoản: Loại bỏ "BR" và hiển thị 6 số đầu, 6 số cuối, giữa là "*****"
            $accountNumber = $data['accountNumber'];
            $accountNumberWithoutPrefix = preg_replace('/^BR/', '', $accountNumber);
            $formattedAccountNumber = substr($accountNumberWithoutPrefix, 0, 6) . "*****" . substr($accountNumberWithoutPrefix, -6);

            // Tính toán tháng và ngày đầu tháng cho phần "Balance on Jun 7"
            $statementPeriod = $data['statementPeriod'];
            $datesRange = explode(' to ', $statementPeriod);
            $startDate = Carbon::createFromFormat('d/M/Y', $datesRange[0]);
            $endDate = Carbon::createFromFormat('d/M/Y', $datesRange[1]);
            $month = $startDate->format('M');
            $day = $startDate->format('j');
            $daysInMonthFormatted = "$month $day";

            if (!file_exists($fileName)) {
                return response()->json(['error' => "Không tìm thấy file mẫu tại: $fileName"], 400);
            }

            try {
                $templateProcessor = new TemplateProcessor($fileName);
            } catch (\Exception $e) {
                $outputFilesFailures[] = [
                    'error' => 'Không thể tải mẫu: ' . $e->getMessage(),
                    'data' => $data
                ];
                continue;
            }

            // --- SET THÔNG TIN CHUNG ---
            $templateProcessor->setValue('fullname', mb_strtoupper($data['fullname']));
            $templateProcessor->setValue('addressOne', $data['addressOne']);
            $templateProcessor->setValue('addressTwo', $data['addressTwo']);
            $templateProcessor->setValue('accountName', mb_strtoupper($data['accountName']));
            $templateProcessor->setValue('accountNumber', $formattedAccountNumber);
            $templateProcessor->setValue('statementPeriod', $data['statementPeriod']);
            $templateProcessor->setValue('date', Carbon::now()->format('d/m/Y'));
            $templateProcessor->setValue('month', $daysInMonthFormatted);

            // --- TỰ ĐỘNG XỬ LÝ CÁC PLACEHOLDER CỦA ACCOUNT SUMMARY (totalOn, totalIn, totalOut, ebBal) ---
            $totalOn = rand(1000000, 5000000) / 100;    // VD: 27,584.38
            $totalIn = rand(300000, 1500000) / 100;
            $totalOut = rand(200000, 1400000) / 100;
            $ebBal = $totalOn + $totalIn - $totalOut;

            // Đặt dấu $ phía trước và sử dụng dấu chấm phân cách phần thập phân, dấu phẩy cho phần nghìn
            $templateProcessor->setValue('totalOn', '$' . number_format($totalOn, 2, '.', ','));
            $templateProcessor->setValue('totalIn', '$' . number_format($totalIn, 2, '.', ','));
            $templateProcessor->setValue('totalOut', '$' . number_format($totalOut, 2, '.', ','));
            $templateProcessor->setValue('balanceOn', '$' . number_format($ebBal, 2, '.', ','));
            $templateProcessor->setValue('ebBal', number_format($ebBal, 2, '.', ','));

            // --- XỬ LÝ DANH SÁCH NGÀY CHO CỘT DATE THEO ĐÚNG ĐỊNH DẠNG MM/DD ---
            $maxRows = 11;
            $allDays = [];
            $period = Carbon::parse($startDate)->daysUntil($endDate);
            foreach ($period as $dayObj) {
                $allDays[] = $dayObj->copy();
            }
            // Chọn ngẫu nhiên $maxRows ngày, không trùng, sort tăng dần
            if (count($allDays) > $maxRows) {
                $chosenKeys = array_rand($allDays, $maxRows);
                if (!is_array($chosenKeys))
                    $chosenKeys = [$chosenKeys];
                $chosenDays = [];
                foreach ($chosenKeys as $k)
                    $chosenDays[] = $allDays[$k];
                usort($chosenDays, fn($a, $b) => $a->timestamp - $b->timestamp);
            } else {
                $chosenDays = $allDays;
            }

            // --- TỰ ĐỘNG XỬ LÝ GIAO DỊCH BẢNG (PLACEHOLDER date1, withdra1, deposit1, balance1, ...)
            $transactions = [];
            $balance = $totalOn;
            for ($i = 0; $i < $maxRows; $i++) {
                $dateValue = isset($chosenDays[$i]) ? $chosenDays[$i]->format('m/d') : '';
                $type = rand(1, 3);
                $withdraw = '';
                $deposit = '';
                $desc = '';
                if ($type == 1) {
                    $withdraw = rand(1000, 2000000) / 100; // ví dụ 253.68
                    $balance -= $withdraw;
                    $desc = "Payment #" . rand(1000, 9999);
                } elseif ($type == 2) {
                    $deposit = rand(1000, 3000000) / 100;
                    $balance += $deposit;
                    $desc = "Deposit #" . rand(1000, 9999);
                } else {
                    $withdraw = rand(500, 1000000) / 100;
                    $deposit = rand(500, 2000000) / 100;
                    $desc = "Transfer #" . rand(100, 999);
                    $balance = $balance - $withdraw + $deposit;
                }
                $transactions[] = [
                    'date' => $dateValue,
                    'desc' => $desc,
                    'withdraw' => $withdraw !== '' ? number_format($withdraw, 2, '.', ',') : '',
                    'deposit' => $deposit !== '' ? number_format($deposit, 2, '.', ',') : '',
                    'balance' => number_format($balance, 2, '.', ',')
                ];
            }

            // Gán giá trị từng placeholder theo template (date1, withdra1, deposit1, balance1 ...)
            for ($i = 1; $i <= $maxRows; $i++) {
                $idx = $i - 1;
                $templateProcessor->setValue("date$i", $transactions[$idx]['date']);
                $templateProcessor->setValue("withdra$i", $transactions[$idx]['withdraw']);
                $templateProcessor->setValue("deposit$i", $transactions[$idx]['deposit']);
                $templateProcessor->setValue("balance" . ($i + 1), $transactions[$idx]['balance']);
            }
            // Số dư đầu kỳ là balance1, số dư cuối kỳ là ebBal (đã gán ở trên)
            $templateProcessor->setValue('balance1', number_format($totalOn, 2, '.', ','));

            // Với các placeholder không sử dụng thì gán rỗng
            for ($i = $maxRows + 1; $i <= 20; $i++) {
                $templateProcessor->setValue("date$i", '');
                $templateProcessor->setValue("withdra$i", '');
                $templateProcessor->setValue("deposit$i", '');
                $templateProcessor->setValue("balance$i", '');
            }

            // CheckNo và các check đặc biệt (nếu có), ví dụ minh họa: checkNo1, checkNo2
            $templateProcessor->setValue("checkNo1", rand(1000, 9999));
            $templateProcessor->setValue("checkNo2", rand(100, 999));

            $sanitizedFilename = str_replace('-', '_', $data['filename']);
            $outputFileName = "btg_pactual_business_$sanitizedFilename.docx";
            $outputFilePath = public_path("generated/$outputFileName");

            if (!file_exists(dirname($outputFilePath))) {
                mkdir(dirname($outputFilePath), 0755, true);
            }

            try {
                $templateProcessor->saveAs($outputFilePath);
                $outputFilesSuccess[] = [
                    'file' => $outputFileName,
                    'file_url' => url("generated/$outputFileName")
                ];
                $generatedFilePaths[] = $outputFilePath;
            } catch (\Exception $e) {
                $outputFilesFailures[] = [
                    'error' => 'Không thể lưu tài liệu đã tạo: ' . $e->getMessage(),
                    'data' => $data
                ];
            }
        }

        // --- Nén các file đã tạo thành file ZIP ---
        $zipFileName = 'bank_bills_' . time() . '.zip';
        $zipFilePath = public_path("generated/$zipFileName");
        $zip = new \ZipArchive();
        if ($zip->open($zipFilePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
            foreach ($generatedFilePaths as $filePath) {
                // Đưa vào ZIP với tên file gốc
                $zip->addFile($filePath, basename($filePath));
            }
            $zip->close();
            $zipUrl = url("generated/$zipFileName");
        } else {
            $zipUrl = null;
        }

        return response()->json([
            'message' => 'Các hóa đơn ngân hàng đã được tạo thành công.',
            'total' => count($outputFilesSuccess),
            'failures' => $outputFilesFailures,
            'zip_url' => $zipUrl,
            'data' => $outputFilesSuccess,
        ], 201);
    }
}