<?php

namespace App\Filament\Widgets;

use App\Models\Shipment;
use Filament\Widgets\ChartWidget;
use Carbon\Carbon;

class RevenueAnalyticsWidget extends ChartWidget
{
    protected static ?string $heading = 'Revenue Analytics';
    protected static ?int $sort = 3;

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

        // Get last 6 months revenue data
        $revenueData = [];
        $labels = [];

        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);

            $monthlyRevenue = (clone $query)
                ->whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->sum('shipping_cost');

            $revenueData[] = floatval($monthlyRevenue);
            $labels[] = $date->format('M Y');
        }

        return [
            'datasets' => [
                [
                    'label' => 'Revenue ($)',
                    'data' => $revenueData,
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'fill' => true,
                    'tension' => 0.4,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'callback' => 'function(value) { return "$" + value.toLocaleString(); }',
                    ],
                ],
            ],
            'plugins' => [
                'tooltip' => [
                    'callbacks' => [
                        'label' => 'function(context) { return context.dataset.label + ": $" + context.parsed.y.toLocaleString(); }',
                    ],
                ],
            ],
        ];
    }
}
