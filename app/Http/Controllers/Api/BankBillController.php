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

    /**
     * Sinh danh sách giao dịch ngẫu nhiên trong khoảng kỳ sao kê
     * 
     * @param float $balanceOn
     * @param Carbon|string $startOfPeriod
     * @param Carbon|string $endOfPeriod
     * @return array
     */
    private function generateRandomTransactions($balanceOn, $startOfPeriod, $endOfPeriod)
    {
        // Danh sách mô tả giao dịch đúng như mẫu (11 dòng)
        $descriptions = [
            ['type' => 'withdrawal'], // Internet Bill
            ['type' => 'withdrawal'], // Electric Bill
            ['type' => 'deposit'],    // Check No. 4598
            ['type' => 'deposit'],    // Deposit from Credit Card Processor
            ['type' => 'withdrawal'], // Payroll Run
            ['type' => 'withdrawal'], // Debit Transaction
            ['type' => 'withdrawal'], // Rent Bill
            ['type' => 'deposit'],    // Check No. 234
            ['type' => 'withdrawal'], // Payroll Run
            ['type' => 'deposit'],    // Deposit
            ['type' => 'withdrawal'], // Debit Transaction
        ];

        // Chuyển về đối tượng Carbon nếu truyền vào là string
        $startDate = $startOfPeriod instanceof Carbon ? $startOfPeriod->copy() : Carbon::parse($startOfPeriod);
        $endDate = $endOfPeriod instanceof Carbon ? $endOfPeriod->copy() : Carbon::parse($endOfPeriod);

        $totalDays = $startDate->diffInDays($endDate);

        $transactions = [];
        $balanceArr = [];
        $balance = floatval($balanceOn);
        $totalIn = 0;
        $totalOut = 0;

        // Giao dịch đầu tiên là balance trước giao dịch
        $balanceArr[] = $balance;

        // Sinh ngày tăng dần, nhiều nhất 11 dòng và không trùng nhau
        $dates = [];
        $usedDays = [];

        // Ép ngày đầu tiên luôn là ngày bắt đầu kỳ sao kê
        $dates[] = $startDate->format('m/d');
        $usedDays[] = 0; // offset 0 tương ứng ngày bắt đầu

        // Sinh thêm 10 ngày còn lại (đảm bảo không trùng)
        $need = 10;
        while ($need > 0) {
            $randDay = rand(0, $totalDays);
            if (in_array($randDay, $usedDays)) {
                continue;
            }
            $usedDays[] = $randDay;
            $dateObj = (clone $startDate)->addDays($randDay);
            $dates[] = $dateObj->format('m/d');
            $need--;
        }
        sort($dates);

        for ($i = 0; $i < 11; $i++) {
            $desc = $descriptions[$i];
            $date = $dates[$i];
            $withdrawal = null;
            $deposit = null;

            if ($desc['type'] == 'withdrawal') {
                $withdrawal = round(rand(7000, 400000) / 100, 2);
                $balance -= $withdrawal;
                $totalOut += $withdrawal;
            } else {
                $deposit = round(rand(10000, 600000) / 100, 2);
                $balance += $deposit;
                $totalIn += $deposit;
            }
            $balanceArr[] = $balance;

            $transactions[] = [
                'date' => $date,
                'withdrawal' => $withdrawal,
                'deposit' => $deposit,
                'balance' => $balance
            ];
        }

        return [
            'transactions' => $transactions,
            'balanceArr' => $balanceArr,
            'totalIn' => $totalIn,
            'totalOut' => $totalOut,
            'endingBalance' => $balance
        ];
    }

    /**
     * API generate bank bill BTG Pactual
     */
    public function generateBankBillBTGPactualGenerate(Request $request)
    {
        $fileName = $this->templatePath['btg_pactual'];
        $dataArray = $request->all();

        $validator = Validator::make($dataArray, [
            '*.filename' => 'required|string',
            '*.fullname' => 'required|string',
            '*.addressOne' => 'required|string',
            '*.addressTwo' => 'required|string',
            '*.accountName' => 'string',
            '*.accountNumber' => 'required|string',
            '*.totalOn' => 'required|numeric'
        ], [
            '*.filename.required' => 'Tên file là bắt buộc',
            '*.fullname.required' => 'Họ và tên là bắt buộc',
            '*.addressOne.required' => 'Địa chỉ dòng một là bắt buộc',
            '*.addressTwo.required' => 'Địa chỉ dòng hai là bắt buộc',
            '*.accountNumber.required' => 'Số tài khoản là bắt buộc',
            '*.totalOn.required' => 'Số dư đầu kỳ là bắt buộc'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $outputFilesSuccess = [];
        $outputFilesFailures = [];
        $generatedFilePaths = [];

        foreach ($dataArray as $data) {
            $data['accountName'] = $data['fullname'];
            $accountNumber = $data['accountNumber'];
            $accountNumberWithoutPrefix = substr($accountNumber, 2);
            $formattedAccountNumber = substr($accountNumberWithoutPrefix, 0, 6) . "*****" . substr($accountNumberWithoutPrefix, -6);

            $balanceOn = floatval($data['totalOn']);

            // Xác định kỳ sao kê: từ đầu 2 tháng trước tới hết tháng trước
            $startOfPeriod = Carbon::now()->subMonths(2)->startOfMonth();
            $endOfPeriod = Carbon::now()->subMonth()->endOfMonth();
            $statementPeriod = $startOfPeriod->format('d/M/Y') . ' to ' . $endOfPeriod->format('d/M/Y');
            $daysInMonthFormatted = $startOfPeriod->format('M j');

            // Sinh giao dịch nằm trong khoảng kỳ sao kê
            $transactionData = $this->generateRandomTransactions($balanceOn, $startOfPeriod, $endOfPeriod);
            $transactions = $transactionData['transactions'];
            $balanceArr = $transactionData['balanceArr'];
            $totalIn = $transactionData['totalIn'];
            $totalOut = $transactionData['totalOut'];
            $endingBalance = $transactionData['endingBalance'];

            // mapping index cho withdrawal và deposit theo mẫu
            $withdrawIndex = [0, 1, 4, 5, 6, 8, 10];
            $depositIndex = [2, 3, 7, 9];

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

            // Set các trường chung
            $templateProcessor->setValue('fullname', mb_strtoupper($data['fullname']));
            $templateProcessor->setValue('addressOne', $data['addressOne']);
            $templateProcessor->setValue('addressTwo', $data['addressTwo']);
            $templateProcessor->setValue('accountName', mb_strtoupper($data['accountName']));
            $templateProcessor->setValue('accountNumber', $formattedAccountNumber);
            $templateProcessor->setValue('statementPeriod', $statementPeriod);
            $templateProcessor->setValue('date', Carbon::now()->format('d/m/Y'));
            $templateProcessor->setValue('month', $daysInMonthFormatted);

            // Set tổng kết
            $templateProcessor->setValue('totalOn', '$' . number_format($balanceArr[0], 2, '.', ','));
            $templateProcessor->setValue('totalIn', '$' . number_format($totalIn, 2, '.', ','));
            $templateProcessor->setValue('totalOut', '$' . number_format($totalOut, 2, '.', ','));
            $templateProcessor->setValue('balanceOn', '$' . number_format($endingBalance, 2, '.', ','));
            $templateProcessor->setValue('ebBal', number_format($endingBalance, 2, '.', ','));

            // Map DATE
            for ($i = 1; $i <= 11; $i++) {
                $tx = $transactions[$i - 1];
                $templateProcessor->setValue("date$i", $tx['date']);
            }

            // Map WITHDRAWAL
            for ($i = 1; $i <= 7; $i++) {
                $idx = $withdrawIndex[$i - 1];
                $templateProcessor->setValue(
                    "withdra$i",
                    (isset($transactions[$idx]) && $transactions[$idx]['withdrawal'])
                    ? number_format($transactions[$idx]['withdrawal'], 2, '.', ',')
                    : ''
                );
            }

            // Map DEPOSIT
            for ($i = 1; $i <= 4; $i++) {
                $idx = $depositIndex[$i - 1];
                $templateProcessor->setValue(
                    "deposit$i",
                    (isset($transactions[$idx]) && $transactions[$idx]['deposit'])
                    ? number_format($transactions[$idx]['deposit'], 2, '.', ',')
                    : ''
                );
            }

            // Map BALANCE
            for ($i = 1; $i <= 12; $i++) {
                $templateProcessor->setValue(
                    "balance$i",
                    isset($balanceArr[$i - 1])
                    ? number_format($balanceArr[$i - 1], 2, '.', ',')
                    : ''
                );
            }

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

        $zipFileName = null;
        $zipFileUrl = null;
        if (count($generatedFilePaths) > 0) {
            $zipFileName = 'generated/btg_pactual_bills_' . date('Ymd_His') . '.zip';
            $zipFilePath = public_path($zipFileName);

            $zip = new \ZipArchive();
            if ($zip->open($zipFilePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
                foreach ($generatedFilePaths as $filePath) {
                    if (file_exists($filePath)) {
                        $localName = basename($filePath);
                        $zip->addFile($filePath, $localName);
                    }
                }
                $zip->close();
                $zipFileUrl = url($zipFileName);
            }
        }

        return response()->json([
            'message' => 'Các hóa đơn ngân hàng đã được tạo thành công.',
            'total' => count($outputFilesSuccess),
            'failures' => $outputFilesFailures,
            'zip_download_url' => $zipFileUrl,
            'data' => $outputFilesSuccess
        ], 201);
    }
}