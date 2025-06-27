<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ZoneResource\Pages;
use App\Models\Zone;
use App\Models\Governorate;
use App\Models\City;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Database\Eloquent\Builder;

class ZoneResource extends Resource
{
    protected static ?string $model = Zone::class;
    protected static ?string $navigationIcon = 'heroicon-o-map';
    protected static ?string $navigationLabel = 'Zones Management';
    protected static ?string $navigationGroup = 'Location Settings';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Zone Location')
                    ->schema([
                        Forms\Components\Select::make('governorate_id')
                            ->label('Governorate')
                            ->options(Governorate::where('is_active', true)->pluck('governorate_name_en', 'id'))
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set) {
                                $set('city_id', null);
                            }),

                        Forms\Components\Select::make('city_id')
                            ->label('City')
                            ->options(fn (Get $get): array =>
                                City::where('governorate_id', $get('governorate_id'))
                                    ->where('is_active', true)
                                    ->pluck('city_name_en', 'id')
                                    ->toArray()
                            )
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                $city = City::find($get('city_id'));
                                if ($city) {
                                    $set('zone_name', $city->city_name_en);
                                }
                            }),

                        Forms\Components\TextInput::make('zone_name')
                            ->label('Zone Name')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Pricing Configuration')
                    ->schema([
                        Forms\Components\TextInput::make('shipping_cost')
                            ->label('Standard Shipping Cost')
                            ->numeric()
                            ->required()
                            ->prefix('EGP')
                            ->step(0.01)
                            ->minValue(0)
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                $basePrice = (float)$get('shipping_cost');
                                if ($basePrice > 0) {
                                    $set('express_cost', $basePrice * 1.5);
                                    $set('same_day_cost', $basePrice * 2);
                                }
                            }),

                        Forms\Components\TextInput::make('express_cost')
                            ->label('Express Shipping Cost')
                            ->numeric()
                            ->prefix('EGP')
                            ->step(0.01)
                            ->minValue(0)
                            ->helperText('Leave empty to use 1.5x standard cost'),

                        Forms\Components\TextInput::make('same_day_cost')
                            ->label('Same Day Shipping Cost')
                            ->numeric()
                            ->prefix('EGP')
                            ->step(0.01)
                            ->minValue(0)
                            ->helperText('Leave empty to use 2x standard cost'),

                        Forms\Components\TextInput::make('cod_fee')
                            ->label('Cash on Delivery Fee')
                            ->numeric()
                            ->prefix('EGP')
                            ->step(0.01)
                            ->minValue(0)
                            ->default(5.00),

                        Forms\Components\TextInput::make('estimated_delivery_days')
                            ->label('Estimated Delivery Days')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(30)
                            ->default(1)
                            ->suffix('days'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Is Active')
                            ->default(true),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Additional Information')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3)
                            ->columnSpanFull()
                            ->placeholder('Any additional notes about this zone...'),
                    ]),
            ]);
    }// في ZoneResource.php - استبدل الـ table method

public static function table(Table $table): Table
{
    return $table
        // ✅ بدون modifyQueryUsing - هيعرض كل الزونز
        ->columns([
            Tables\Columns\TextColumn::make('governorate.governorate_name_en')
                ->label('Governorate')
                ->searchable()
                ->sortable(),

            Tables\Columns\TextColumn::make('city.city_name_en')
                ->label('City')
                ->searchable()
                ->sortable(),

            Tables\Columns\TextColumn::make('zone_name')
                ->label('Zone Name')
                ->searchable()
                ->sortable(),

            Tables\Columns\TextColumn::make('shipping_cost')
                ->label('Standard')
                ->money('EGP')
                ->sortable(),

            Tables\Columns\TextColumn::make('express_cost')
                ->label('Express')
                ->money('EGP')
                ->placeholder('Auto'),

            Tables\Columns\TextColumn::make('same_day_cost')
                ->label('Same Day')
                ->money('EGP')
                ->placeholder('Auto'),

            Tables\Columns\TextColumn::make('cod_fee')
                ->label('COD Fee')
                ->money('EGP')
                ->sortable(),

            Tables\Columns\TextColumn::make('estimated_delivery_days')
                ->label('Delivery Days')
                ->suffix(' days')
                ->sortable(),

            Tables\Columns\IconColumn::make('is_active')
                ->label('Active')
                ->boolean(),

            Tables\Columns\TextColumn::make('created_at')
                ->label('Created')
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ])
        ->filters([
            Tables\Filters\SelectFilter::make('governorate')
                ->relationship('governorate', 'governorate_name_en')
                ->label('Governorate'),

            Tables\Filters\SelectFilter::make('city')
                ->relationship('city', 'city_name_en')
                ->label('City'),

            Tables\Filters\TernaryFilter::make('is_active')
                ->label('Status')
                ->placeholder('All zones')
                ->trueLabel('Active zones')
                ->falseLabel('Inactive zones'),
        ])
        ->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ])
        ->bulkActions([
            Tables\Actions\BulkActionGroup::make([
                Tables\Actions\DeleteBulkAction::make(),

                Tables\Actions\BulkAction::make('activate')
                    ->label('Activate Selected')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->action(function ($records) {
                        $records->each->update(['is_active' => true]);
                    }),

                Tables\Actions\BulkAction::make('deactivate')
                    ->label('Deactivate Selected')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->action(function ($records) {
                        $records->each->update(['is_active' => false]);
                    }),

                Tables\Actions\BulkAction::make('update_prices')
                    ->label('Update Prices')
                    ->icon('heroicon-o-currency-dollar')
                    ->color('warning')
                    ->form([
                        Forms\Components\TextInput::make('price_multiplier')
                            ->label('Price Multiplier')
                            ->numeric()
                            ->step(0.01)
                            ->minValue(0.1)
                            ->maxValue(10)
                            ->default(1)
                            ->helperText('Multiply current prices by this value'),

                        Forms\Components\TextInput::make('fixed_increase')
                            ->label('Fixed Increase')
                            ->numeric()
                            ->step(0.01)
                            ->minValue(-1000)
                            ->maxValue(1000)
                            ->default(0)
                            ->prefix('EGP')
                            ->helperText('Add this amount to current prices'),
                    ])
                    ->action(function (array $data, $records) {
                        $multiplier = $data['price_multiplier'] ?? 1;
                        $increase = $data['fixed_increase'] ?? 0;

                        $records->each(function ($zone) use ($multiplier, $increase) {
                            $zone->update([
                                'shipping_cost' => ($zone->shipping_cost * $multiplier) + $increase,
                                'express_cost' => $zone->express_cost ? (($zone->express_cost * $multiplier) + $increase) : null,
                                'same_day_cost' => $zone->same_day_cost ? (($zone->same_day_cost * $multiplier) + $increase) : null,
                            ]);
                        });
                    }),
            ]),
        ])
        ->defaultSort('governorate.governorate_name_en');
}

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListZones::route('/'),
            'create' => Pages\CreateZone::route('/create'),
            'edit' => Pages\EditZone::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('is_active', true)->count();
    }
}
