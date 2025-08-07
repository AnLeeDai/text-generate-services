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
        'demo2' => 'demo2.docx',
    ];

    public function __construct()
    {
        $this->templatePath = array_map(function ($template) {
            return storage_path("app/private/templates/$template");
        }, $this->templatePath);
    }

    public function generateBankBillBTGPactualGenerate(Request $request)
    {
        $fileName = $this->templatePath['btg_pactual'];

        $data = $request->all();
        $validator = Validator::make($data, [
            'filename' => 'required|string',
            'fullname' => 'required|string',
            'addressOne' => 'required|string',
            'addressTwo' => 'required|string',
            'accountName' => 'string',
            'accountNumber' => 'required|string',
            'statementPeriod' => [
                'required',
                function ($attribute, $value, $fail) {
                    if (!preg_match('/^\d{2}\/[A-Za-z]{3}\/\d{4} to \d{2}\/[A-Za-z]{3}\/\d{4}$/', $value)) {
                        return $fail("{$attribute} must be in the format DD/Mon/YYYY to DD/Mon/YYYY.");
                    }

                    $dates = explode(' to ', $value);
                    if (count($dates) === 2) {
                        $startDate = Carbon::createFromFormat('d/M/Y', $dates[0]);
                        $endDate = Carbon::createFromFormat('d/M/Y', $dates[1]);

                        if (!$startDate || !$endDate) {
                            return $fail("{$attribute} dates are invalid.");
                        }
                        if ($startDate > $endDate) {
                            return $fail("{$attribute} start date cannot be later than end date.");
                        }
                    } else {
                        return $fail("{$attribute} must contain a valid date range.");
                    }
                }
            ],
        ], [
            'filename.required' => 'Tên file là bắt buộc',
            'fullname.required' => 'Họ và tên là bắt buộc',
            'addressOne.required' => 'Địa chỉ dòng một là bắt buộc',
            'addressTwo.required' => 'Địa chỉ dòng hai là bắt buộc',
            'accountNumber.required' => 'Số tài khoản là bắt buộc',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data['accountName'] = $data['fullname'];

        $templatePath = $fileName;

        if (!file_exists($templatePath)) {
            return response()->json(['error' => "Không tìm thấy file mẫu tại: $templatePath"], 400);
        }

        try {
            $templateProcessor = new TemplateProcessor($templatePath);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Không thể tải mẫu: ' . $e->getMessage()], 500);
        }

        $templateProcessor->setValue('fullname', $data['fullname']);
        $templateProcessor->setValue('addressOne', $data['addressOne']);
        $templateProcessor->setValue('addressTwo', $data['addressTwo']);
        $templateProcessor->setValue('accountName', $data['accountName']);
        $templateProcessor->setValue('accountNumber', $data['accountNumber']);
        $templateProcessor->setValue('statementPeriod', $data['statementPeriod']);
        $templateProcessor->setValue('date', Carbon::now()->format('d/m/Y'));

        $sanitizedFilename = str_replace('-', '_', strtolower($data['filename']));
        $outputFileName = 'btg_pactual_business_' . $sanitizedFilename . '.docx';
        $outputFilePath = storage_path("app/private/generated/$outputFileName");

        if (!file_exists(dirname($outputFilePath))) {
            mkdir(dirname($outputFilePath), 0755, true);
        }

        try {
            $templateProcessor->saveAs($outputFilePath);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Không thể lưu tài liệu đã tạo: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'message' => 'Hóa đơn ngân hàng đã được tạo thành công.',
            'file' => $outputFileName,
            'file_url' => url("storage/private/generated/$outputFileName")
        ], 201);
    }

}