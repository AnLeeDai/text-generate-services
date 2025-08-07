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

        $validator = Validator::make($dataArray, [
            '*.filename' => 'required|string',
            '*.fullname' => 'required|string',
            '*.addressOne' => 'required|string',
            '*.addressTwo' => 'required|string',
            '*.accountName' => 'string|nullable',
            '*.accountNumber' => 'required|string',
            '*.statementPeriod' => 'string|nullable',
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

        foreach ($dataArray as $data) {
            // Nếu accountName rỗng thì lấy fullname
            $accountName = !empty($data['accountName']) ? $data['accountName'] : $data['fullname'];

            // Mask accountNumber: 6 số đầu + ***** + 6 số cuối (bỏ BR nếu có)
            $accountNumber = $data['accountNumber'];
            $accountNumber = preg_replace('/^BR/i', '', $accountNumber);
            $maskedNumber = substr($accountNumber, 0, 6) . "*****" . substr($accountNumber, -6);

            // Parse statementPeriod
            if (empty($data['statementPeriod'])) {
                $startDate = Carbon::now()->subMonth()->startOfMonth();
                $endDate = $startDate->copy()->endOfMonth();
                $statementPeriod = $startDate->format('d/M/Y') . ' to ' . $endDate->format('d/M/Y');
            } else {
                $dates = explode(' to ', $data['statementPeriod']);
                if (count($dates) == 2) {
                    try {
                        $startDate = Carbon::createFromFormat('d/M/Y', $dates[0]);
                        $endDate = Carbon::createFromFormat('d/M/Y', $dates[1]);
                        $statementPeriod = $startDate->format('d/M/Y') . ' to ' . $endDate->format('d/M/Y');
                    } catch (\Exception $e) {
                        $outputFilesFailures[] = ['error' => 'Định dạng ngày không hợp lệ. Vui lòng sử dụng dd/Mon/yyyy', 'data' => $data];
                        continue;
                    }
                } else {
                    $outputFilesFailures[] = ['error' => 'statementPeriod phải có định dạng dd/Mon/yyyy to dd/Mon/yyyy', 'data' => $data];
                    continue;
                }
            }

            // Month cho summary
            $month = $startDate->format('M');

            if (!file_exists($fileName)) {
                $outputFilesFailures[] = ['error' => "Không tìm thấy file mẫu tại: $fileName", 'data' => $data];
                continue;
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

            // 1. Map Header
            $templateProcessor->setValue('fullname', mb_strtoupper($data['fullname']));
            $templateProcessor->setValue('addressOne', $data['addressOne']);
            $templateProcessor->setValue('addressTwo', $data['addressTwo']);
            $templateProcessor->setValue('accountName', mb_strtoupper($accountName));
            $templateProcessor->setValue('accountNumber', $maskedNumber);
            $templateProcessor->setValue('statementPeriod', $statementPeriod);

            // 2. Map Account Summary
            // Số dư đầu kỳ: random hợp lý hoặc cố định sát mẫu
            $totalOn = rand(25500, 29500) + rand(0, 99) / 100; // 25,500.00 - 29,500.99

            // Giao dịch mẫu sát thực tế file ảnh
            $templateTransactions = [
                [
                    'desc' => 'Internet Bill',
                    'withdra' => 75.99,
                    'deposit' => 0,
                    'note' => '',
                ],
                [
                    'desc' => 'Electric Bill',
                    'withdra' => 253.68,
                    'deposit' => 0,
                    'note' => '',
                ],
                [
                    'desc' => 'Check No. %check1%',
                    'withdra' => 0,
                    'deposit' => 456.84,
                    'note' => 'Payment from Lisa Williams',
                ],
                [
                    'desc' => 'Deposit from Credit Card Processor',
                    'withdra' => 0,
                    'deposit' => 5891.26,
                    'note' => '',
                ],
                [
                    'desc' => 'Payroll Run',
                    'withdra' => 3894.75,
                    'deposit' => 0,
                    'note' => '',
                ],
                [
                    'desc' => 'Debit Transaction',
                    'withdra' => 243.36,
                    'deposit' => 0,
                    'note' => 'Main Office Wholesale',
                ],
                [
                    'desc' => 'Rent Bill',
                    'withdra' => 750.00,
                    'deposit' => 268.84,
                    'note' => '',
                ],
                [
                    'desc' => 'Check No. %check2%',
                    'withdra' => 0,
                    'deposit' => 0,
                    'note' => 'Payment from Mark Moore',
                ],
                [
                    'desc' => 'Payroll Run',
                    'withdra' => 3743.23,
                    'deposit' => 0,
                    'note' => '',
                ],
                [
                    'desc' => 'Deposit',
                    'withdra' => 0,
                    'deposit' => 3656.45,
                    'note' => '',
                ],
                [
                    'desc' => 'Debit Transaction',
                    'withdra' => 1548.96,
                    'deposit' => 0,
                    'note' => 'ABC Business Supplies',
                ],
            ];

            // Random ngày giao dịch thực sự trong khoảng period (không trùng, sắp xếp tăng dần)
            $periodStart = $startDate->copy();
            $periodEnd = $endDate->copy();
            $daysRange = $periodEnd->diffInDays($periodStart);

            $transactionDates = [];
            while (count($transactionDates) < count($templateTransactions)) {
                $randDay = $periodStart->copy()->addDays(rand(0, $daysRange))->format('m/d');
                if (!in_array($randDay, $transactionDates)) {
                    $transactionDates[] = $randDay;
                }
            }
            sort($transactionDates);

            // Random check number
            $check1 = rand(1000, 9999);
            $check2 = rand(100, 999);

            // Tính tổng tiền vào, tổng tiền ra theo giao dịch thực tế
            $totalIn = 0;
            $totalOut = 0;
            foreach ($templateTransactions as $t) {
                $totalIn += $t['deposit'];
                $totalOut += $t['withdra'];
            }
            $ebBal = $totalOn + $totalIn - $totalOut;

            $templateProcessor->setValue('month', $month);
            $templateProcessor->setValue('totalOn', number_format($totalOn, 2, '.', ','));
            $templateProcessor->setValue('totalIn', number_format($totalIn, 2, '.', ','));
            $templateProcessor->setValue('totalOut', number_format($totalOut, 2, '.', ','));
            $templateProcessor->setValue('ebBal', number_format($ebBal, 2, '.', ','));

            // 3. Map Transactions (theo ngày random, sát thực tế)
            $balance = $totalOn;
            $templateProcessor->setValue("balance1", number_format($totalOn, 2, '.', ','));
            foreach ($templateTransactions as $i => $t) {
                $date = $transactionDates[$i];
                // Thay check number vào description nếu có
                $desc = str_replace(['%check1%', '%check2%'], [$check1, $check2], $t['desc']);
                $withdra = $t['withdra'] ? number_format($t['withdra'], 2, '.', ',') : '';
                $deposit = $t['deposit'] ? number_format($t['deposit'], 2, '.', ',') : '';
                $balance = $balance + $t['deposit'] - $t['withdra'];

                $templateProcessor->setValue("date" . ($i + 1), $date);
                $templateProcessor->setValue("desc" . ($i + 1), $desc);
                $templateProcessor->setValue("withdra" . ($i + 1), $withdra);
                $templateProcessor->setValue("deposit" . ($i + 1), $deposit);
                $templateProcessor->setValue("balance" . ($i + 2), number_format($balance, 2, '.', ','));

                // Map note nếu có
                if ($t['note']) {
                    $templateProcessor->setValue("note" . ($i + 1), $t['note']);
                }
            }
            // Số dư cuối kỳ
            $templateProcessor->setValue("ebBal", number_format($balance, 2, '.', ','));

            // Lưu file
            $sanitizedFilename = str_replace(['-', ' '], '_', $data['filename']);
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
            } catch (\Exception $e) {
                $outputFilesFailures[] = [
                    'error' => 'Không thể lưu tài liệu đã tạo: ' . $e->getMessage(),
                    'data' => $data
                ];
            }
        }

        // Trả về kết quả
        $response = [
            'message' => 'Các hóa đơn ngân hàng đã được tạo thành công.',
            'total_success' => count($outputFilesSuccess),
            'total_failures' => count($outputFilesFailures),
            'success_files' => $outputFilesSuccess,
        ];
        if (!empty($outputFilesFailures)) {
            $response['failures'] = $outputFilesFailures;
        }
        return response()->json($response, 201);
    }
}