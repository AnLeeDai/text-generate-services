<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PhpOffice\PhpWord\TemplateProcessor;
use Validator;

class BanrisulBillController extends Controller
{
    private $templatePath = [
        'banrisul' => 'btg_banrisul_bank_bill_template.docx',
    ];

    public function __construct()
    {
        $this->templatePath = array_map(fn($template) => storage_path("app/private/$template"), $this->templatePath);
    }

    /**
     * API generate bank bill Banrisul
     */
    public function generateBanrisulBillGenerate(Request $request)
    {
        $fileName = $this->templatePath['banrisul'];
        $dataArray = $request->all();

        // Đảm bảo dataArray là array
        if (!is_array($dataArray) || (isset($dataArray[0]) && !is_array($dataArray))) {
            $dataArray = [$dataArray];
        }

        $validator = Validator::make($dataArray, [
            '*.filename' => 'required|string',
            '*.fullname' => 'required|string',
            '*.address1' => 'required|string', 
            '*.address2' => 'required|string',
            '*.accountNumber' => 'required|string'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $outputFilesSuccess = [];
        $outputFilesFailures = [];
        $generatedFilePaths = [];

        foreach ($dataArray as $data) {
            $rawAccountNumber = $data['accountNumber'];
            // Loại bỏ tất cả ký tự không phải số
            $numberPart = preg_replace('/\D/', '', $rawAccountNumber);

            // Lấy 8 số đầu và 8 số cuối (nếu có)
            $first8 = substr($numberPart, 0, 8);
            $remain = substr($numberPart, 8); // phần còn lại sau 8 số đầu
            // Lấy 8 số cuối từ phần còn lại, nếu không đủ thì lấy hết
            $last8 = strlen($remain) > 8 ? substr($remain, -8) : $remain;
            // Nếu có phần cuối (last8) thì format, nếu không thì chỉ lấy 8 số đầu
            if ($last8 !== '') {
                $formattedAccountNumber = $first8 . '*****' . $last8;
            } else {
                $formattedAccountNumber = $first8;
            }

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

            // Map placeholders từ template
            $templateProcessor->setValue('fullname', mb_strtoupper($data['fullname'])); // ${fullname} và {fullname}
            $templateProcessor->setValue('address1', $data['address1']); // {address1}
            $templateProcessor->setValue('address2', $data['address2']); // {address2}
            $templateProcessor->setValue('accountNo', $formattedAccountNumber); // {accountNo}

            $sanitizedFilename = str_replace('-', '_', $data['filename']);
            $outputFileName = "banrisul_business_{$sanitizedFilename}.docx";
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
            $zipFileName = 'generated/banrisul_bills_' . date('Ymd_His') . '.zip';
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
        ], 'Các hóa đơn ngân hàng Banrisul đã được tạo thành công.');
    }
}
