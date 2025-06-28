<?php

namespace App\Imports;

use App\Models\Shipment;
use App\Models\User;
use App\Models\Governorate;
use App\Models\City;
use App\Models\Zone;
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

        // 🎯 البحث عن الـ Seller
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

        // 🎯 البحث عن الـ Driver
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

        // 🗺️ النظام الجديد الذكي المحسن - البحث عن المحافظة والمدينة والمنطقة
        $governorateId = null;
        $cityId = null;
        $zoneId = null;
        $calculatedShippingCost = null;

        // البحث الذكي عن المحافظة
        if (isset($row['governorate']) && !empty($row['governorate'])) {
            $governorateInput = trim($row['governorate']);
            $normalizedGovInput = $this->normalizeArabicText($governorateInput);

            $governorate = Governorate::where('is_active', true)
                ->where(function($q) use ($governorateInput, $normalizedGovInput) {
                    // البحث المباشر
                    $q->where('governorate_name_ar', 'LIKE', '%' . $governorateInput . '%')
                      ->orWhere('governorate_name_en', 'LIKE', '%' . $governorateInput . '%')
                      // البحث المطبع (normalized)
                      ->orWhereRaw('REPLACE(REPLACE(REPLACE(governorate_name_ar, "أ", "ا"), "إ", "ا"), "آ", "ا") LIKE ?', ['%' . $normalizedGovInput . '%'])
                      // البحث العكسي
                      ->orWhere('governorate_name_ar', 'LIKE', '%اسكندرية%')
                        ->when(stripos($governorateInput, 'اسكندرية') !== false, function($query) {
                            return $query->orWhere('governorate_name_ar', 'LIKE', '%الأسكندرية%');
                        })
                      // البحث بالإنجليزية
                      ->orWhere('governorate_name_en', 'LIKE', '%' . strtolower($governorateInput) . '%');
                })
                ->first();

            if ($governorate) {
                $governorateId = $governorate->id;
                Log::info("✅ Smart match found governorate: {$governorate->governorate_name_ar} for input: {$governorateInput}");
            } else {
                Log::warning("❌ Could not find governorate for: {$governorateInput}");
            }
        }

        // البحث الذكي عن المدينة
        if (isset($row['city']) && !empty($row['city'])) {
            $cityInput = trim($row['city']);
            $normalizedCityInput = $this->normalizeArabicText($cityInput);

            // إذا لم نجد محافظة، حاول استخراجها من اسم المدينة
            if (!$governorateId) {
                $extractedGov = $this->extractGovernorateFromCombined($cityInput);
                if ($extractedGov) {
                    $governorate = Governorate::where('is_active', true)
                        ->where(function($q) use ($extractedGov) {
                            $normalizedExtracted = $this->normalizeArabicText($extractedGov);
                            $q->where('governorate_name_ar', 'LIKE', '%' . $extractedGov . '%')
                              ->orWhere('governorate_name_en', 'LIKE', '%' . $extractedGov . '%')
                              ->orWhereRaw('REPLACE(REPLACE(REPLACE(governorate_name_ar, "أ", "ا"), "إ", "ا"), "آ", "ا") LIKE ?', ['%' . $normalizedExtracted . '%']);
                        })
                        ->first();

                    if ($governorate) {
                        $governorateId = $governorate->id;
                        $cityInput = $this->extractCityFromCombined($cityInput);
                        $normalizedCityInput = $this->normalizeArabicText($cityInput);
                        Log::info("🔄 Auto-extracted governorate: {$governorate->governorate_name_ar} from city input: {$row['city']}");
                    }
                }
            }

            if ($governorateId) {
                $city = City::where('governorate_id', $governorateId)
                    ->where('is_active', true)
                    ->where(function($q) use ($cityInput, $normalizedCityInput) {
                        // البحث المباشر
                        $q->where('city_name_ar', 'LIKE', '%' . $cityInput . '%')
                          ->orWhere('city_name_en', 'LIKE', '%' . $cityInput . '%')
                          // البحث المطبع
                          ->orWhereRaw('REPLACE(REPLACE(REPLACE(city_name_ar, "ى", "ي"), "ة", "ه"), "ت", "ه") LIKE ?', ['%' . $normalizedCityInput . '%'])
                          // حالات خاصة
                          ->orWhere('city_name_ar', 'LIKE', '%الدقى%')
                            ->when(stripos($cityInput, 'دقي') !== false, function($query) {
                                return $query->orWhere('city_name_ar', 'LIKE', '%الدقى%');
                            })
                          // البحث بالإنجليزية
                          ->orWhere('city_name_en', 'LIKE', '%' . strtolower($cityInput) . '%');
                    })
                    ->first();

                if ($city) {
                    $cityId = $city->id;
                    Log::info("✅ Smart match found city: {$city->city_name_ar} (ID: {$city->id}) for input: {$cityInput}");

                    // البحث عن المنطقة النشطة في هذه المدينة
                    $zone = Zone::where('city_id', $cityId)
                        ->where('is_active', true)
                        ->first();

                    if ($zone) {
                        $zoneId = $zone->id;
                        Log::info("✅ Found active zone: {$zone->zone_name} for city ID: {$cityId}");

                        // حساب التكلفة بناءً على نوع الشحنة
                        $shipmentType = isset($row['shipment_type']) && !empty($row['shipment_type'])
                            ? strtolower(trim($row['shipment_type']))
                            : 'standard';

                        // التأكد من أن نوع الشحنة صحيح
                        if (!in_array($shipmentType, ['standard', 'express', 'same_day'])) {
                            $shipmentType = 'standard';
                        }

                        // حساب التكلفة من الـ zone
                        if (method_exists($zone, 'getCostByType')) {
                            $calculatedShippingCost = $zone->getCostByType($shipmentType);
                        } else {
                            // Fallback manual calculation
                            switch ($shipmentType) {
                                case 'express':
                                    $calculatedShippingCost = $zone->express_cost ?? ($zone->shipping_cost * 1.5);
                                    break;
                                case 'same_day':
                                    $calculatedShippingCost = $zone->same_day_cost ?? ($zone->shipping_cost * 2);
                                    break;
                                default:
                                    $calculatedShippingCost = $zone->shipping_cost;
                            }
                        }

                        Log::info("💰 Calculated shipping cost: {$calculatedShippingCost} EGP for type: {$shipmentType}");
                    } else {
                        Log::warning("❌ No active zone found for city ID: {$cityId} ({$city->city_name_ar})");
                    }
                } else {
                    Log::warning("❌ Could not find city: {$cityInput} in governorate ID: {$governorateId}");
                }
            } else {
                Log::warning("❌ No governorate found, cannot search for city: {$cityInput}");
            }
        }

        // تحديد التكلفة النهائية
        $finalShippingCost = $calculatedShippingCost ?? ($row['shipping_cost'] ?? 15.00);

        // تحديد تاريخ التسليم المتوقع
        $expectedDeliveryDate = Carbon::now()->addDay(); // القيمة الافتراضية
        if ($zoneId) {
            $zone = Zone::find($zoneId);
            if ($zone && $zone->estimated_delivery_days) {
                $expectedDeliveryDate = Carbon::now()->addDays($zone->estimated_delivery_days);
            }
        } elseif (isset($row['expected_delivery_date']) && !empty($row['expected_delivery_date'])) {
            $expectedDeliveryDate = Carbon::parse($row['expected_delivery_date']);
        }

        // تحديد نوع الشحنة مع معالجة القيم العربية
        $shipmentType = 'standard';
        if (isset($row['shipment_type']) && !empty($row['shipment_type'])) {
            $type = strtolower(trim($row['shipment_type']));

            // معالجة القيم المختلفة
            if (in_array($type, ['standard', 'express', 'same_day'])) {
                $shipmentType = $type;
            } else {
                // إذا كانت القيمة مش من القيم المطلوبة، خليها standard
                $shipmentType = 'standard';
                Log::info("Invalid shipment_type '{$row['shipment_type']}' converted to 'standard'");
            }
        }

        // تحديد طريقة الدفع مع معالجة القيم بحالات مختلفة
        $paymentMethod = 'cod';
        if (isset($row['payment_method']) && !empty($row['payment_method'])) {
            $method = strtolower(trim($row['payment_method']));

            // معالجة القيم المختلفة
            if ($method === 'cod' || $method === 'cash on delivery') {
                $paymentMethod = 'cod';
            } elseif ($method === 'prepaid' || $method === 'paid') {
                $paymentMethod = 'prepaid';
            } elseif ($method === 'electronic_wallet' || $method === 'wallet' || $method === 'e-wallet') {
                $paymentMethod = 'electronic_wallet';
            } else {
                // القيمة الافتراضية
                $paymentMethod = 'cod';
                Log::info("Invalid payment_method '{$row['payment_method']}' converted to 'cod'");
            }
        }

        // تحديد الحالة
        $status = 'in_transit';
        if (isset($row['status']) && !empty($row['status'])) {
            $statusValue = strtolower(str_replace(' ', '_', trim($row['status'])));
            $validStatuses = ['pending', 'picked_up', 'in_transit', 'out_for_delivery', 'delivered', 'failed_delivery', 'returned', 'canceled'];
            if (in_array($statusValue, $validStatuses)) {
                $status = $statusValue;
            }
        }

        $shipment = new Shipment([
            // البيانات الأساسية
            'tracking_number' => $row['tracking_number'] ?? $this->generateTrackingNumber(),
            'status' => $status,

            // بيانات المرسل
            'sender_name' => !empty($row['sender_name']) ? $row['sender_name'] : null,
            'sender_phone' => !empty($row['sender_phone']) ? $row['sender_phone'] : null, // عادي بدون cast
            'sender_address' => !empty($row['sender_address']) ? $row['sender_address'] : null,
            'sender_city' => !empty($row['sender_city']) ? $row['sender_city'] : null,
            'seller_id' => $sellerId,

            // بيانات المستقبل مع معالجة receiver_city الفاضي
            'receiver_name' => $row['receiver_name'],
            'receiver_phone' => $row['receiver_phone'] ?? '', // عادي بدون cast
            'receiver_address' => $row['receiver_address'],
            'receiver_city' => !empty($row['receiver_city'])
                ? $row['receiver_city']
                : (!empty($row['city']) ? $row['city'] : 'Unknown'), // استخدم city لو receiver_city فاضي

            // 🗺️ النظام الجديد - المواقع
            'governorate_id' => $governorateId,
            'city_id' => $cityId,
            'zone_id' => $zoneId,

            // بيانات الشحن والتسعير
            'shipment_type' => $shipmentType,
            'weight' => $row['weight'] ?? 1,
            'shipping_cost' => $finalShippingCost,
            'payment_method' => $paymentMethod,
            'cod_amount' => ($paymentMethod === 'cod') ? ($row['cod_amount'] ?? 0) : 0,

            // بيانات التوقيت
            'pickup_date' => isset($row['pickup_date']) && !empty($row['pickup_date'])
                ? Carbon::parse($row['pickup_date']) : null,
            'expected_delivery_date' => $expectedDeliveryDate,
            'actual_delivery_date' => isset($row['actual_delivery_date']) && !empty($row['actual_delivery_date'])
                ? Carbon::parse($row['actual_delivery_date']) : null,

            // بيانات الطرد
            'package_type' => $row['package_type'] ?? 'Standard',
            'quantity' => $row['quantity'] ?? 1,
            'declared_value' => $row['declared_value'] ?? 0,
            'description' => $row['description'] ?? null,

            // ملاحظات
            'notes' => $row['notes'] ?? null,
            'internal_notes' => $row['internal_notes'] ?? null,

            // السائق
            'driver_id' => $driverId,
        ]);

        Log::info('Final shipment data to be saved:', [
            'tracking_number' => $shipment->tracking_number,
            'governorate_id' => $shipment->governorate_id,
            'city_id' => $shipment->city_id,
            'zone_id' => $shipment->zone_id,
            'shipment_type' => $shipment->shipment_type,
            'shipping_cost' => $shipment->shipping_cost,
            'calculated_cost' => $calculatedShippingCost,
            'original_cost' => $row['shipping_cost'] ?? 'N/A'
        ]);

        return $shipment;
    }

    /**
     * تطبيع النصوص العربية للبحث الذكي
     */
    private function normalizeArabicText($text)
    {
        if (empty($text)) {
            return $text;
        }

        // تحويل الهمزات المختلفة لألف عادية
        $text = str_replace(['أ', 'إ', 'آ'], 'ا', $text);

        // تحويل الياء المختلفة
        $text = str_replace(['ى', 'ي'], 'ي', $text);

        // تحويل التاء المربوطة والمفتوحة
        $text = str_replace(['ة', 'ت'], 'ه', $text);

        // إزالة المسافات الزائدة
        $text = trim($text);

        return $text;
    }

    /**
     * تطبيع النصوص الإنجليزية للبحث الذكي
     */
    private function normalizeEnglishText($text)
    {
        if (empty($text)) {
            return $text;
        }

        // تحويل لأحرف صغيرة
        $text = strtolower($text);

        // إزالة المسافات الزائدة
        $text = trim($text);

        return $text;
    }

    /**
     * استخراج اسم المحافظة من نص مدمج مثل "اسكندرية العصافرة"
     */
    private function extractGovernorateFromCombined($input)
    {
        $input = trim($input);

        // قائمة بأسماء المحافظات المشهورة
        $governorateVariations = [
            'القاهرة' => ['قاهرة', 'cairo'],
            'الجيزة' => ['جيزة', 'giza'],
            'الأسكندرية' => ['اسكندرية', 'إسكندرية', 'سكندرية', 'alexandria', 'alex'],
            'الدقهلية' => ['دقهلية', 'dakahlia'],
            'الغربية' => ['غربية', 'gharbiya'],
            'الشرقية' => ['شرقية', 'sharkia'],
            'المنوفية' => ['منوفية', 'menofia'],
            'البحيرة' => ['بحيرة', 'beheira'],
            'كفر الشيخ' => ['كفر الشيخ', 'kafr el sheikh'],
            'دمياط' => ['دمياط', 'damietta'],
            'بورسعيد' => ['بورسعيد', 'port said'],
            'الإسماعلية' => ['اسماعيلية', 'إسماعيلية', 'ismailia'],
            'السويس' => ['سويس', 'suez'],
            'جنوب سيناء' => ['جنوب سيناء', 'south sinai'],
            'شمال سيناء' => ['شمال سيناء', 'north sinai'],
            'البحر الأحمر' => ['بحر احمر', 'red sea'],
            'الأقصر' => ['اقصر', 'luxor'],
            'اسوان' => ['أسوان', 'aswan'],
            'قنا' => ['قنا', 'qena'],
            'سوهاج' => ['سوهاج', 'sohag'],
            'اسيوط' => ['أسيوط', 'assiut'],
            'المنيا' => ['منيا', 'minya'],
            'بني سويف' => ['بني سويف', 'beni suef'],
            'الفيوم' => ['فيوم', 'fayoum'],
            'مطروح' => ['مطروح', 'matrouh'],
            'الوادي الجديد' => ['وادي جديد', 'new valley'],
            'القليوبية' => ['قليوبية', 'qaliubiya'],
            'جنوب سيناء' => ['جنوب سيناء', 'south sinai', 'جنوبسيناء'],
            'شمال سيناء' => ['شمال سيناء', 'north sinai', 'شمالسيناء'],
            'البحر الأحمر' => ['بحر احمر', 'البحرالاحمر', 'red sea'],
            'الأقصر' => ['اقصر', 'الاقصر', 'luxor'],
            'اسوان' => ['أسوان', 'اسوان', 'aswan'],
            'قنا' => ['قنا', 'qena'],
            'سوهاج' => ['سوهاج', 'sohag'],
            'اسيوط' => ['أسيوط', 'اسيوط', 'assiut'],
            'المنيا' => ['منيا', 'المنيا', 'minya'],
            'بني سويف' => ['بني سويف', 'بنيسويف', 'beni suef'],
            'الفيوم' => ['فيوم', 'الفيوم', 'fayoum'],
            'مطروح' => ['مطروح', 'matrouh'],
            'الوادي الجديد' => ['وادي جديد', 'الواديالجديد', 'new valley'],
            'القليوبية' => ['قليوبية', 'القليوبيه', 'qaliubiya']
        ];

        // البحث عن تطابق
        foreach ($governorateVariations as $official => $variations) {
            foreach ($variations as $variation) {
                if (stripos($input, $variation) !== false) {
                    Log::info("🎯 Matched governorate '{$official}' from input '{$input}' using variation '{$variation}'");
                    return $official;
                }
            }
        }

        // محاولة أخيرة - أول كلمة
        $words = explode(' ', $input);
        if (count($words) > 1) {
            $firstWord = $words[0];
            // جرب البحث بأول كلمة
            foreach ($governorateVariations as $official => $variations) {
                foreach ($variations as $variation) {
                    if (stripos($firstWord, $variation) !== false || stripos($variation, $firstWord) !== false) {
                        Log::info("🎯 Matched governorate '{$official}' from first word '{$firstWord}'");
                        return $official;
                    }
                }
            }
        }

        Log::warning("❓ Could not extract governorate from: {$input}");
        return null;
    }

    /**
     * استخراج اسم المدينة من نص مدمج
     */
    private function extractCityFromCombined($input)
    {
        $input = trim($input);

        // قائمة أسماء المحافظات للإزالة
        $governorateNames = [
            'القاهرة', 'الجيزة', 'الأسكندرية', 'اسكندرية', 'إسكندرية',
            'الدقهلية', 'الغربية', 'الشرقية', 'المنوفية', 'البحيرة',
            'كفر الشيخ', 'دمياط', 'بورسعيد', 'الإسماعلية', 'السويس',
            'جنوب سيناء', 'شمال سيناء', 'البحر الأحمر', 'الأقصر',
            'اسوان', 'قنا', 'سوهاج', 'اسيوط', 'المنيا', 'بني سويف',
            'الفيوم', 'مطروح', 'الوادي الجديد', 'القليوبية'
        ];

        // محاولة إزالة اسم المحافظة من بداية النص
        foreach ($governorateNames as $govName) {
            if (stripos($input, $govName) === 0) {
                $cityPart = trim(str_ireplace($govName, '', $input));
                if (!empty($cityPart)) {
                    Log::info("🏙️ Extracted city '{$cityPart}' after removing governorate '{$govName}' from '{$input}'");
                    return $cityPart;
                }
            }
        }

        // إذا لم نجد محافظة في البداية، خذ آخر كلمة
        $words = explode(' ', $input);
        if (count($words) > 1) {
            $lastWord = end($words);
            Log::info("🏙️ Using last word '{$lastWord}' as city from input '{$input}'");
            return $lastWord;
        }

        Log::info("🏙️ Using full input '{$input}' as city");
        return $input;
    }
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
            // البيانات الأساسية
            'tracking_number' => ['nullable', 'string', 'max:255', 'unique:shipments,tracking_number'],
            'status' => ['nullable', 'string'],

            // بيانات المرسل
            'sender_name' => ['nullable', 'string', 'max:255'],
            'sender_phone' => ['nullable', 'string', 'max:20'],
            'sender_address' => ['nullable', 'string'],
            'sender_city' => ['nullable', 'string', 'max:255'],
            'seller_company' => ['nullable', 'string'],

            // بيانات المستقبل - تعديل receiver_phone ليكون numeric
            'receiver_name' => ['required', 'string', 'max:255'],
            'receiver_phone' => ['required', 'numeric'], // عدلناها لـ numeric
            'receiver_address' => ['required', 'string'],
            'receiver_city' => ['nullable', 'string', 'max:255'], // مش required عشان نقدر نعالجه

            // 🗺️ النظام الجديد - المواقع (اختيارية لأنها هتتحدد تلقائياً)
            'governorate' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'zone' => ['nullable', 'string', 'max:255'],

            // بيانات الشحن والتسعير - تخفيف القيود
            'shipment_type' => ['nullable', 'string'], // إزالة in validation
            'weight' => ['nullable', 'numeric', 'min:0.01'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'string'], // إزالة in validation
            'cod_amount' => ['nullable', 'numeric', 'min:0'],

            // بيانات التوقيت
            'pickup_date' => ['nullable', 'date'],
            'expected_delivery_date' => ['nullable', 'date'],
            'actual_delivery_date' => ['nullable', 'date'],

            // بيانات الطرد
            'package_type' => ['nullable', 'string', 'max:255'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'declared_value' => ['nullable', 'string'], // string عشان يقبل فاضي
            'description' => ['nullable', 'string'],

            // ملاحظات
            'notes' => ['nullable', 'string'],
            'internal_notes' => ['nullable', 'string'],

            // السائق
            'driver' => ['nullable', 'string'],
        ];
    }

    public function chunkSize(): int
    {
        return 500; // قللت الرقم شوية عشان المعالجة الجديدة
    }
}
