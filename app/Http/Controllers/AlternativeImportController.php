<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AlternativeImportController extends Controller
{
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:csv,txt,xlsx'
        ]);

        $file = $request->file('file');
        $extension = $file->getClientOriginalExtension();

        if ($extension === 'xlsx') {
            return $this->importExcel($file);
        } else {
            return $this->importCsv($file);
        }
    }

    private function importCsv($file)
    {
        // قراءة الملف مع encoding مختلف
        $content = file_get_contents($file->getPathname());

        // تجربة encodings مختلفة
        $encodings = ['UTF-8', 'Windows-1256', 'ISO-8859-6', 'CP1256'];
        $detectedEncoding = mb_detect_encoding($content, $encodings, true);

        if ($detectedEncoding && $detectedEncoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $detectedEncoding);
        }

        // إزالة BOM إن وجد
        $content = str_replace("\xEF\xBB\xBF", '', $content);

        // تقسيم إلى أسطر
        $lines = explode("\n", $content);
        $headers = str_getcsv(array_shift($lines));

        // تنظيف العناوين
        $headers = array_map('trim', $headers);

        $imported = 0;
        $errors = [];

        foreach ($lines as $lineNumber => $line) {
            if (empty(trim($line))) continue;

            try {
                $data = str_getcsv($line);

                if (count($data) !== count($headers)) {
                    $errors[] = "Line " . ($lineNumber + 2) . ": Column count mismatch";
                    continue;
                }

                $row = array_combine($headers, $data);

                // تنظيف البيانات
                foreach ($row as $key => $value) {
                    $row[$key] = $this->cleanArabicText($value);
                }

                $this->createShipment($row);
                $imported++;

            } catch (\Exception $e) {
                $errors[] = "Line " . ($lineNumber + 2) . ": " . $e->getMessage();
                Log::error("Import error on line " . ($lineNumber + 2), [
                    'error' => $e->getMessage(),
                    'data' => $line
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'imported' => $imported,
            'errors' => $errors
        ]);
    }

    private function importExcel($file)
    {
        try {
            // استخدام PhpSpreadsheet مباشرة لتحكم أفضل
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($file->getPathname());

            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            if (empty($rows)) {
                return response()->json(['error' => 'Empty file'], 400);
            }

            // العناوين من الصف الأول
            $headers = array_map('trim', $rows[0]);
            unset($rows[0]);

            $imported = 0;
            $errors = [];

            foreach ($rows as $index => $rowData) {
                if (array_filter($rowData)) { // تجاهل الصفوف الفارغة
                    try {
                        $row = array_combine($headers, $rowData);

                        // تنظيف النصوص العربية
                        foreach ($row as $key => $value) {
                            if (is_string($value)) {
                                $row[$key] = $this->cleanArabicText($value);
                            }
                        }

                        $this->createShipment($row);
                        $imported++;

                    } catch (\Exception $e) {
                        $errors[] = "Row " . ($index + 1) . ": " . $e->getMessage();
                    }
                }
            }

            return response()->json([
                'success' => true,
                'imported' => $imported,
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function cleanArabicText($text)
    {
        if (empty($text)) {
            return $text;
        }

        // إزالة BOM والمسافات
        $text = trim(str_replace(["\xEF\xBB\xBF", "\xFF\xFE", "\xFE\xFF"], '', $text));

        // تجربة تحويلات encoding مختلفة للنصوص التي تحتوي على أحرف غريبة
        if (!empty($text) && !preg_match('//u', $text)) {
            $encodings = ['Windows-1256', 'ISO-8859-6', 'CP1256', 'UTF-8'];

            foreach ($encodings as $encoding) {
                try {
                    $converted = iconv($encoding, 'UTF-8//IGNORE', $text);
                    if ($converted !== false && preg_match('/[\x{0600}-\x{06FF}]/u', $converted)) {
                        return $converted;
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
        }

        return $text;
    }

    private function createShipment($row)
    {
        $sellerId = null;
        if (!empty($row['seller_company'])) {
            $seller = User::where('company_name', $row['seller_company'])
                          ->whereHas('roles', function ($q) {
                              $q->where('name', 'Seller');
                          })
                          ->first();
            if ($seller) {
                $sellerId = $seller->id;
            }
        }

        $driverId = null;
        if (!empty($row['driver'])) {
            $driver = User::where('name', $row['driver'])
                         ->whereHas('roles', function ($q) {
                             $q->where('name', 'Driver');
                         })
                         ->first();
            if ($driver) {
                $driverId = $driver->id;
            }
        }

        // طباعة البيانات للتأكد
        Log::info('Creating shipment with data:', [
            'receiver_name' => $row['receiver_name'],
            'receiver_city' => $row['receiver_city'],
            'sender_name' => $row['sender_name'] ?? null,
        ]);

        return Shipment::create([
            'tracking_number' => $row['tracking_number'] ?? $this->generateTrackingNumber(),
            'sender_name' => $row['sender_name'] ?? null,
            'sender_phone' => $row['sender_phone'] ?? null,
            'sender_address' => $row['sender_address'] ?? null,
            'sender_city' => $row['sender_city'] ?? null,
            'seller_id' => $sellerId,
            'receiver_name' => $row['receiver_name'],
            'receiver_phone' => $row['receiver_phone'],
            'receiver_address' => $row['receiver_address'],
            'receiver_city' => $row['receiver_city'],
            'weight' => $row['weight'] ?? null,
            'shipping_cost' => $row['shipping_cost'],
            'cod_amount' => $row['cod_amount'] ?? 0,
            'driver_id' => $driverId,
            'pickup_date' => !empty($row['pickup_date']) ? Carbon::parse($row['pickup_date']) : null,
            'expected_delivery_date' => !empty($row['expected_delivery_date']) ? Carbon::parse($row['expected_delivery_date']) : Carbon::now()->addDay(),
            'actual_delivery_date' => !empty($row['actual_delivery_date']) ? Carbon::parse($row['actual_delivery_date']) : null,
            'package_type' => $row['package_type'] ?? 'standard',
            'quantity' => $row['quantity'] ?? 1,
            'declared_value' => $row['declared_value'] ?? 0,
            'description' => $row['description'] ?? null,
            'notes' => $row['notes'] ?? null,
            'internal_notes' => $row['internal_notes'] ?? null,
        ]);
    }

    private function generateTrackingNumber()
    {
        do {
            $trackingNumber = 'DP-' . date('Y') . str_pad(mt_rand(1, 99999999), 7, '0', STR_PAD_LEFT);
        } while (Shipment::where('tracking_number', $trackingNumber)->exists());

        return $trackingNumber;
    }
}
