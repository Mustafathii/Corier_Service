{{-- resources/views/filament/components/zone-pricing-info.blade.php --}}

<div class="bg-gray-50 rounded-lg p-4 border">
    <div class="flex items-center space-x-2 mb-3">
        <div class="w-2 h-2 bg-blue-500 rounded-full"></div>
        <h4 class="text-sm font-semibold text-gray-700">{{ $zone->zone_name }} Pricing</h4>
    </div>

    <div class="grid grid-cols-3 gap-4 text-sm">
        <div class="text-center">
            <div class="text-xs text-gray-500 mb-1">Standard</div>
            <div class="font-semibold text-blue-600">EGP {{ number_format($zone->shipping_cost, 2) }}</div>
        </div>

        @if($zone->express_cost)
        <div class="text-center">
            <div class="text-xs text-gray-500 mb-1">Express</div>
            <div class="font-semibold text-orange-600">EGP {{ number_format($zone->express_cost, 2) }}</div>
        </div>
        @endif

        @if($zone->same_day_cost)
        <div class="text-center">
            <div class="text-xs text-gray-500 mb-1">Same Day</div>
            <div class="font-semibold text-red-600">EGP {{ number_format($zone->same_day_cost, 2) }}</div>
        </div>
        @endif
    </div>

    <div class="mt-3 pt-3 border-t border-gray-200 flex justify-between text-xs text-gray-600">
        <span>COD Fee: EGP {{ number_format($zone->cod_fee, 2) }}</span>
        <span>Delivery: {{ $zone->estimated_delivery_days }} day(s)</span>
    </div>
</div>
