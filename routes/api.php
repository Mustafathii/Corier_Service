<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\LocationController;

Route::prefix('locations')->group(function () {
    // الحصول على جميع المحافظات
    Route::get('/governorates', [LocationController::class, 'getGovernorates']);

    // الحصول على المدن حسب المحافظة
    Route::get('/governorates/{governorateId}/cities', [LocationController::class, 'getCitiesByGovernorate']);

    // الحصول على المناطق حسب المدينة
    Route::get('/cities/{cityId}/zones', [LocationController::class, 'getZonesByCity']);

    // الحصول على تكلفة الشحن
    Route::post('/shipping-cost', [LocationController::class, 'getShippingCost']);

    // البحث في المواقع
    Route::get('/search', [LocationController::class, 'searchLocations']);

    // الحصول على الهيكل الكامل للمواقع
    Route::get('/hierarchy', [LocationController::class, 'getLocationHierarchy']);
});


Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
