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
        $generatedFilePaths = [];

        foreach ($dataArray as $dataIdx => $data) {
            $data['accountName'] = $data['accountName'] ?? $data['fullname'];
            $accountNumber = $data['accountNumber'];
            $accountNumberWithoutPrefix = preg_replace('/^BR/', '', $accountNumber);
            $formattedAccountNumber = substr($accountNumberWithoutPrefix, 0, 6) . "*****" . substr($accountNumberWithoutPrefix, -6);

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
                return response()->json([
                    'error' => 'Không thể tải mẫu: ' . $e->getMessage(),
                    'data' => $data
                ], 500);
            }

            $templateProcessor->setValue('fullname', mb_strtoupper($data['fullname']));
            $templateProcessor->setValue('addressOne', $data['addressOne']);
            $templateProcessor->setValue('addressTwo', $data['addressTwo']);
            $templateProcessor->setValue('accountName', mb_strtoupper($data['accountName']));
            $templateProcessor->setValue('accountNumber', $formattedAccountNumber);
            $templateProcessor->setValue('statementPeriod', $data['statementPeriod']);
            $templateProcessor->setValue('date', Carbon::now()->format('d/m/Y'));
            $templateProcessor->setValue('month', $daysInMonthFormatted);

            $totalOn = 27563.55;
            $templateProcessor->setValue('balance1', number_format($totalOn, 2, '.', ','));

            $numTransactions = 11;
            $allDates = [];
            $period = Carbon::parse($startDate)->daysUntil($endDate->copy()->addDay());
            foreach ($period as $dayObj) {
                $allDates[] = $dayObj->copy();
            }
            if (count($allDates) >= $numTransactions) {
                $chosenKeys = array_rand($allDates, $numTransactions);
                if (!is_array($chosenKeys))
                    $chosenKeys = [$chosenKeys];
                $chosenDays = [];
                foreach ($chosenKeys as $k)
                    $chosenDays[] = $allDates[$k];
                usort($chosenDays, fn($a, $b) => $a->timestamp - $b->timestamp);
            } else {
                $chosenDays = [];
                for ($i = 0; $i < $numTransactions; $i++) {
                    $offset = intval($i * (count($allDates) - 1) / ($numTransactions - 1));
                    $chosenDays[] = $allDates[$offset];
                }
            }
            $dates = array_map(fn($d) => $d->format('m/d'), $chosenDays);

            $withdra = [];
            $deposit = [];
            $withdra[1] = "75.99";
            $withdra[2] = "253.68";
            $withdra[3] = "3894.75";
            $withdra[4] = "243.36";
            $withdra[5] = "750.00";
            $withdra[6] = number_format(rand(100, 1000) / 100, 2, '.', ',');
            $withdra[7] = number_format(rand(100, 1000) / 100, 2, '.', ',');

            $deposit[1] = "456.84";
            $deposit[2] = "33581.98";
            $deposit[3] = "456.84";
            $deposit[4] = "5891.26";

            $checkNo1 = 5753;
            $checkNo2 = 220;

            $rows = [
                [1, 1, 0, 0, "Internet Bill"],
                [2, 2, 0, 0, "Electric Bill"],
                [3, 0, 1, 1, "Check No. $checkNo1\nPayment from Lisa Williams"],
                [4, 0, 2, 0, "Deposit from Credit Card Processor"],
                [5, 3, 0, 0, "Payroll Run"],
                [6, 4, 0, 0, "Debit Transaction\nMain Office Wholesale"],
                [7, 5, 3, 0, "Rent Bill"],
                [8, 0, 0, 2, "Check No. $checkNo2\nPayment From Mark Moore"],
                [9, 6, 0, 0, "Payroll Run"],
                [10, 0, 4, 0, "Deposit"],
                [11, 7, 0, 0, "Debit Transaction\nABC Business Supplies"],
            ];

            $balance = $totalOn;
            for ($i = 1; $i <= 11; $i++) {
                $templateProcessor->setValue("date$i", $dates[$i - 1]);
                $w = $rows[$i - 1][1] ? $withdra[$rows[$i - 1][1]] : number_format(rand(100, 1000) / 100, 2, '.', ',');
                $templateProcessor->setValue("withdra$i", $w);
                $d = $rows[$i - 1][2] ? $deposit[$rows[$i - 1][2]] : number_format(rand(100, 1000) / 100, 2, '.', ',');
                $templateProcessor->setValue("deposit$i", $d);
                $balance = $balance - floatval(str_replace(',', '', $w)) + floatval(str_replace(',', '', $d));
                $templateProcessor->setValue("balance" . ($i + 1), number_format($balance, 2, '.', ','));
            }
            $templateProcessor->setValue('ebBal', number_format($balance, 2, '.', ','));
            $templateProcessor->setValue("checkNo1", $checkNo1);
            $templateProcessor->setValue("checkNo2", $checkNo2);

            $totalMoneyIn = 0;
            $totalMoneyOut = 0;
            for ($i = 1; $i <= 11; $i++) {
                $w = $rows[$i - 1][1] ? floatval(str_replace(',', '', $withdra[$rows[$i - 1][1]])) : 0;
                $d = $rows[$i - 1][2] ? floatval(str_replace(',', '', $deposit[$rows[$i - 1][2]])) : 0;
                $totalMoneyIn += $d;
                $totalMoneyOut += $w;
            }
            $templateProcessor->setValue('totalIn', 'R$' . number_format($totalMoneyIn, 2, '.', ','));
            $templateProcessor->setValue('totalOut', 'R$' . number_format($totalMoneyOut, 2, '.', ','));
            $templateProcessor->setValue('balanceOn', 'R$' . number_format($balance, 2, '.', ','));

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
                return response()->json([
                    'error' => 'Không thể lưu tài liệu đã tạo: ' . $e->getMessage(),
                    'data' => $data
                ], 500);
            }
        }

        $zipFileName = 'bank_bills_' . time() . '.zip';
        $zipFilePath = public_path("generated/$zipFileName");
        $zip = new \ZipArchive();
        if ($zip->open($zipFilePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
            foreach ($generatedFilePaths as $filePath) {
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
            'zip_url' => $zipUrl,
            'data' => $outputFilesSuccess,
        ], 201);
    }
}