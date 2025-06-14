<?php

namespace App\Filament\Widgets;

use App\Models\Shipment;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class CityDistributionWidget extends ChartWidget
{
    protected static ?string $heading = 'Top Delivery Cities';
    protected static ?int $sort = 4;

    protected function getData(): array
    {
        $user = auth()->user();

        // Base query
        $query = Shipment::query();

        // Filter by user role
        if ($user->hasRole('Driver')) {
            $query->where('driver_id', $user->id);
        } elseif ($user->hasRole('Seller')) {
            $query->where('seller_id', $user->id);
        }

        // Get top 10 cities by shipment count
        $cityData = $query->select('receiver_city', DB::raw('count(*) as shipment_count'))
            ->whereNotNull('receiver_city')
            ->where('receiver_city', '!=', '')
            ->groupBy('receiver_city')
            ->orderByDesc('shipment_count')
            ->limit(10)
            ->get();

        $cities = $cityData->pluck('receiver_city')->toArray();
        $counts = $cityData->pluck('shipment_count')->toArray();

        // Generate colors for each city
        $colors = [
            '#3b82f6', // blue
            '#ef4444', // red
            '#10b981', // green
            '#f59e0b', // amber
            '#8b5cf6', // purple
            '#f97316', // orange
            '#06b6d4', // cyan
            '#84cc16', // lime
            '#ec4899', // pink
            '#6b7280', // gray
        ];

        return [
            'datasets' => [
                [
                    'label' => 'Shipments',
                    'data' => $counts,
                    'backgroundColor' => array_slice($colors, 0, count($cities)),
                    'borderWidth' => 2,
                    'borderColor' => '#ffffff',
                ],
            ],
            'labels' => $cities,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y', // Horizontal bar chart
            'scales' => [
                'x' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'stepSize' => 1,
                    ],
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
        ];
    }
}
