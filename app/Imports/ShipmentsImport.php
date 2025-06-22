<?php

namespace App\Imports;

use App\Models\Shipment;
use App\Models\User;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use PhpOffice\PhpSpreadsheet\Shared\StringHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ShipmentsImport implements ToModel, WithHeadingRow, WithValidation, WithChunkReading
{
    public function __construct()
    {
        // تعيين encoding للمكتبة
        StringHelper::setDecimalSeparator('.');
        StringHelper::setThousandsSeparator(',');
    }

    public function model(array $row)
    {
        // طباعة البيانات للتأكد من المحتوى
        Log::info('Raw row data:', $row);

        // معالجة خاصة للنصوص العربية
        foreach ($row as $key => $value) {
            if (is_string($value)) {
                // تسجيل القيمة الأصلية
                Log::info("Original value for {$key}: " . bin2hex($value));

                // تنظيف وتحويل encoding
                $row[$key] = $this->fixArabicEncoding($value);

                // تسجيل القيمة بعد التحويل
                Log::info("Fixed value for {$key}: " . $row[$key]);
            }
        }

        $sellerId = null;
        if (isset($row['seller_company']) && !empty($row['seller_company'])) {
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
        if (isset($row['driver']) && !empty($row['driver'])) {
            $driver = User::where('name', $row['driver'])
                         ->whereHas('roles', function ($q) {
                             $q->where('name', 'Driver');
                         })
                         ->first();
            if ($driver) {
                $driverId = $driver->id;
            }
        }

        $shipment = new Shipment([
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
            'weight' => $row['weight'],
            'shipping_cost' => $row['shipping_cost'],
            'cod_amount' => $row['cod_amount'] ?? 0,
            'driver_id' => $driverId,
            'pickup_date' => isset($row['pickup_date']) ? Carbon::parse($row['pickup_date']) : null,
            'expected_delivery_date' => isset($row['expected_delivery_date']) ? Carbon::parse($row['expected_delivery_date']) : Carbon::now()->addDay(),
            'actual_delivery_date' => isset($row['actual_delivery_date']) ? Carbon::parse($row['actual_delivery_date']) : null,
            'package_type' => $row['package_type'] ?? 'standard',
            'quantity' => $row['quantity'] ?? 1,
            'declared_value' => $row['declared_value'] ?? 0,
            'description' => $row['description'] ?? null,
            'notes' => $row['notes'] ?? null,
            'internal_notes' => $row['internal_notes'] ?? null,
        ]);

        Log::info('Shipment data to be saved:', $shipment->toArray());

        return $shipment;
    }

    /**
     * إصلاح encoding للنصوص العربية
     */
    private function fixArabicEncoding($text)
    {
        if (empty($text)) {
            return $text;
        }

        // إزالة BOM
        $text = str_replace(["\xEF\xBB\xBF", "\xFF\xFE", "\xFE\xFF"], '', $text);

        // تنظيف المسافات
        $text = trim($text);

        // إذا كان النص يحتوي على أحرف غريبة، جرب تحويلات مختلفة
        if (preg_match('/[\x80-\xFF]/', $text) && !preg_match('/[\x{0600}-\x{06FF}]/u', $text)) {
            // جرب تحويلات encoding مختلفة
            $encodings = [
                'Windows-1256',
                'ISO-8859-6',
                'UTF-8',
                'CP1256',
                'ISO-8859-1'
            ];

            foreach ($encodings as $encoding) {
                try {
                    $converted = mb_convert_encoding($text, 'UTF-8', $encoding);
                    // تحقق إذا كان التحويل ينتج نص عربي صحيح
                    if (preg_match('/[\x{0600}-\x{06FF}]/u', $converted)) {
                        return $converted;
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
        }

        // إذا كان النص يبدو أنه UTF-8 مُعطل، جرب إصلاحه
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        }

        return $text;
    }

    private function generateTrackingNumber()
    {
        do {
            $trackingNumber = 'DP-' . date('Y') . str_pad(mt_rand(1, 99999999), 7, '0', STR_PAD_LEFT);
        } while (Shipment::where('tracking_number', $trackingNumber)->exists());

        return $trackingNumber;
    }

    public function rules(): array
    {
        return [
            'tracking_number' => ['nullable', 'string', 'max:255', 'unique:shipments,tracking_number'],
            'sender_name' => ['nullable', 'string', 'max:255'],
            'sender_phone' => ['nullable', 'numeric'],
            'sender_address' => ['nullable', 'string'],
            'sender_city' => ['nullable', 'string', 'max:255'],
            'seller_company' => ['nullable', 'string'],
            'receiver_name' => ['required', 'string', 'max:255'],
            'receiver_phone' => ['required', 'numeric'],
            'receiver_address' => ['required', 'string'],
            'receiver_city' => ['required', 'string', 'max:255'],
            'weight' => ['nullable','numeric', 'min:0.01'],
            'shipping_cost' => ['required', 'numeric', 'min:0'],
            'cod_amount' => ['nullable', 'numeric', 'min:0'],
            'driver' => ['nullable', 'string'],
            'expected_delivery_date' => ['nullable', 'date'],
            'actual_delivery_date' => ['nullable', 'date'],
            'pickup_date' => ['nullable', 'date'],
            'package_type' => ['nullable', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1'],
            'declared_value' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'internal_notes' => ['nullable', 'string'],
        ];
    }

    public function chunkSize(): int
    {
        return 1000;
    }
}
