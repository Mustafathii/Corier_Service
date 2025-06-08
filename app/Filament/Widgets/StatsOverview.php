<?php

namespace App\Filament\Widgets;

use App\Models\Shipment;
use App\Models\Driver;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Shipments', Shipment::count())
                ->description('All time shipments')
                ->color('primary')
                ->icon('heroicon-o-truck'),

            Stat::make('Pending Shipments', Shipment::where('status', 'pending')->count())
                ->description('Awaiting pickup')
                ->color('warning')
                ->icon('heroicon-o-clock'),

            Stat::make('In Transit', Shipment::whereIn('status', ['picked_up', 'in_transit', 'out_for_delivery'])->count())
                ->description('Currently shipping')
                ->color('info')
                ->icon('heroicon-o-arrow-right'),

            Stat::make('Delivered Today', Shipment::where('status', 'delivered')
                ->whereDate('actual_delivery_date', today())->count())
                ->description('Completed today')
                ->color('success')
                ->icon('heroicon-o-check-circle'),
        ];
    }
}
