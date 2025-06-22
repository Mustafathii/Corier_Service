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
        $user = auth()->user();

        // Base query - this is key for filtering!
        $shipmentsQuery = Shipment::query();

        // Apply role-based filtering
        if ($user->hasRole('Driver')) {
            $shipmentsQuery->where('driver_id', $user->id);
        } elseif ($user->hasRole('Seller')) {
            $shipmentsQuery->where('seller_id', $user->id);
        }
        // Admin/Operations Manager see all shipments (no filtering)

        // Calculate stats based on filtered query
        $totalShipments = $shipmentsQuery->count();
        $pendingShipments = (clone $shipmentsQuery)->where('status', 'pending')->count();
        $inTransitShipments = (clone $shipmentsQuery)->whereIn('status', ['picked_up', 'in_transit', 'out_for_delivery'])->count();
        $deliveredToday = (clone $shipmentsQuery)->where('status', 'delivered')
            ->whereDate('actual_delivery_date', today())->count();

        return [
            Stat::make('Total Shipments', $totalShipments)
                ->description($this->getStatDescription('total'))
                ->color('primary')
                ->icon('heroicon-o-truck'),

            Stat::make('Pending Shipments', $pendingShipments)
                ->description($this->getStatDescription('pending'))
                ->color('warning')
                ->icon('heroicon-o-clock'),

            Stat::make('In Transit', $inTransitShipments)
                ->description($this->getStatDescription('transit'))
                ->color('info')
                ->icon('heroicon-o-arrow-right'),

            Stat::make('Delivered Today', $deliveredToday)
                ->description($this->getStatDescription('delivered'))
                ->color('success')
                ->icon('heroicon-o-check-circle'),
        ];
    }

    /**
     * Get appropriate description based on user role
     */
    protected function getStatDescription(string $type): string
    {
        $user = auth()->user();

        if ($user->hasRole('Seller')) {
            return match($type) {
                'total' => 'Your shipments',
                'pending' => 'Your pending orders',
                'transit' => 'Your shipments in transit',
                'delivered' => 'Your deliveries today',
                default => 'Your shipments'
            };
        } elseif ($user->hasRole('Driver')) {
            return match($type) {
                'total' => 'Your assigned shipments',
                'pending' => 'Awaiting your pickup',
                'transit' => 'You are delivering',
                'delivered' => 'You delivered today',
                default => 'Your assignments'
            };
        } else {
            // Admin/Manager
            return match($type) {
                'total' => 'All time shipments',
                'pending' => 'Awaiting pickup',
                'transit' => 'Currently shipping',
                'delivered' => 'Completed today',
                default => 'System wide'
            };
        }
    }
}
