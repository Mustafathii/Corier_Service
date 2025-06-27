<?php
// app/Http/Controllers/Api/LocationController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Governorate;
use App\Models\City;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LocationController extends Controller
{
    /**
     * Get all governorates
     */
    public function getGovernorates(): JsonResponse
    {
        $governorates = Governorate::where('is_active', true)
            ->select('id', 'governorate_name_ar', 'governorate_name_en')
            ->orderBy('governorate_name_en')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $governorates->map(function ($gov) {
                return [
                    'id' => $gov->id,
                    'name_ar' => $gov->governorate_name_ar,
                    'name_en' => $gov->governorate_name_en,
                    'display_name' => $gov->display_name
                ];
            })
        ]);
    }

    /**
     * Get cities by governorate
     */
    public function getCitiesByGovernorate(int $governorateId): JsonResponse
    {
        $cities = City::where('governorate_id', $governorateId)
            ->where('is_active', true)
            ->with('governorate:id,governorate_name_ar,governorate_name_en')
            ->select('id', 'governorate_id', 'city_name_ar', 'city_name_en')
            ->orderBy('city_name_en')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $cities->map(function ($city) {
                return [
                    'id' => $city->id,
                    'governorate_id' => $city->governorate_id,
                    'name_ar' => $city->city_name_ar,
                    'name_en' => $city->city_name_en,
                    'display_name' => $city->display_name,
                    'full_name' => $city->full_name
                ];
            })
        ]);
    }

    /**
     * Get zones by city
     */
    public function getZonesByCity(int $cityId): JsonResponse
    {
        $zones = Zone::where('city_id', $cityId)
            ->where('is_active', true)
            ->with(['governorate:id,governorate_name_en', 'city:id,city_name_en'])
            ->orderBy('zone_name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $zones->map(function ($zone) {
                return [
                    'id' => $zone->id,
                    'zone_name' => $zone->zone_name,
                    'shipping_cost' => $zone->shipping_cost,
                    'express_cost' => $zone->express_cost,
                    'same_day_cost' => $zone->same_day_cost,
                    'cod_fee' => $zone->cod_fee,
                    'estimated_delivery_days' => $zone->estimated_delivery_days,
                    'full_location' => $zone->full_location
                ];
            })
        ]);
    }

    /**
     * Get shipping cost for specific zone and shipment type
     */
    public function getShippingCost(Request $request): JsonResponse
    {
        $request->validate([
            'zone_id' => 'required|exists:zones,id',
            'shipment_type' => 'required|in:standard,express,same_day'
        ]);

        $zone = Zone::find($request->zone_id);

        if (!$zone || !$zone->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Zone not found or inactive'
            ], 404);
        }

        $cost = $zone->getCostByType($request->shipment_type);
        $estimatedDelivery = now()->addDays($zone->estimated_delivery_days);

        return response()->json([
            'success' => true,
            'data' => [
                'zone_id' => $zone->id,
                'zone_name' => $zone->zone_name,
                'shipment_type' => $request->shipment_type,
                'shipping_cost' => $cost,
                'cod_fee' => $zone->cod_fee,
                'estimated_delivery_days' => $zone->estimated_delivery_days,
                'estimated_delivery_date' => $estimatedDelivery->format('Y-m-d'),
                'zone_info' => [
                    'standard_cost' => $zone->shipping_cost,
                    'express_cost' => $zone->express_cost,
                    'same_day_cost' => $zone->same_day_cost
                ]
            ]
        ]);
    }

    /**
     * Search locations (governorates and cities)
     */
    public function searchLocations(Request $request): JsonResponse
    {
        $query = $request->get('q', '');

        if (strlen($query) < 2) {
            return response()->json([
                'success' => false,
                'message' => 'Query must be at least 2 characters'
            ], 400);
        }

        // Search governorates
        $governorates = Governorate::where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('governorate_name_ar', 'like', "%{$query}%")
                  ->orWhere('governorate_name_en', 'like', "%{$query}%");
            })
            ->get(['id', 'governorate_name_ar', 'governorate_name_en']);

        // Search cities
        $cities = City::where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('city_name_ar', 'like', "%{$query}%")
                  ->orWhere('city_name_en', 'like', "%{$query}%");
            })
            ->with('governorate:id,governorate_name_ar,governorate_name_en')
            ->get(['id', 'governorate_id', 'city_name_ar', 'city_name_en']);

        return response()->json([
            'success' => true,
            'data' => [
                'governorates' => $governorates->map(function ($gov) {
                    return [
                        'type' => 'governorate',
                        'id' => $gov->id,
                        'name_ar' => $gov->governorate_name_ar,
                        'name_en' => $gov->governorate_name_en,
                        'display_name' => $gov->display_name
                    ];
                }),
                'cities' => $cities->map(function ($city) {
                    return [
                        'type' => 'city',
                        'id' => $city->id,
                        'governorate_id' => $city->governorate_id,
                        'name_ar' => $city->city_name_ar,
                        'name_en' => $city->city_name_en,
                        'display_name' => $city->display_name,
                        'full_name' => $city->full_name
                    ];
                })
            ]
        ]);
    }

    /**
     * Get complete location hierarchy
     */
    public function getLocationHierarchy(): JsonResponse
    {
        $governorates = Governorate::where('is_active', true)
            ->with(['cities' => function ($query) {
                $query->where('is_active', true)
                      ->with(['zones' => function ($q) {
                          $q->where('is_active', true);
                      }]);
            }])
            ->orderBy('governorate_name_en')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $governorates->map(function ($governorate) {
                return [
                    'id' => $governorate->id,
                    'name_ar' => $governorate->governorate_name_ar,
                    'name_en' => $governorate->governorate_name_en,
                    'cities' => $governorate->cities->map(function ($city) {
                        return [
                            'id' => $city->id,
                            'name_ar' => $city->city_name_ar,
                            'name_en' => $city->city_name_en,
                            'zones' => $city->zones->map(function ($zone) {
                                return [
                                    'id' => $zone->id,
                                    'name' => $zone->zone_name,
                                    'shipping_cost' => $zone->shipping_cost,
                                    'express_cost' => $zone->express_cost,
                                    'same_day_cost' => $zone->same_day_cost,
                                    'estimated_delivery_days' => $zone->estimated_delivery_days
                                ];
                            })
                        ];
                    })
                ];
            })
        ]);
    }
}
