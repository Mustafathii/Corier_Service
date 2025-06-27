<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Models\User;
use App\Models\City;
use App\Models\Zone;
use App\Models\Governorate;
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
    // البحث عن البائع
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

    // البحث عن السائق
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

    // البحث عن المحافظة والمدينة والمنطقة
    $locationData = $this->resolveLocationData($row);

    // حساب تكلفة الشحن
    $shippingCost = $this->calculateShippingCost($row, $locationData);

    // طباعة البيانات للتأكد
    Log::info('Creating shipment with data:', [
        'receiver_name' => $row['receiver_name'],
        'receiver_city' => $row['receiver_city'],
        'governorate_id' => $locationData['governorate_id'] ?? null,
        'city_id' => $locationData['city_id'] ?? null,
        'zone_id' => $locationData['zone_id'] ?? null,
        'shipping_cost' => $shippingCost,
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

        // إضافة بيانات الموقع الجديدة
        'governorate_id' => $locationData['governorate_id'] ?? null,
        'city_id' => $locationData['city_id'] ?? null,
        'zone_id' => $locationData['zone_id'] ?? null,

        'weight' => $row['weight'] ?? null,
        'shipping_cost' => $shippingCost,
        'cod_amount' => $row['cod_amount'] ?? 0,
        'driver_id' => $driverId,
        'pickup_date' => !empty($row['pickup_date']) ? Carbon::parse($row['pickup_date']) : null,
        'expected_delivery_date' => !empty($row['expected_delivery_date'])
            ? Carbon::parse($row['expected_delivery_date'])
            : $this->calculateExpectedDeliveryDate($locationData),
        'actual_delivery_date' => !empty($row['actual_delivery_date']) ? Carbon::parse($row['actual_delivery_date']) : null,
        'package_type' => $row['package_type'] ?? 'standard',
        'quantity' => $row['quantity'] ?? 1,
        'declared_value' => $row['declared_value'] ?? 0,
        'description' => $row['description'] ?? null,
        'notes' => $row['notes'] ?? null,
        'internal_notes' => $row['internal_notes'] ?? null,
        'shipment_type' => $row['shipment_type'] ?? 'standard',
        'payment_method' => $row['payment_method'] ?? 'cod',
        'status' => $row['status'] ?? 'in_transit',
    ]);
}

/**
 * حل بيانات الموقع من النص
 */
private function resolveLocationData($row): array
{
    $governorateId = null;
    $cityId = null;
    $zoneId = null;

    // إذا كانت هناك أعمدة محددة للمحافظة والمدينة
    if (!empty($row['governorate_name']) || !empty($row['governorate_id'])) {
        $governorate = $this->findGovernorate($row);
        if ($governorate) {
            $governorateId = $governorate->id;
        }
    }

    if (!empty($row['city_name']) || !empty($row['city_id'])) {
        $city = $this->findCity($row, $governorateId);
        if ($city) {
            $cityId = $city->id;
            $governorateId = $city->governorate_id; // تأكد من المحافظة الصحيحة
        }
    }

    // إذا لم نجد المدينة، نحاول البحث في receiver_city
    if (!$cityId && !empty($row['receiver_city'])) {
        $city = $this->searchCityByName($row['receiver_city']);
        if ($city) {
            $cityId = $city->id;
            $governorateId = $city->governorate_id;
        }
    }

    // البحث عن Zone
    if ($cityId) {
        $zone = Zone::where('city_id', $cityId)
                   ->where('is_active', true)
                   ->first();
        if ($zone) {
            $zoneId = $zone->id;
        }
    }

    return [
        'governorate_id' => $governorateId,
        'city_id' => $cityId,
        'zone_id' => $zoneId
    ];
}

/**
 * البحث عن المحافظة
 */
private function findGovernorate($row)
{
    // إذا كان هناك ID محدد
    if (!empty($row['governorate_id'])) {
        return Governorate::find($row['governorate_id']);
    }

    // البحث بالاسم
    if (!empty($row['governorate_name'])) {
        $name = $this->cleanArabicText($row['governorate_name']);
        return Governorate::where('governorate_name_ar', $name)
                        ->orWhere('governorate_name_en', $name)
                        ->first();
    }

    return null;
}

/**
 * البحث عن المدينة
 */
private function findCity($row, $governorateId = null)
{
    // إذا كان هناك ID محدد
    if (!empty($row['city_id'])) {
        return City::find($row['city_id']);
    }

    // البحث بالاسم
    if (!empty($row['city_name'])) {
        $name = $this->cleanArabicText($row['city_name']);
        $query = City::where(function ($q) use ($name) {
            $q->where('city_name_ar', $name)
            ->orWhere('city_name_en', $name);
        });

        if ($governorateId) {
            $query->where('governorate_id', $governorateId);
        }

        return $query->first();
    }

    return null;
}

/**
 * البحث عن المدينة بالاسم في كل المحافظات
 */
private function searchCityByName($cityName)
{
    $cleanName = $this->cleanArabicText($cityName);

    return City::where('city_name_ar', 'like', "%{$cleanName}%")
            ->orWhere('city_name_en', 'like', "%{$cleanName}%")
            ->with('governorate')
                    ->first();
}

/**
 * حساب تكلفة الشحن
 */
private function calculateShippingCost($row, $locationData): float
{
    // إذا كان السعر محدد في الملف، استخدمه
    if (!empty($row['shipping_cost']) && is_numeric($row['shipping_cost'])) {
        return (float) $row['shipping_cost'];
    }

    // إذا كان لدينا zone، احسب السعر منها
    if (!empty($locationData['zone_id'])) {
        $zone = Zone::find($locationData['zone_id']);
        if ($zone) {
            $shipmentType = $row['shipment_type'] ?? 'standard';
            return $zone->getCostByType($shipmentType);
        }
    }

    // سعر افتراضي حسب المحافظة
    if (!empty($locationData['governorate_id'])) {
        return $this->getDefaultPriceByGovernorate($locationData['governorate_id']);
    }

    // سعر افتراضي عام
    return 15.00;
}

/**
 * حساب تاريخ التوصيل المتوقع
 */
private function calculateExpectedDeliveryDate($locationData)
{
    $days = 1; // افتراضي

    if (!empty($locationData['zone_id'])) {
        $zone = Zone::find($locationData['zone_id']);
        if ($zone) {
            $days = $zone->estimated_delivery_days;
        }
    } elseif (!empty($locationData['governorate_id'])) {
        // تحديد الأيام حسب المحافظة
        $days = match($locationData['governorate_id']) {
            1, 2, 3 => 1, // القاهرة الكبرى
            4, 8, 10, 12, 20 => 2, // المحافظات القريبة
            default => 3 // باقي المحافظات
        };
    }

    return Carbon::now()->addDays($days);
}

/**
 * الحصول على السعر الافتراضي حسب المحافظة
 */
private function getDefaultPriceByGovernorate(int $governorateId): float
{
    return match($governorateId) {
        1, 2, 3 => 15.00, // القاهرة، الجيزة، الإسكندرية
        4, 8, 10, 12, 20 => 20.00, // المحافظات القريبة
        default => 25.00 // باقي المحافظات
    };
}
}
