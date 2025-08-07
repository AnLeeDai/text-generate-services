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

            // === ACCOUNT SUMMARY DATA (bám sát mẫu, động theo period) ===
            $totalOn = 28375.76;
            // Giao dịch mẫu giống mẫu ảnh số 2, nhưng số ngày lấy động trong period
            $transactions = [
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
                    'deposit' => 0,
                    'note' => 'Payment from Check No. %check1note%',
                ],
                [
                    'desc' => 'Deposit from Credit Card Processor',
                    'withdra' => 0,
                    'deposit' => 5891.26,
                    'note' => '',
                ],
                [
                    'desc' => 'Payroll Run',
                    'withdra' => 0,
                    'deposit' => 0,
                    'note' => '',
                ],
                [
                    'desc' => 'Debit Transaction',
                    'withdra' => 0,
                    'deposit' => 0,
                    'note' => 'Debit Transaction',
                ],
                [
                    'desc' => 'Rent Bill',
                    'withdra' => 3894.75,
                    'deposit' => 456.84,
                    'note' => '',
                ],
                [
                    'desc' => 'Check No. %check2%',
                    'withdra' => 0,
                    'deposit' => 0,
                    'note' => 'Check No. %check2note%',
                ],
                [
                    'desc' => 'Payroll Run',
                    'withdra' => 243.36,
                    'deposit' => 0,
                    'note' => '',
                ],
                [
                    'desc' => 'Deposit',
                    'withdra' => 0,
                    'deposit' => 5891.26,
                    'note' => '',
                ],
                [
                    'desc' => 'Debit Transaction',
                    'withdra' => 750.00,
                    'deposit' => 0,
                    'note' => 'Debit Transaction',
                ],
            ];

            // Ngày động: RANDOM KHÔNG TRÙNG trong khoảng period, có thể không tăng dần
            $numTrans = count($transactions);
            $periodDays = $endDate->diffInDays($startDate);
            $availableDayIndexes = range(0, $periodDays);
            shuffle($availableDayIndexes);
            $transactionDates = [];
            for ($i = 0; $i < $numTrans; $i++) {
                $transactionDates[] = $startDate->copy()->addDays($availableDayIndexes[$i])->format('m/d');
            }

            // Random check number động mỗi lần xuất file
            $checkNoRand1 = rand(9000, 9999);
            $checkNoRand2 = rand(100, 999);

            // Tổng tiền vào/ra: lấy thực tế từ danh sách giao dịch
            $totalIn = 0;
            $totalOut = 0;
            foreach ($transactions as $t) {
                $totalIn += $t['deposit'];
                $totalOut += $t['withdra'];
            }
            $ebBal = $totalOn + $totalIn - $totalOut;

            // Map account summary
            $templateProcessor->setValue('month', $month);
            $templateProcessor->setValue('totalOn', number_format($totalOn, 2, '.', ','));
            $templateProcessor->setValue('totalIn', number_format($totalIn, 2, '.', ','));
            $templateProcessor->setValue('totalOut', number_format($totalOut, 2, '.', ','));
            $templateProcessor->setValue('ebBal', number_format($ebBal, 2, '.', ','));

            // 3. Map Transactions
            $balance = $totalOn;
            $templateProcessor->setValue("balance1", number_format($totalOn, 2, '.', ','));

            foreach ($transactions as $i => $t) {
                $date = $transactionDates[$i];
                $desc = $t['desc'];
                $note = $t['note'];

                // Thay số check động vào desc/note nếu có
                $desc = str_replace(
                    ['%check1%', '%check2%'],
                    [$checkNoRand1, $checkNoRand2],
                    $desc
                );
                $note = str_replace(
                    ['%check1note%', '%check2note%'],
                    [$checkNoRand1, $checkNoRand2],
                    $note
                );

                $withdra = $t['withdra'] ? number_format($t['withdra'], 2, '.', ',') : '';
                $deposit = $t['deposit'] ? number_format($t['deposit'], 2, '.', ',') : '';
                $balance = $balance + $t['deposit'] - $t['withdra'];

                $templateProcessor->setValue("date".($i+1), $date);
                $templateProcessor->setValue("desc".($i+1), $desc);
                $templateProcessor->setValue("withdra".($i+1), $withdra);
                $templateProcessor->setValue("deposit".($i+1), $deposit);
                $templateProcessor->setValue("balance".($i+2), number_format($balance, 2, '.', ','));

                // Chỉ map note nếu là Check No hoặc Debit Transaction
                if (
                    stripos($desc, 'check no') !== false ||
                    stripos($desc, 'debit transaction') !== false
                ) {
                    if ($note) {
                        $templateProcessor->setValue("note".($i+1), $note);
                    }
                } else {
                    // Xóa hoặc để trống note nếu không phải 2 loại trên
                    $templateProcessor->setValue("note".($i+1), '');
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