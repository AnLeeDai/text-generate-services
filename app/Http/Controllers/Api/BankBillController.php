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
            '*.accountName' => 'string',
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
            // Thêm accountName từ fullname
            $data['accountName'] = $data['fullname'];

            // Xử lý số tài khoản: Loại bỏ "BR" và hiển thị 6 số đầu và 6 số cuối, phần giữa là "*****"
            $accountNumber = $data['accountNumber'];
            $accountNumberWithoutPrefix = substr($accountNumber, 2);
            $formattedAccountNumber = substr($accountNumberWithoutPrefix, 0, 6) . "*****" . substr($accountNumberWithoutPrefix, -6);

            // Nếu không có statementPeriod, tự động chọn trong 2 tháng gần nhất
            if (empty($data['statementPeriod'])) {
                $startDate = Carbon::now()->subMonth()->startOfMonth();
                $endDate = $startDate->copy()->endOfMonth();
                $data['statementPeriod'] = $startDate->format('d/M/Y') . ' to ' . $endDate->format('d/M/Y');
            } else {
                // Kiểm tra và định dạng lại date nếu không đúng
                $dates = explode(' to ', $data['statementPeriod']);
                if (count($dates) == 2) {
                    try {
                        $startDate = Carbon::createFromFormat('d/M/Y', $dates[0]);
                        $endDate = Carbon::createFromFormat('d/M/Y', $dates[1]);
                        $data['statementPeriod'] = $startDate->format('d/M/Y') . ' to ' . $endDate->format('d/M/Y');
                    } catch (\Exception $e) {
                        return response()->json(['error' => 'Định dạng ngày không hợp lệ. Vui lòng sử dụng dd/Mon/yyyy'], 400);
                    }
                } else {
                    return response()->json(['error' => 'statementPeriod phải có định dạng dd/Mon/yyyy to dd/Mon/yyyy'], 400);
                }
            }

            // Tính toán tháng và ngày đầu tháng
            $month = $startDate->format('M');
            $day = $startDate->format('j');
            $daysInMonthFormatted = "$month $day";

            if (!file_exists($fileName)) {
                return response()->json(['error' => "Không tìm thấy file mẫu tại: $fileName"], 400);
            }

            try {
                $templateProcessor = new TemplateProcessor($fileName);
            } catch (\Exception $e) {
                // Thêm lỗi vào mảng thất bại nếu không thể tải mẫu
                $outputFilesFailures[] = [
                    'error' => 'Không thể tải mẫu: ' . $e->getMessage(),
                    'data' => $data
                ];
                continue;
            }

            // Thay thế các giá trị trong template
            $templateProcessor->setValue('fullname', mb_strtoupper($data['fullname']));
            $templateProcessor->setValue('addressOne', $data['addressOne']);
            $templateProcessor->setValue('addressTwo', $data['addressTwo']);
            $templateProcessor->setValue('accountName', mb_strtoupper($data['accountName']));
            $templateProcessor->setValue('accountNumber', $formattedAccountNumber);
            $templateProcessor->setValue('statementPeriod', $data['statementPeriod']);
            $templateProcessor->setValue('date', Carbon::now()->format('d/m/Y'));
            $templateProcessor->setValue('month', $daysInMonthFormatted);

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
            } catch (\Exception $e) {
                $outputFilesFailures[] = [
                    'error' => 'Không thể lưu tài liệu đã tạo: ' . $e->getMessage(),
                    'data' => $data
                ];
            }
        }

        return response()->json([
            'message' => 'Các hóa đơn ngân hàng đã được tạo thành công.',
            'total' => count($outputFilesSuccess),
            'failures' => $outputFilesFailures,
            'data' => $outputFilesSuccess,
        ], 201);
    }
}