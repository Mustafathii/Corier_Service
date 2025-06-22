<?php

namespace App\Filament\Widgets;

use App\Models\Shipment;
use App\Models\User;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class DriverPerformanceWidget extends BaseWidget
{
    protected static ?int $sort = 2;
    protected static ?string $heading = 'Driver Performance';
    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = auth()->user();

        // Only show to Admin and Operations Manager
        // Hide from Sellers and individual Drivers
        return $user->hasAnyRole(['Admin', 'Operations Manager']);
    }

    public function table(Table $table): Table
    {
        $user = auth()->user();

        // Base query for users with Driver role
        $driversQuery = User::query()
            ->whereHas('roles', function ($query) {
                $query->where('name', 'Driver');
            })
            ->where('is_active', true);

        // Apply role-based filtering for shipment counts
        $shipmentCountsModifier = function (Builder $query) use ($user) {
            // If current user is a seller, only count shipments from that seller
            if ($user->hasRole('Seller')) {
                $query->where('seller_id', $user->id);
            }
            // Admin/Operations Manager see all shipments (no additional filtering)
        };

        return $table
            ->query(
                $driversQuery->withCount([
                    'driverShipments as total_shipments' => $shipmentCountsModifier,
                    'driverShipments as delivered_shipments' => function (Builder $query) use ($shipmentCountsModifier) {
                        $shipmentCountsModifier($query);
                        $query->where('status', 'delivered');
                    },
                    'driverShipments as pending_shipments' => function (Builder $query) use ($shipmentCountsModifier) {
                        $shipmentCountsModifier($query);
                        $query->where('status', 'pending');
                    },
                    'driverShipments as in_transit_shipments' => function (Builder $query) use ($shipmentCountsModifier) {
                        $shipmentCountsModifier($query);
                        $query->whereIn('status', ['picked_up', 'in_transit', 'out_for_delivery']);
                    },
                    'driverShipments as today_deliveries' => function (Builder $query) use ($shipmentCountsModifier) {
                        $shipmentCountsModifier($query);
                        $query->where('status', 'delivered')
                              ->whereDate('actual_delivery_date', today());
                    }
                ])
                ->orderByDesc('total_shipments')
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Driver Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone')
                    ->placeholder('N/A'),

                Tables\Columns\BadgeColumn::make('total_shipments')
                    ->label($this->getColumnLabel('total'))
                    ->color('primary')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('delivered_shipments')
                    ->label($this->getColumnLabel('delivered'))
                    ->color('success')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('in_transit_shipments')
                    ->label($this->getColumnLabel('transit'))
                    ->color('warning')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('pending_shipments')
                    ->label($this->getColumnLabel('pending'))
                    ->color('gray')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('today_deliveries')
                    ->label($this->getColumnLabel('today'))
                    ->color('info')
                    ->sortable(),

                Tables\Columns\TextColumn::make('success_rate')
                    ->label('Success Rate')
                    ->state(function ($record) {
                        if ($record->total_shipments == 0) {
                            return '0%';
                        }
                        $rate = ($record->delivered_shipments / $record->total_shipments) * 100;
                        return number_format($rate, 1) . '%';
                    })
                    ->color(fn ($state) => match(true) {
                        floatval($state) >= 90 => 'success',
                        floatval($state) >= 70 => 'warning',
                        default => 'danger'
                    })
                    ->badge(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->actions([
                Tables\Actions\Action::make('view_shipments')
                    ->label('View Shipments')
                    ->icon('heroicon-m-eye')
                    ->url(fn (User $record): string =>
                        $this->getShipmentViewUrl($record)
                    )
                    ->openUrlInNewTab(),
            ])
            ->emptyStateHeading($this->getEmptyStateHeading())
            ->emptyStateDescription($this->getEmptyStateDescription())
            ->emptyStateIcon('heroicon-o-user-group');
    }

    /**
     * Get column labels based on user role
     */
    protected function getColumnLabel(string $type): string
    {
        $user = auth()->user();

        if ($user->hasRole('Seller')) {
            return match($type) {
                'total' => 'Your Total',
                'delivered' => 'Your Delivered',
                'transit' => 'Your In Transit',
                'pending' => 'Your Pending',
                'today' => 'Your Today',
                default => 'Your Shipments'
            };
        } else {
            // Admin/Manager
            return match($type) {
                'total' => 'Total Shipments',
                'delivered' => 'Delivered',
                'transit' => 'In Transit',
                'pending' => 'Pending',
                'today' => 'Today',
                default => 'Shipments'
            };
        }
    }

    /**
     * Get shipment view URL with appropriate filters
     */
    protected function getShipmentViewUrl(User $record): string
    {
        $user = auth()->user();

        $filters = ['tableFilters[driver][value]' => $record->id];

        // If seller, also filter by seller_id
        if ($user->hasRole('Seller')) {
            $filters['tableFilters[seller][value]'] = $user->id;
        }

        return route('filament.admin.resources.shipments.index', $filters);
    }

    /**
     * Get appropriate empty state heading
     */
    protected function getEmptyStateHeading(): string
    {
        $user = auth()->user();

        if ($user->hasRole('Seller')) {
            return 'No Drivers Assigned';
        }

        return 'No Active Drivers';
    }

    /**
     * Get appropriate empty state description
     */
    protected function getEmptyStateDescription(): string
    {
        $user = auth()->user();

        if ($user->hasRole('Seller')) {
            return 'No drivers have been assigned to your shipments yet.';
        }

        return 'No drivers are currently active in the system.';
    }

    /**
     * Update widget heading based on user role
     */
    public function getHeading(): string
    {
        $user = auth()->user();

        if ($user->hasRole('Seller')) {
            return 'Driver Performance (Your Shipments)';
        }

        return 'Driver Performance';
    }
}
