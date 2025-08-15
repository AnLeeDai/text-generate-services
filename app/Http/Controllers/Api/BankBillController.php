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

    /* ==== Helpers ==== */
    private function moneyToStr(int $cents): string
    {
        return number_format($cents / 100, 2, '.', ',');
    }

    private function randCents(int $min, int $max): int
    {
        return mt_rand($min, $max);
    }

    /**
     * Tính toán số tiền hợp lý cho từng loại giao dịch
     */
    private function getRealisticAmount($transactionName, $type): int
    {
        switch ($transactionName) {
            case 'Internet Bill':
                // Hóa đơn internet: $25 - $150
                return $this->randCents(2500, 15000);

            case 'Electric Bill':
                // Hóa đơn điện: $80 - $350
                return $this->randCents(8000, 35000);

            case 'Rent Bill':
                // Tiền thuê nhà: $800 - $2,500
                return $this->randCents(80000, 250000);

            case 'Payroll Run':
                // Lương nhân viên: $1,500 - $5,000
                return $this->randCents(150000, 500000);

            case 'Debit Transaction':
                // Giao dịch thẻ ghi nợ: $20 - $500
                return $this->randCents(2000, 50000);

            case 'Check No. 4598':
            case 'Check No. 234':
                // Séc: $500 - $3,000
                return $this->randCents(50000, 300000);

            case 'Credit Card Processor':
                // Thu từ xử lý thẻ tín dụng: $2,000 - $8,000
                return $this->randCents(200000, 800000);

            case 'Deposit':
                // Tiền gửi chung: $300 - $2,000
                return $this->randCents(30000, 200000);

            default:
                // Fallback cho các giao dịch khác
                if ($type === 'withdrawal') {
                    return $this->randCents(7599, 400000); // $75.99 – $4,000.00
                } else {
                    return $this->randCents(45684, 600000); // $456.84 – $6,000.00
                }
        }
    }

    /**
     * Sinh danh sách giao dịch ngẫu nhiên trong khoảng kỳ sao kê (CHUẨN)
     * - Dùng integer cents để tính toán
     * - Ngày giao dịch RANDOM nhưng sau đó SẮP XẾP TĂNG DẦN
     * - Dòng đầu tiên luôn là ngày bắt đầu kỳ (offset 0)
     * - Dòng cuối cùng luôn là ngày kết thúc kỳ (offset cuối)
     */
    private function generateRandomTransactions($balanceOn, $startOfPeriod, $endOfPeriod): array
    {
        // 11 dòng đúng như template với tên giao dịch cụ thể
        $pattern = [
            ['type' => 'withdrawal', 'name' => 'Internet Bill'],           // 0
            ['type' => 'withdrawal', 'name' => 'Electric Bill'],           // 1
            ['type' => 'deposit', 'name' => 'Check No. 4598'],             // 2
            ['type' => 'deposit', 'name' => 'Credit Card Processor'],      // 3
            ['type' => 'withdrawal', 'name' => 'Payroll Run'],             // 4
            ['type' => 'withdrawal', 'name' => 'Debit Transaction'],       // 5
            ['type' => 'withdrawal', 'name' => 'Rent Bill'],               // 6
            ['type' => 'deposit', 'name' => 'Check No. 234'],              // 7
            ['type' => 'withdrawal', 'name' => 'Payroll Run'],             // 8
            ['type' => 'deposit', 'name' => 'Deposit'],                    // 9
            ['type' => 'withdrawal', 'name' => 'Debit Transaction'],       // 10
        ];

        $start = $startOfPeriod instanceof Carbon ? $startOfPeriod->copy() : Carbon::parse($startOfPeriod);
        $end = $endOfPeriod instanceof Carbon ? $endOfPeriod->copy() : Carbon::parse($endOfPeriod);
        $totalDays = $start->diffInDays($end); // KHÔNG cộng +1

        if ($totalDays < 10) {
            // Nếu số ngày trong kỳ < 11, lấy tất cả các ngày, lặp lại ngày đầu cho đủ 11
            $offsets = range(0, $totalDays);
            while (count($offsets) < 11) {
                $offsets[] = 0;
            }
            $offsets = array_slice($offsets, 0, 11);
        } else {
            // Luôn lấy ngày đầu (0) và ngày cuối ($totalDays - 1)
            $offsets = [0, $totalDays - 1];
            // Random 9 ngày KHÔNG trùng đầu/cuối, nằm trong khoảng 1..($totalDays-2)
            $pool = range(1, $totalDays - 2);
            shuffle($pool);
            $randomOffsets = array_slice($pool, 0, 9);
            $offsets = array_merge($offsets, $randomOffsets);
            $offsets = array_unique($offsets);
            $offsets = array_slice($offsets, 0, 11); // Đảm bảo đúng 11 phần tử
            sort($offsets);
        }

        $balCents = (int) round(((float) $balanceOn) * 100);
        $balances = [$balCents];     // balance1 (Previous balance)
        $txs = [];
        $inCents = 0;
        $outCents = 0;

        $internetBillValue = null;
        $electricBillValue = null;
        foreach ($pattern as $i => $item) {
            $dateStr = (clone $start)->addDays($offsets[$i])->format('m/d');
            $w = 0;
            $d = 0;

            if ($item['type'] === 'withdrawal') {
                if ($item['name'] === 'Internet Bill') {
                    // Sinh Internet Bill trước, đảm bảo trên $50 (5000 cents)
                    do {
                        $internetBillValue = $this->getRealisticAmount('Internet Bill', 'withdrawal');
                    } while ($internetBillValue < 5000);
                    $w = $internetBillValue;
                } elseif ($item['name'] === 'Electric Bill') {
                    // Electric Bill không được trùng Internet Bill, và cũng trên $50
                    do {
                        $electricBillValue = $this->getRealisticAmount('Electric Bill', 'withdrawal');
                    } while ($electricBillValue === $internetBillValue || $electricBillValue < 5000);
                    $w = $electricBillValue;
                } else {
                    $w = $this->getRealisticAmount($item['name'], 'withdrawal');
                }
                $balCents -= $w;
                $outCents += $w;
            } else {
                $d = $this->getRealisticAmount($item['name'], 'deposit');
                $balCents += $d;
                $inCents += $d;
            }

            $txs[] = [
                'date' => $dateStr,
                'withdrawal' => $w,        // int cents
                'deposit' => $d,        // int cents
                'balance' => $balCents, // int cents
            ];
            $balances[] = $balCents;       // balance2..balance12
        }

        // Ràng buộc phương trình sổ cái
        $expected = $balances[0] + $inCents - $outCents;
        if ($expected !== $balCents) {
            $balCents = $expected;
            $txs[count($txs) - 1]['balance'] = $balCents;
            $balances[count($balances) - 1] = $balCents;
        }

        return [
            'transactions' => $txs,
            'balanceArr' => $balances,
            'totalIn' => $inCents,
            'totalOut' => $outCents,
            'endingBalance' => $balCents,
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
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $outputFilesSuccess = [];
        $outputFilesFailures = [];
        $generatedFilePaths = [];

        foreach ($dataArray as $data) {
            $data['accountName'] = $data['fullname'];
            $accountNumber = $data['accountNumber'];
            $accountNumberDigits = preg_replace('/\D/', '', $accountNumber);
            $formattedAccountNumber = substr($accountNumberDigits, 0, 6) . "*****" . substr($accountNumberDigits, -6);

            $balanceOn = (float) $data['totalOn'];

            // Kỳ sao kê: từ đầu 2 tháng trước tới hết tháng trước
            $startOfPeriod = Carbon::now()->subMonths(2)->startOfMonth();
            $endOfPeriod = Carbon::now()->subMonth()->endOfMonth();
            $statementPeriod = $startOfPeriod->format('d/M/Y') . ' to ' . $endOfPeriod->format('d/M/Y');
            $startOfPeriodFormatted = $startOfPeriod->format('M d');
            $endOfPeriodFormatted = $endOfPeriod->format('M d');

            // Sinh giao dịch
            $transactionData = $this->generateRandomTransactions($balanceOn, $startOfPeriod, $endOfPeriod);
            $transactions = $transactionData['transactions'];
            $balanceArr = $transactionData['balanceArr'];
            $totalInCents = $transactionData['totalIn'];
            $totalOutCents = $transactionData['totalOut'];
            $endingCents = $transactionData['endingBalance'];

            // mapping theo đúng template: 7 withdrawal, 4 deposit
            $withdrawIndex = [0, 1, 4, 5, 6, 8, 10];
            $depositIndex = [2, 3, 7, 9];

            if (!file_exists($fileName)) {
                return $this->notFoundResponse("Không tìm thấy file mẫu tại: $fileName");
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

            // Thông tin chung
            $templateProcessor->setValue('fullname', mb_strtoupper($data['fullname']));
            $templateProcessor->setValue('addressOne', $data['addressOne']);
            $templateProcessor->setValue('addressTwo', $data['addressTwo']);
            $templateProcessor->setValue('accountName', mb_strtoupper($data['accountName']));
            $templateProcessor->setValue('accountNumber', $formattedAccountNumber);
            $templateProcessor->setValue('statementPeriod', $statementPeriod);
            $templateProcessor->setValue('date', Carbon::now()->format('d/m/Y'));
            $templateProcessor->setValue('month_start', $startOfPeriodFormatted);
            $templateProcessor->setValue('month_end', $endOfPeriodFormatted);

            // SUMMARY (template có R${...} sẵn)
            $templateProcessor->setValue('totalOn', '$' . $this->moneyToStr($balanceArr[0]));
            $templateProcessor->setValue('totalIn', '$' . $this->moneyToStr($totalInCents));
            $templateProcessor->setValue('totalOut', '$' . $this->moneyToStr($totalOutCents));
            $templateProcessor->setValue('balanceOn', '$' . $this->moneyToStr($endingCents));
            // ebBal ở cuối không có 'R$' => chỉ số
            $templateProcessor->setValue('ebBal', $this->moneyToStr($endingCents));

            // DATE: date1..date11 (đã tăng dần theo logic ở trên)
            for ($i = 1; $i <= 11; $i++) {
                $templateProcessor->setValue("date{$i}", $transactions[$i - 1]['date']);
            }

            // WITHDRAWAL: withdra1..withdra7
            for ($i = 1; $i <= 7; $i++) {
                $idx = $withdrawIndex[$i - 1];
                $val = $transactions[$idx]['withdrawal'] ?? 0;
                $templateProcessor->setValue("withdra{$i}", $val > 0 ? $this->moneyToStr($val) : '');
            }

            // DEPOSIT: deposit1..deposit4
            for ($i = 1; $i <= 4; $i++) {
                $idx = $depositIndex[$i - 1];
                $val = $transactions[$idx]['deposit'] ?? 0;
                $templateProcessor->setValue("deposit{$i}", $val > 0 ? $this->moneyToStr($val) : '');
            }

            // BALANCE: balance1..balance12
            for ($i = 1; $i <= 12; $i++) {
                $templateProcessor->setValue("balance{$i}", $this->moneyToStr($balanceArr[$i - 1]));
            }

            $sanitizedFilename = str_replace('-', '_', $data['filename']);
            $outputFileName = "btg_pactual_business_{$sanitizedFilename}.docx";
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

        // ZIP nếu có nhiều file
        $zipFileName = null;
        $zipFileUrl = null;
        if (count($generatedFilePaths) > 0) {
            $zipFileName = 'generated/btg_pactual_bills_' . date('Ymd_His') . '.zip';
            $zipFilePath = public_path($zipFileName);

            $zip = new \ZipArchive();
            if ($zip->open($zipFilePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
                foreach ($generatedFilePaths as $filePath) {
                    if (file_exists($filePath)) {
                        $zip->addFile($filePath, basename($filePath));
                    }
                }
                $zip->close();
                $zipFileUrl = url($zipFileName);
            }
        }

        return $this->createdResponse([
            'total' => count($outputFilesSuccess),
            'failures' => $outputFilesFailures,
            'zip_download_url' => $zipFileUrl,
            'files' => $outputFilesSuccess
        ], 'Các hóa đơn ngân hàng đã được tạo thành công.');
    }
}
