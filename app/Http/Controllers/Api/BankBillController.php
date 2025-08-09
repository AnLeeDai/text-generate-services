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
     * Sinh danh sách giao dịch ngẫu nhiên trong khoảng kỳ sao kê (CHUẨN)
     * - Dùng integer cents để tính toán
     * - Ngày giao dịch RANDOM nhưng sau đó SẮP XẾP TĂNG DẦN
     * - Dòng đầu tiên luôn là ngày bắt đầu kỳ (offset 0)
     */
    private function generateRandomTransactions($balanceOn, $startOfPeriod, $endOfPeriod): array
    {
        // 11 dòng đúng như template
        $pattern = [
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

        $start = $startOfPeriod instanceof Carbon ? $startOfPeriod->copy() : Carbon::parse($startOfPeriod);
        $end = $endOfPeriod instanceof Carbon ? $endOfPeriod->copy() : Carbon::parse($endOfPeriod);
        $totalDays = $start->diffInDays($end);

        // Ép có offset 0 (ngày đầu kỳ), lấy ngẫu nhiên thêm 10 offset khác, rồi SORT tăng dần
        $offsets = [0];
        if ($totalDays > 0) {
            $pool = range(1, $totalDays);
            shuffle($pool);
            $offsets = array_merge($offsets, array_slice($pool, 0, 10));
            $offsets = array_values(array_unique($offsets));
            // đảm bảo đủ 11 phần tử
            while (count($offsets) < 11) {
                $try = mt_rand(1, $totalDays);
                if (!in_array($try, $offsets, true))
                    $offsets[] = $try;
            }
        } else {
            // trường hợp kỳ chỉ 1 ngày (hiếm), lặp lại 0 cho đủ 11 (mọi giao dịch cùng ngày)
            while (count($offsets) < 11)
                $offsets[] = 0;
        }
        sort($offsets); // sắp xếp tăng dần theo yêu cầu

        $balCents = (int) round(((float) $balanceOn) * 100);
        $balances = [$balCents];     // balance1 (Previous balance)
        $txs = [];
        $inCents = 0;
        $outCents = 0;

        foreach ($pattern as $i => $item) {
            $dateStr = (clone $start)->addDays($offsets[$i])->format('m/d');
            $w = 0;
            $d = 0;

            if ($item['type'] === 'withdrawal') {
                $w = $this->randCents(7599, 400000); // 75.99 – 4,000.00
                $balCents -= $w;
                $outCents += $w;
            } else {
                $d = $this->randCents(45684, 600000); // 456.84 – 6,000.00
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

            $balanceOn = (float) $data['totalOn'];

            // Kỳ sao kê: từ đầu 2 tháng trước tới hết tháng trước
            $startOfPeriod = Carbon::now()->subMonths(2)->startOfMonth();
            $endOfPeriod = Carbon::now()->subMonth()->endOfMonth();
            $statementPeriod = $startOfPeriod->format('d/M/Y') . ' to ' . $endOfPeriod->format('d/M/Y');
            $daysInMonthFormatted = $startOfPeriod->format('M j');

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

            // Thông tin chung
            $templateProcessor->setValue('fullname', mb_strtoupper($data['fullname']));
            $templateProcessor->setValue('addressOne', $data['addressOne']);
            $templateProcessor->setValue('addressTwo', $data['addressTwo']);
            $templateProcessor->setValue('accountName', mb_strtoupper($data['accountName']));
            $templateProcessor->setValue('accountNumber', $formattedAccountNumber);
            $templateProcessor->setValue('statementPeriod', $statementPeriod);
            $templateProcessor->setValue('date', Carbon::now()->format('d/m/Y'));
            $templateProcessor->setValue('month', $daysInMonthFormatted);

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

        return response()->json([
            'message' => 'Các hóa đơn ngân hàng đã được tạo thành công.',
            'total' => count($outputFilesSuccess),
            'failures' => $outputFilesFailures,
            'zip_download_url' => $zipFileUrl,
            'data' => $outputFilesSuccess
        ], 201);
    }
}
