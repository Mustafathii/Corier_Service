<?php

namespace App\Filament\Widgets;

use App\Models\Shipment;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentShipments extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Shipment::query()->latest()->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('tracking_number')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'info' => ['picked_up', 'in_transit'],
                        'primary' => 'out_for_delivery',
                        'success' => 'delivered',
                        'danger' => ['failed_delivery', 'returned', 'canceled'],
                    ]),
                Tables\Columns\TextColumn::make('sender_name'),
                Tables\Columns\TextColumn::make('receiver_name'),
                Tables\Columns\TextColumn::make('receiver_city'),
                Tables\Columns\TextColumn::make('driver.name')
                    ->placeholder('Not assigned'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->since(),
            ]);
    }
}
