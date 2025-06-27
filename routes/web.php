<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BillOfLadingController;
use App\Filament\Pages\BulkScannerAssignment;
use App\Http\Controllers\WhatsAppController;


Route::get('/', function () {
    return view('welcome');
});

Route::get('admin/shipments/{shipment}/bill-of-lading', [BillOfLadingController::class, 'generateBillOfLading'])
    ->name('shipments.bill-of-lading');

Route::post('admin/shipments/bills-of-lading', [BillOfLadingController::class, 'generateMultipleBills'])
    ->name('shipments.bills-of-lading');

Route::get('/bill-of-lading/{shipment}', [BillOfLadingController::class, 'generateBillOfLading'])
    ->name('bill-of-lading.generate');

Route::middleware(['web', 'auth'])->group(function () {
    // Route للحصول على معلومات المنطقة والسعر
    Route::get('/api/zone-info/{zoneId}', function ($zoneId) {
        $zone = \App\Models\Zone::with(['governorate', 'city'])
            ->where('is_active', true) // إضافة فلترة للمناطق النشطة فقط
            ->find($zoneId);

        if (!$zone) {
            return response()->json(['error' => 'Zone not found or inactive'], 404);
        }

        return response()->json([
            'zone' => [
                'id' => $zone->id,
                'name' => $zone->zone_name,
                'shipping_cost' => $zone->shipping_cost,
                'express_cost' => $zone->express_cost,
                'same_day_cost' => $zone->same_day_cost,
                'cod_fee' => $zone->cod_fee,
                'estimated_delivery_days' => $zone->estimated_delivery_days,
                'governorate' => $zone->governorate->governorate_name_en,
                'city' => $zone->city->city_name_en ?? null,
            ]
        ]);
    });

    // API للحصول على كل المناطق النشطة
    Route::get('/api/active-zones', function() {
        $zones = \App\Models\Zone::with(['governorate', 'city'])
            ->where('is_active', true)
            ->get()
            ->map(function($zone) {
                return [
                    'id' => $zone->id,
                    'name' => $zone->zone_name,
                    'governorate' => $zone->governorate->governorate_name_en,
                    'city' => $zone->city->city_name_en,
                    'shipping_cost' => $zone->shipping_cost,
                    'express_cost' => $zone->express_cost,
                    'same_day_cost' => $zone->same_day_cost,
                    'cod_fee' => $zone->cod_fee,
                    'estimated_delivery_days' => $zone->estimated_delivery_days,
                ];
            });

        return response()->json(['zones' => $zones]);
    });

    // API للحصول على المناطق النشطة حسب المحافظة
    Route::get('/api/zones/governorate/{governorateId}', function($governorateId) {
        $zones = \App\Models\Zone::with(['city'])
            ->where('governorate_id', $governorateId)
            ->where('is_active', true)
            ->get()
            ->map(function($zone) {
                return [
                    'id' => $zone->id,
                    'name' => $zone->zone_name,
                    'city' => $zone->city->city_name_en,
                    'shipping_cost' => $zone->shipping_cost,
                    'express_cost' => $zone->express_cost,
                    'same_day_cost' => $zone->same_day_cost,
                    'cod_fee' => $zone->cod_fee,
                    'estimated_delivery_days' => $zone->estimated_delivery_days,
                ];
            });

        return response()->json(['zones' => $zones]);
    });

    // API للحصول على المناطق النشطة حسب المدينة
    Route::get('/api/zones/city/{cityId}', function($cityId) {
        $zones = \App\Models\Zone::where('city_id', $cityId)
            ->where('is_active', true)
            ->get()
            ->map(function($zone) {
                return [
                    'id' => $zone->id,
                    'name' => $zone->zone_name,
                    'shipping_cost' => $zone->shipping_cost,
                    'express_cost' => $zone->express_cost,
                    'same_day_cost' => $zone->same_day_cost,
                    'cod_fee' => $zone->cod_fee,
                    'estimated_delivery_days' => $zone->estimated_delivery_days,
                ];
            });

        return response()->json(['zones' => $zones]);
    });

    Route::post('/admin/bulk-scanner-assignment/check-tracking', function() {
        $page = new BulkScannerAssignment();
        $page->mount();
        return $page->checkTrackingNumber();
    })->name('admin.scanner.check-tracking');




    // Route::get('/whatsapp/test', [WhatsAppController::class, 'test']);
    // Route::post('/whatsapp/send', [WhatsAppController::class, 'sendMessage']);
    // Route::get('/whatsapp/test-direct', [WhatsAppController::class, 'testDirect']);
    // Route::get('/whatsapp/test-template', [WhatsAppController::class, 'testTemplate']);


});
