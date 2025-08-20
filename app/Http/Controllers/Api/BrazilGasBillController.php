<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use PhpOffice\PhpWord\TemplateProcessor;
use Illuminate\Http\Request;
use Validator;
use Carbon\Carbon;

class BrazilGasBillController extends Controller
{
    private $allowedPlaceholders = [];

    public function getPlaceholders()
    {
        $templatePath = storage_path('app/private/brazil_gas_bill_template.docx');

        $zip = new \ZipArchive();
        $xml = '';
        if ($zip->open($templatePath) === true) {
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();
        }

        $plainText = strip_tags($xml);
        $plainText = preg_replace('/\s+/', '', $plainText);
        preg_match_all('/\$\{(.*?)\}/', $plainText, $matches);

        $placeholders = array_unique($matches[0]);
        $this->allowedPlaceholders = array_values($placeholders);

        return $this->allowedPlaceholders;
    }

    public function generate(Request $request)
    {
        $templatePath = storage_path('app/private/brazil_gas_bill_template.docx');
        $dataArray = $request->all();

        if (!is_array($dataArray) || (isset($dataArray[0]) && !is_array($dataArray))) {
            $dataArray = [$dataArray];
        }

        $validator = Validator::make($dataArray, [
            '*.filename' => 'required|string',
            '*.fullName' => 'required|string',
            '*.fullAddress' => 'required|string',
            '*.accountNum' => 'required|string',
            '*.addressOne' => 'required|string',
            '*.addressTwo' => 'required|string',
            '*.therms' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $placeholders = $this->getPlaceholders();

        $outputFilesSuccess = [];
        $outputFilesFailures = [];
        $generatedFilePaths = [];

        foreach ($dataArray as $data) {

            if (!file_exists($templatePath)) {
                return $this->notFoundResponse("Không tìm thấy file mẫu tại: $templatePath");
            }

            try {
                $templateProcessor = new TemplateProcessor($templatePath);
            } catch (\Exception $e) {
                $outputFilesFailures[] = [
                    'error' => 'Không thể tải template: ' . $e->getMessage(),
                    'data' => $data
                ];
                continue;
            }

            $digits = preg_replace('/\D/', '', $data['accountNum']);
            $formattedAccount = substr($digits, 0, 8) . '*****' . substr($digits, -4);

            if (isset($data['fullName'])) {
                $data['fullName'] = strtoupper($data['fullName']);
            }

            try {
                $baseDate = Carbon::now()->subDay();
                $randomDays = rand(0, 29);
                $nowDate = $baseDate->copy()->subDays($randomDays);

                if (!empty($data['nowDate'])) {
                } else {
                }
            } catch (\Exception $e) {
            }

            $prevMonth = $nowDate->copy()->subMonth();
            $nextMonth = $nowDate->copy()->addMonth();

            $therms = $data['therms'];
            $totalUsage = $therms * 0.829846 + 7.84;
            $sumCosts = $totalUsage + 155.10;
            $totalGas = $therms * 0.340811 + 3.22;
            $sumGas = $totalGas;
            $oldPrev = $therms + 0.03;

            $autoValues = [
                '${nowDate}' => $nowDate->format('F d, Y'),
                '${dateAmount}' => $nowDate->format('F d, Y'),
                '${sumMaryDate}' => $nowDate->copy()->subDays(43)->format('F d, Y'),
                '${prevMonth}' => $prevMonth->copy()->day(15)->format('M d, Y'),
                '${nextMonth}' => $nextMonth->copy()->day($nowDate->day)->format('F d, Y'),
                '${sDate}' => $prevMonth->copy()->day(15)->format('m-d-y'),
                '${eDate}' => $nowDate->format('m-d-y'),
                '${monthPrev}' => $prevMonth->format('F Y'),
                '${monthNow}' => $nowDate->format('F Y'),
                '${therms}' => number_format($therms, 2),
                '${totalUsage}' => '$' . number_format($totalUsage, 2),
                '${sumCosts}' => '$' . number_format($sumCosts, 2),
                '${totalGas}' => '$' . number_format($totalGas, 2),
                '${sumGas}' => '$' . number_format($sumGas, 2),
                '${oldPrev}' => number_format($oldPrev, 2),
            ];

            foreach ($placeholders as $p) {
                $name = trim($p, '${}');

                if ($name === 'accountNum') {
                    $templateProcessor->setValue($p, $formattedAccount);
                } elseif (array_key_exists($name, $data)) {
                    $templateProcessor->setValue($p, $data[$name]);
                } elseif (isset($autoValues[$p])) {
                    $templateProcessor->setValue($p, $autoValues[$p]);
                }
            }

            $safe = str_replace('-', '_', $data['filename']);
            $outputName = "brazil_gas_bill_{$safe}.docx";
            $outputPath = public_path("generated/$outputName");

            if (!file_exists(dirname($outputPath))) {
                mkdir(dirname($outputPath), 0755, true);
            }

            try {
                $templateProcessor->saveAs($outputPath);
                $generatedFilePaths[] = $outputPath;
                $outputFilesSuccess[] = [
                    'file' => $outputName,
                    'file_url' => url("generated/$outputName")
                ];
            } catch (\Exception $e) {
                $outputFilesFailures[] = [
                    'error' => 'Không thể lưu file: ' . $e->getMessage(),
                    'data' => $data
                ];
            }
        }

        $zipDownloadUrl = null;
        if (count($generatedFilePaths) > 0) {
            $zipName = 'generated/brazil_bills_' . date('Ymd_His') . '.zip';
            $zipPath = public_path($zipName);

            $zip = new \ZipArchive();
            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE)) {
                foreach ($generatedFilePaths as $filePath) {
                    $zip->addFile($filePath, basename($filePath));
                }
                $zip->close();
                $zipDownloadUrl = url($zipName);
            }
        }

        return $this->createdResponse([
            'total' => count($outputFilesSuccess),
            'failures' => $outputFilesFailures,
            'zip_download_url' => $zipDownloadUrl,
            'files' => $outputFilesSuccess
        ], 'Các hóa đơn Brazil Gas đã được tạo thành công.');
    }
}