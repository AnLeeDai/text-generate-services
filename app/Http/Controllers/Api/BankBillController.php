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
            // Random số dư và tổng cộng cho đẹp
            $totalOn = rand(2000000, 3000000) / 100; // Số dư đầu kỳ
            $totalIn = rand(1000000, 2000000) / 100; // Tổng tiền vào
            $totalOut = rand(800000, 1500000) / 100;  // Tổng tiền ra
            $ebBal = $totalOn + $totalIn - $totalOut;

            $templateProcessor->setValue('month', $month);
            $templateProcessor->setValue('totalOn', number_format($totalOn, 2, '.', ','));
            $templateProcessor->setValue('totalIn', number_format($totalIn, 2, '.', ','));
            $templateProcessor->setValue('totalOut', number_format($totalOut, 2, '.', ','));
            $templateProcessor->setValue('ebBal', number_format($ebBal, 2, '.', ','));

            // 3. Map Transactions (11 giao dịch, mapping đúng placeholder trong ảnh)
            // Danh sách mô tả mẫu và các dòng đặc biệt
            $descs = [
                ['desc' => 'Internet Bill', 'withdra' => rand(50, 150) / 1.0, 'deposit' => 0],
                ['desc' => 'Electric Bill', 'withdra' => rand(80, 200) / 1.0, 'deposit' => 0],
                ['desc' => '', 'withdra' => 0, 'deposit' => rand(200, 500) / 1.0], // check dòng 3
                ['desc' => 'Payroll Run', 'withdra' => rand(500, 1000) / 1.0, 'deposit' => 0],
                ['desc' => 'Deposit', 'withdra' => 0, 'deposit' => rand(300, 700) / 1.0],
                ['desc' => 'Debit Transaction', 'withdra' => rand(200, 400) / 1.0, 'deposit' => 0],
                ['desc' => 'Rent Bill', 'withdra' => rand(700, 1200) / 1.0, 'deposit' => rand(200, 400) / 1.0],
                ['desc' => '', 'withdra' => 0, 'deposit' => 0], // check dòng 8
                ['desc' => 'Payroll Run', 'withdra' => rand(500, 1000) / 1.0, 'deposit' => 0],
                ['desc' => 'Debit Transaction', 'withdra' => rand(300, 700) / 1.0, 'deposit' => 0],
            ];
            // Các dòng đặc biệt: check, payment from ...
            $checkNoRand1 = rand(1000, 9999);
            $checkNoRand2 = rand(1000, 9999);
            $desc3 = 'Payment from Lisa Williams';
            $desc6 = 'Main Office Wholesale';
            $desc7 = 'Payment from Mark Moore';
            $desc10 = 'ABC Business Supplies';

            // Giao dịch ngày từ đầu đến cuối kỳ
            $balance = $totalOn;
            for ($i = 1; $i <= 11; $i++) {
                $curDate = $startDate->copy()->addDays($i - 1)->format('m/d');
                $templateProcessor->setValue("date$i", $curDate);

                // Xử lý từng dòng theo đúng template
                if ($i == 3) {
                    // Dòng check + payment
                    $templateProcessor->setValue("desc$i", "Check No. $checkNoRand1");
                    $templateProcessor->setValue("checkNoRand1", $checkNoRand1);
                    $templateProcessor->setValue("desc3", $desc3);
                    $withdra = '';
                    $deposit = number_format($descs[2]['deposit'], 2, '.', ',');
                    $balance += $descs[2]['deposit'];
                } elseif ($i == 6) {
                    $templateProcessor->setValue("desc$i", 'Debit Transaction');
                    $templateProcessor->setValue("desc6", $desc6);
                    $withdra = number_format($descs[5]['withdra'], 2, '.', ',');
                    $deposit = '';
                    $balance -= $descs[5]['withdra'];
                } elseif ($i == 8) {
                    $templateProcessor->setValue("desc$i", "Check No. $checkNoRand2");
                    $templateProcessor->setValue("checkNoRand2", $checkNoRand2);
                    $templateProcessor->setValue("desc7", $desc7);
                    $withdra = '';
                    $deposit = '';
                } elseif ($i == 11) {
                    $templateProcessor->setValue("desc$i", 'Debit Transaction');
                    $templateProcessor->setValue("desc10", $desc10);
                    $withdra = number_format($descs[9]['withdra'], 2, '.', ',');
                    $deposit = '';
                    $balance -= $descs[9]['withdra'];
                } else {
                    $templateProcessor->setValue("desc$i", $descs[$i - 1]['desc']);
                    $withdra = $descs[$i - 1]['withdra'] ? number_format($descs[$i - 1]['withdra'], 2, '.', ',') : '';
                    $deposit = $descs[$i - 1]['deposit'] ? number_format($descs[$i - 1]['deposit'], 2, '.', ',') : '';
                    $balance = $balance + $descs[$i - 1]['deposit'] - $descs[$i - 1]['withdra'];
                }

                $templateProcessor->setValue("withdra$i", $withdra);
                $templateProcessor->setValue("deposit" . ($i <= 4 ? $i : $i - 4), $deposit); // mapping deposit1-4
                $templateProcessor->setValue("balance" . ($i + 1), number_format($balance, 2, '.', ','));
            }
            // Số dư đầu kỳ
            $templateProcessor->setValue("balance1", number_format($totalOn, 2, '.', ','));
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