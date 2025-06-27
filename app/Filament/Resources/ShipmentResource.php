<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShipmentResource\Pages;
use App\Models\Shipment;
use App\Models\User;
use App\Models\City;
use App\Models\Governorate;
use App\Models\Zone;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Carbon\Carbon;

class ShipmentResource extends Resource
{
    protected static ?string $model = Shipment::class;
    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return auth()->user()->can('view_shipments') || auth()->user()->can('view_own_shipments');
    }
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        $query->with(['seller', 'driver']);

        if ($user->hasRole('Driver')) {
            return $query->where('driver_id', $user->id);
        }

        if ($user->hasRole('Seller')) {
            return $query->where('seller_id', $user->id);
        }

        return $query;
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['seller', 'driver']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'tracking_number',
            'sender_name',
            'receiver_name',
            'receiver_city',
            'package_type',
            'description',
        ];
    }


    // Helper method to get status-specific query for sub-pages
public static function getEloquentQueryForStatus(string $status): Builder
{
    $query = static::getEloquentQuery();

    if ($status === 'in_transit') {
        return $query->where('status', 'in_transit');
    }

    if ($status === 'out_for_delivery') {
        return $query->where('status', 'out_for_delivery');
    }

    if ($status === 'delivered') {
        return $query->where('status', 'delivered');
    }

    if ($status === 'canceled') {
        return $query->whereIn('status', ['canceled', 'returned']);
    }

    return $query;
}

public static function getGlobalSearchResultDetails(Model $record): array
{
    $details = [];

    if ($record->seller?->company_name) {
        $details['Company'] = $record->seller->company_name;
    }

    if ($record->receiver_city) {
        $details['City'] = $record->receiver_city;
    }

    if ($record->status) {
        $details['Status'] = ucfirst(str_replace('_', ' ', $record->status));
    }

    return $details;
}

    public static function form(Form $form): Form
{
    $user = auth()->user();

    return $form
        ->schema([
            Forms\Components\Section::make('Shipment Details')
                ->schema([
                    Forms\Components\Toggle::make('is_existing_seller')
                        ->label('Client Type')
                        ->helperText('Toggle Right for Existing Seller, Left for New Client')
                        ->default(true)
                        ->live()
                        ->afterStateUpdated(function (Set $set, $state) {
                            if (!$state) {
                                $set('seller_id', null);
                            }
                        }),

                    Forms\Components\Select::make('seller_id')
                        ->label('Select Seller Company')
                        ->relationship(
                            'seller',
                            'company_name',
                            fn ($query) => $query->whereNotNull('company_name')
                                ->where('company_name', '!=', '')
                                ->whereHas('roles', function ($q) {
                                    $q->where('name', 'Seller');
                                })
                        )
                        ->getOptionLabelFromRecordUsing(fn ($record) => $record->company_name ?: $record->name)
                        ->searchable()
                        ->preload()
                        ->required(fn (Get $get) => $get('is_existing_seller'))
                        ->visible(fn (Get $get) => $get('is_existing_seller'))
                        ->placeholder('Select seller company'),

                    Forms\Components\TextInput::make('sender_name')
                        ->label('Sender Name')
                        ->required(fn (Get $get) => !$get('is_existing_seller'))
                        ->visible(fn (Get $get) => !$get('is_existing_seller'))
                        ->maxLength(255),

                    Forms\Components\TextInput::make('sender_phone')
                        ->label('Sender Phone')
                        ->tel()
                        ->required(fn (Get $get) => !$get('is_existing_seller'))
                        ->visible(fn (Get $get) => !$get('is_existing_seller'))
                        ->maxLength(255),

                    Forms\Components\Textarea::make('sender_address')
                        ->label('Sender Address')
                        ->required(fn (Get $get) => !$get('is_existing_seller'))
                        ->visible(fn (Get $get) => !$get('is_existing_seller'))
                        ->rows(2),

                    Forms\Components\TextInput::make('sender_city')
                        ->label('Sender City')
                        ->required(fn (Get $get) => !$get('is_existing_seller'))
                        ->visible(fn (Get $get) => !$get('is_existing_seller'))
                        ->maxLength(255),

                    Forms\Components\TextInput::make('tracking_number')
                        ->label('Tracking Number')
                        ->default(fn() => 'DP-' . date('Y') . str_pad(mt_rand(1, 99999999), 7, '0', STR_PAD_LEFT))
                        ->disabled()
                        ->dehydrated()
                        ->unique(ignoreRecord: true)
                        ->suffixAction(
                            Action::make('generateBarcode')
                                ->icon('heroicon-m-qr-code')
                                ->tooltip('Generate Barcode')
                                ->action(function (Set $set, Get $get) {
                                })
                        ),
                ])
                ->columns(2),

            Forms\Components\Section::make('Receiver Information')
                ->schema([
                    Forms\Components\TextInput::make('receiver_name')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('receiver_phone')
                        ->tel()
                        ->required()
                        ->maxLength(255),

                    Forms\Components\Textarea::make('receiver_address')
                        ->required()
                        ->rows(2)
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('receiver_city')
                        ->label('City (Text)')
                        ->maxLength(255)
                        ->helperText('This will be overridden by selected city below'),
                ])
                ->columns(2),

            // 🎯 النظام الجديد - Location & Pricing
            Forms\Components\Section::make('🗺️ Location & Pricing')
    ->description('Select governorate and city for automatic pricing')
    ->schema([
        Forms\Components\Select::make('governorate_id')
            ->label('Governorate')
            ->options(function () {
                return \App\Models\Governorate::where('is_active', true)
                    ->pluck('governorate_name_en', 'id');
            })
            ->required()
            ->searchable()
            ->live()
            ->afterStateUpdated(function (Set $set) {
                $set('city_id', null);
                $set('zone_id', null);
                $set('shipping_cost', 15.00); // Default cost
            }),

        Forms\Components\Select::make('city_id')
    ->label('City')
    ->options(function (Get $get): array {
        if (!$get('governorate_id')) {
            return [];
        }

        // جيب المدن اللي عندها zones نشطة بس
        return \App\Models\City::where('governorate_id', $get('governorate_id'))
            ->where('is_active', true)
            ->whereHas('zones', function($query) {
                $query->where('is_active', true);
            })
            ->pluck('city_name_en', 'id')
            ->toArray();
    })
    ->required()
    ->searchable()
    ->live()
    ->afterStateUpdated(function (Get $get, Set $set) {
        $set('zone_id', null);

        if ($get('city_id')) {
            // Update receiver_city text field with selected city
            $city = \App\Models\City::find($get('city_id'));
            if ($city) {
                $set('receiver_city', $city->city_name_en);
            }

            // Find ACTIVE zone and update pricing
            $zone = \App\Models\Zone::where('city_id', $get('city_id'))
                ->where('is_active', true)
                ->first();

            if ($zone) {
                $set('zone_id', $zone->id);

                // Calculate cost based on shipment type
                $shipmentType = $get('shipment_type') ?? 'standard';
                $cost = $zone->getCostByType($shipmentType);
                $set('shipping_cost', $cost);

                // Update expected delivery date
                $deliveryDate = now()->addDays($zone->estimated_delivery_days);
                $set('expected_delivery_date', $deliveryDate->format('Y-m-d'));
            }
        }
    }),
        Forms\Components\Select::make('zone_id')
            ->label('Zone')
            ->options(fn (Get $get): array =>
                $get('city_id')
                    ? \App\Models\Zone::where('city_id', $get('city_id'))
                        ->where('is_active', true) // ✅ عرض المناطق النشطة فقط
                        ->pluck('zone_name', 'id')
                        ->toArray()
                    : []
            )
            ->visible(fn (Get $get) => !empty($get('city_id')))
            ->live()
            ->afterStateUpdated(function (Get $get, Set $set) {
                // ✅ التأكد من أن المنطقة المختارة نشطة
                $zone = \App\Models\Zone::where('is_active', true)
                    ->find($get('zone_id'));

                if ($zone) {
                    $shipmentType = $get('shipment_type') ?? 'standard';
                    $cost = $zone->getCostByType($shipmentType);
                    $set('shipping_cost', $cost);

                    // Update delivery date
                    $deliveryDate = now()->addDays($zone->estimated_delivery_days);
                    $set('expected_delivery_date', $deliveryDate->format('Y-m-d'));
                }
            }),

                    Forms\Components\Select::make('shipment_type')
                        ->label('Shipment Type')
                        ->options([
                            'standard' => 'Standard',
                            'express' => 'Express',
                            'same_day' => 'Same Day',
                        ])
                        ->required()
                        ->default('standard')
                        ->live()
                        ->afterStateUpdated(function (Get $get, Set $set) {
                            $zoneId = $get('zone_id');
                            if ($zoneId) {
                                $zone = \App\Models\Zone::find($zoneId);
                                if ($zone) {
                                    $shipmentType = $get('shipment_type');
                                    $cost = $zone->getCostByType($shipmentType);
                                    $set('shipping_cost', $cost);
                                }
                            } else {
                                // Fallback to static pricing if no zone selected
                                $costs = [
                                    'express' => 25.00,
                                    'standard' => 15.00,
                                    'same_day' => 35.00,
                                ];
                                $set('shipping_cost', $costs[$get('shipment_type')] ?? 15.00);
                            }
                        }),

                    Forms\Components\TextInput::make('shipping_cost')
                        ->label('Shipping Cost')
                        ->numeric()
                        ->required()
                        ->prefix('EGP')
                        ->step(0.01)
                        ->minValue(0)
                        ->helperText(fn (Get $get) =>
                            $get('zone_id')
                                ? 'Auto-calculated based on selected zone'
                                : 'Manual pricing - select a city for automatic calculation'
                        ),
                ])
                ->columns(3),

            Forms\Components\Section::make('Delivery & Status Information')
                ->schema([
                    Forms\Components\DateTimePicker::make('pickup_date')
                        ->label('Pickup Date')
                        ->placeholder('Optional - when package will be picked up'),

                    Forms\Components\DatePicker::make('expected_delivery_date')
                        ->label('Expected Delivery Date')
                        ->default(Carbon::tomorrow())
                        ->required()
                        ->minDate(today())
                        ->helperText(fn (Get $get) =>
                            $get('zone_id')
                                ? 'Auto-calculated based on zone delivery time'
                                : 'Please select manually'
                        ),

                    Forms\Components\DateTimePicker::make('actual_delivery_date')
                        ->label('Actual Delivery Date')
                        ->visible(fn (Get $get) => in_array($get('status'), ['delivered', 'failed_delivery'])),

                    Forms\Components\Select::make('status')
                        ->options([
                            'pending' => 'Pending',
                            'picked_up' => 'Picked Up',
                            'in_transit' => 'In Transit',
                            'out_for_delivery' => 'Out for Delivery',
                            'delivered' => 'Delivered',
                            'failed_delivery' => 'Failed Delivery',
                            'returned' => 'Returned',
                            'canceled' => 'Canceled',
                        ])
                        ->required()
                        ->default('in_transit')
                        ->disabled(fn () => !$user->can('update_shipment_status')),
                ])
                ->columns(2),

            Forms\Components\Section::make('Package Details')
                ->schema([
                    Forms\Components\TextInput::make('package_type')
                        ->label('Package Type')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('e.g., Electronics, Documents, Fragile, Clothing'),

                    Forms\Components\TextInput::make('weight')
                        ->numeric()
                        ->required()
                        ->suffix('kg')
                        ->step(0.01)
                        ->minValue(0.01),

                    Forms\Components\TextInput::make('quantity')
                        ->numeric()
                        ->required()
                        ->default(1)
                        ->minValue(1),

                    Forms\Components\TextInput::make('declared_value')
                        ->label('Declared Value')
                        ->numeric()
                        ->step(0.01)
                        ->minValue(0)
                        ->placeholder('Optional - for insurance purposes'),

                    Forms\Components\Textarea::make('description')
                        ->label('Package Description')
                        ->rows(3)
                        ->columnSpanFull()
                        ->placeholder('Describe the package contents in detail...'),
                ])
                ->columns(2),

            Forms\Components\Section::make('Payment Information')
                ->schema([
                    Forms\Components\Select::make('payment_method')
                        ->label('Payment Method')
                        ->options([
                            'cod' => 'Cash on Delivery',
                            'prepaid' => 'Prepaid',
                            'electronic_wallet' => 'Electronic Wallet',
                        ])
                        ->required()
                        ->default('cod')
                        ->live(),

                    Forms\Components\TextInput::make('cod_amount')
                        ->label('COD Amount')
                        ->numeric()
                        ->prefix('EGP')
                        ->step(0.01)
                        ->minValue(0)
                        ->required(fn (Get $get) => $get('payment_method') === 'cod')
                        ->visible(fn (Get $get) => $get('payment_method') === 'cod')
                        ->helperText('Amount to collect from receiver'),

                    // Show zone-based pricing info
                    Forms\Components\Placeholder::make('pricing_info')
                        ->label('Pricing Information')
                        ->content(function (Get $get) {
                            $zoneId = $get('zone_id');
                            if ($zoneId) {
                                $zone = \App\Models\Zone::find($zoneId);
                                if ($zone) {
                                    return view('filament.components.zone-pricing-info', compact('zone'));
                                }
                            }
                            return 'Select a city to see pricing breakdown';
                        })
                        ->visible(fn (Get $get) => !empty($get('zone_id')))
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Forms\Components\Section::make('Additional Notes')
                ->schema([
                    Forms\Components\Textarea::make('notes')
                        ->rows(3)
                        ->columnSpanFull()
                        ->placeholder('Any additional notes or special instructions...'),

                    Forms\Components\Textarea::make('internal_notes')
                        ->label('Internal Notes (Staff Only)')
                        ->rows(2)
                        ->columnSpanFull()
                        ->visible(fn () => $user->hasAnyRole(['Admin', 'Operations Manager']))
                        ->placeholder('Internal notes not visible to clients...'),
                ]),
        ]);
}
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('tracking_number')
                    ->label('Tracking #')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('medium'),


                Tables\Columns\BadgeColumn::make('status')
                    ->formatStateUsing(fn (string $state): string => match($state) {
                        'pending' => 'Pending',
                        'picked_up' => 'Picked Up',
                        'in_transit' => 'In Transit',
                        'out_for_delivery' => 'Out for Delivery',
                        'delivered' => 'Delivered',
                        'failed_delivery' => 'Failed Delivery',
                        'returned' => 'Returned',
                        'canceled' => 'Canceled',
                        default => ucfirst(str_replace('_', ' ', $state)),
                    })
                    ->color(fn (string $state): string => match($state) {
                        'pending' => 'gray',
                        'picked_up' => 'info',
                        'in_transit' => 'warning',
                        'out_for_delivery' => 'primary',
                        'delivered' => 'success',
                        'canceled', 'failed_delivery', 'returned' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('sender_name')
                    ->label('Sender')
                    ->searchable()
                    ->sortable()
                    ->placeholder('N/A')
                    ->description(fn ($record) => $record->seller?->company_name),

                Tables\Columns\TextColumn::make('seller.company_name')
                    ->label('Company')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Individual')
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('receiver_name')
                    ->label('Receiver')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('receiver_phone')
                    ->label('Receiver Phone')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('receiver_city')
                    ->label('Destination')
                    ->searchable()
                    ->sortable()
                    ->getStateUsing(fn ($record) => $record->location_display)
                    ->description(fn ($record) => $record->receiver_address)
                    ->icon(fn ($record) => $record->zone_id ? 'heroicon-o-map-pin' : 'heroicon-o-pencil'),

                Tables\Columns\TextColumn::make('governorate.governorate_name_en')
                    ->label('Governorate')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

Tables\Columns\TextColumn::make('city.city_name_en')
                    ->label('City')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->zone?->zone_name ?? 'No zone set')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('shipping_cost')
                    ->label('Cost')
                    ->money('EGP')
                    ->sortable()
                    ->description(fn ($record) => $record->zone ?
                        "Zone: {$record->zone->zone_name}" :
                        "Manual pricing"
                    )
                    ->badge()
                    ->color(fn ($record) => $record->zone_id ? 'success' : 'warning'),

                Tables\Columns\BadgeColumn::make('shipment_type')
                    ->label('Type')
                    ->formatStateUsing(fn (string $state): string => match($state) {
                'express' => 'Express',
                'standard' => 'Standard',
                'same_day' => 'Same Day',
                default => ucfirst(str_replace('_', ' ', $state)),
            })
                    ->colors([
                'success' => 'express',
                'primary' => 'standard',
                'warning' => 'same_day',
            ])
                    ->icons([
                        'heroicon-o-bolt' => 'express',
                        'heroicon-o-truck' => 'standard',
                        'heroicon-o-clock' => 'same_day',
                    ]),


                Tables\Columns\BadgeColumn::make('payment_method')
                    ->label('Payment')
                    ->formatStateUsing(fn (string $state): string => match($state) {
                        'cod' => 'Cash on Delivery',
                        'prepaid' => 'Prepaid',
                        'electronic_wallet' => 'Electronic Wallet',
                        default => ucfirst(str_replace('_', ' ', $state)),
                    })
                    ->colors([
                        'warning' => 'cod',
                        'success' => 'prepaid',
                        'info' => 'electronic_wallet',
                    ]),

                Tables\Columns\TextColumn::make('cod_amount')
                    ->label('COD')
                    ->money('EGP')
                    ->placeholder('N/A'),

                Tables\Columns\TextColumn::make('driver.name')
                    ->label('Driver')
                    ->searchable()
                    ->placeholder('Unassigned'),

                Tables\Columns\TextColumn::make('expected_delivery_date')
                    ->label('Expected')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->modifyQueryUsing(function (Builder $query) {
                return $query->with(['seller', 'driver']);
            })
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'picked_up' => 'Picked Up',
                        'in_transit' => 'In Transit',
                        'out_for_delivery' => 'Out for Delivery',
                        'delivered' => 'Delivered',
                        'failed_delivery' => 'Failed Delivery',
                        'returned' => 'Returned',
                        'canceled' => 'Canceled',
                    ]),

                Tables\Filters\SelectFilter::make('seller')
                    ->relationship(
                        'seller',
                        'company_name',
                        fn ($query) => $query->whereNotNull('company_name')
                            ->where('company_name', '!=', '')
                            ->whereHas('roles', function ($q) {
                                $q->where('name', 'Seller');
                            })
                    )
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->company_name ?: $record->name)
                    ->label('Seller Company')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('shipment_type')
                    ->label('Shipment Type')
                    ->options([
                        'express' => 'Express',
                        'standard' => 'Standard',
                        'same_day' => 'Same Day',
                    ]),

                Tables\Filters\SelectFilter::make('payment_method')
                    ->label('Payment Method')
                    ->options([
                        'cod' => 'Cash on Delivery',
                        'prepaid' => 'Prepaid',
                        'electronic_wallet' => 'Electronic Wallet',
                    ]),

                Tables\Filters\SelectFilter::make('driver')
                    ->relationship('driver', 'name')
                    ->label('Assigned Driver'),

                Tables\Filters\Filter::make('delivery_date')
                    ->form([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('expected_delivery_date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('expected_delivery_date', '<=', $date),
                            );
                    }),
                Tables\Filters\SelectFilter::make('governorate')
                    ->relationship('governorate', 'governorate_name_en')
                    ->label('Governorate')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('city')
                    ->relationship('city', 'city_name_en')
                    ->label('City')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('zone')
                    ->relationship('zone', 'zone_name')
                    ->label('Zone')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('has_zone')
                    ->label('Zone Status')
                    ->form([
                        Forms\Components\Select::make('zone_status')
                            ->options([
                                'with_zone' => 'With Zone (Auto Pricing)',
                                'without_zone' => 'Without Zone (Manual Pricing)',
                            ])
                            ->placeholder('All shipments'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['zone_status'] === 'with_zone',
                            fn (Builder $query): Builder => $query->whereNotNull('zone_id'),
                        )->when(
                            $data['zone_status'] === 'without_zone',
                            fn (Builder $query): Builder => $query->whereNull('zone_id'),
                        );
                    }),
            ])
            ->headerActions([
                Tables\Actions\Action::make('export')
                    ->label('Export to Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(function ($livewire) {
                        $query = $livewire->getFilteredTableQuery();
                        $records = $query->with(['seller', 'driver'])->get();
                        $filename = 'shipments_export_' . now()->format('Y-m-d_H-i-s') . '.xlsx';

                        return \Maatwebsite\Excel\Facades\Excel::download(
                            new \App\Exports\ShipmentsExport($records),
                            $filename
                        );
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Export Shipments')
                    ->modalDescription('This will export all currently filtered shipments to an Excel file.')
                    ->modalSubmitActionLabel('Download Excel'),

                Tables\Actions\Action::make('import')
                    ->label('Import from Excel')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('primary')
                    ->form([
                        Forms\Components\FileUpload::make('excel_file')
                            ->label('Upload Excel File (.xlsx, .xls, .csv)')
                            ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel', 'text/csv'])
                            ->required()
                            ->columnSpanFull(),
                    ])
                    ->action(function (array $data) {
                        try {
                            \Maatwebsite\Excel\Facades\Excel::import(new \App\Imports\ShipmentsImport, $data['excel_file']);

                            \Filament\Notifications\Notification::make()
                                ->title('Import successful')
                                ->body('Shipments have been imported successfully.')
                                ->success()
                                ->send();
                        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
                            $failures = $e->failures();
                            $errorMessage = 'Import failed. Some rows have validation errors:';
                            foreach ($failures as $failure) {
                                $errorMessage .= '<br>Row ' . $failure->row() . ': ' . implode(', ', $failure->errors());
                            }

                            \Filament\Notifications\Notification::make()
                                ->title('Import failed')
                                ->body($errorMessage)
                                ->danger()
                                ->persistent()
                                ->send();
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Import failed')
                                ->body('An error occurred during import: ' . $e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    })
                    ->modalHeading('Import Shipments')
                    ->modalDescription('Upload an Excel file (.xlsx, .xls, .csv) to import shipments.')
                    ->modalSubmitActionLabel('Import')
            ])
            ->actions([
                Tables\Actions\Action::make('printBillOfLading')
                    ->label('Print')
                    ->icon('heroicon-m-document-text')
                    ->url(fn (Shipment $record): string => route('bill-of-lading.generate', $record->id))
                    ->openUrlInNewTab()
                    ->button()
                    ->color('primary'),

                Tables\Actions\Action::make('viewHistory')
                    ->label('History')
                    ->icon('heroicon-m-clock')
                    ->color('info')
                    ->modalHeading(fn (Shipment $record) => 'History for ' . $record->tracking_number)
                    ->modalContent(fn (Shipment $record) => view('filament.modals.shipment-history', [
                        'shipment' => $record,
                        'histories' => $record->histories()->with('user')->get()
                    ]))
                    ->modalWidth('4xl')
                    ->button(),

                Tables\Actions\EditAction::make()
                    ->visible(fn () => auth()->user()->can('edit_shipments')),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn () => auth()->user()->can('delete_shipments')),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('generateBills')
                    ->label('Print Shipments')
                    ->icon('heroicon-o-document-text')
                    ->color('primary')
                    ->outlined()
                    ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                        // Load relationships
                        $shipments = $records->load(['seller', 'driver']);

                        if (count($shipments) === 1) {
                            // Single shipment - generate and download directly
                            return response()->streamDownload(function () use ($shipments) {
                                $shipment = $shipments->first();
                                $billContent = \App\Http\Controllers\BillOfLadingController::generateBillContent($shipment);
                                echo $billContent;
                            }, 'Bill_of_Lading_' . $shipments->first()->tracking_number . '_' . now()->format('Y-m-d') . '.docx');
                        } else {
                            // Multiple shipments - create ONE Word document with all bills
                            return response()->streamDownload(function () use ($shipments) {
                                $billContent = \App\Http\Controllers\BillOfLadingController::generateMultipleBillsContent($shipments);
                                echo $billContent;
                            }, 'Bills_of_Lading_' . count($shipments) . '_shipments_' . now()->format('Y-m-d_H-i-s') . '.docx');
                        }
                    })
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion(),

                // Export Selected - Side by side
                Tables\Actions\BulkAction::make('exportSelected')
                    ->label('Export Selected')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->outlined()
                    ->action(function ($records) {
                        $filename = 'selected_shipments_' . now()->format('Y-m-d_H-i-s') . '.xlsx';
                        return \Maatwebsite\Excel\Facades\Excel::download(
                            new \App\Exports\ShipmentsExport($records),
                            $filename
                        );
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Export Selected Shipments')
                    ->modalDescription('Export only the selected shipments to Excel.')
                    ->modalSubmitActionLabel('Download Excel'),

                // Delete Selected - Side by side
                Tables\Actions\DeleteBulkAction::make()
                    ->outlined()
                    ->visible(fn () => auth()->user()?->can('delete_shipments') ?? false),

                // Assign Driver - Side by side
                Tables\Actions\BulkAction::make('assignDriver')
                    ->label('Assign Driver')
                    ->icon('heroicon-m-user-plus')
                    ->color('success')
                    ->outlined()
                    ->form([
                        Forms\Components\Select::make('driver_id')
                            ->label('Select Driver')
                            ->options(function () {
                                return \App\Models\User::whereHas('roles', function ($q) {
                                    $q->where('name', 'Driver');
                                })->where('is_active', true)->pluck('name', 'id');
                            })
                            ->searchable()
                            ->required()
                            ->placeholder('Choose a driver'),
                    ])
                    ->action(function (array $data, $records) {
                        $records->each(function ($record) use ($data) {
                            $record->update([
                                'driver_id' => $data['driver_id'],
                                'status' => 'out_for_delivery' // Automatically set to "Out for Delivery"
                            ]);
                        });

                        \Filament\Notifications\Notification::make()
                            ->title('Shipments Assigned Successfully')
                            ->body(count($records) . ' shipments have been assigned to the selected driver.')
                            ->success()
                            ->send();
                    })
                    ->visible(fn () => auth()->user()?->can('assign_shipments') ?? false)
                    ->requiresConfirmation()
                    ->modalHeading('Assign Shipments to Driver')
                    ->modalDescription('Select a driver to assign the selected shipments to.')
                    ->modalSubmitActionLabel('Assign Shipments'),

                // Update Status - Side by side
                Tables\Actions\BulkAction::make('updateStatus')
                    ->label('Update Status')
                    ->icon('heroicon-m-arrow-path')
                    ->color('warning')
                    ->outlined()
                    ->form([
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'picked_up' => 'Picked Up',
                                'in_transit' => 'In Transit',
                                'out_for_delivery' => 'Out for Delivery',
                                'delivered' => 'Delivered',
                                'failed_delivery' => 'Failed Delivery',
                                'returned' => 'Returned',
                                'canceled' => 'Canceled',
                            ])
                            ->required(),
                    ])
                    ->action(function (array $data, $records) {
                        $records->each(function ($record) use ($data) {
                            $record->update(['status' => $data['status']]);
                        });

                        \Filament\Notifications\Notification::make()
                            ->title('Status Updated Successfully')
                            ->body(count($records) . ' shipments status updated to ' . $data['status'])
                            ->success()
                            ->send();
                    })
                    ->visible(fn () => auth()->user()?->can('update_shipment_status') ?? false)
                    ->requiresConfirmation(),

                // Unassign Driver - Side by side
                Tables\Actions\BulkAction::make('unassignDriver')
                    ->label('Unassign Driver')
                    ->icon('heroicon-m-user-minus')
                    ->color('danger')
                    ->outlined()
                    ->action(function ($records) {
                        $records->each(function ($record) {
                            $record->update([
                                'driver_id' => null,
                                'status' => 'pending'
                            ]);
                        });

                        \Filament\Notifications\Notification::make()
                            ->title('Drivers Unassigned')
                            ->body(count($records) . ' shipments have been unassigned and set to pending.')
                            ->success()
                            ->send();
                    })
                    ->visible(fn () => auth()->user()?->can('assign_shipments') ?? false)
                    ->requiresConfirmation()
                    ->modalHeading('Unassign Drivers')
                    ->modalDescription('This will remove driver assignments and set status to pending.'),

                // Update Location & Pricing Bulk Action
                Tables\Actions\BulkAction::make('updateLocationPricing')
                    ->label('Update Location & Pricing')
                    ->icon('heroicon-m-map')
                    ->color('info')
                    ->form([
                        Forms\Components\Select::make('governorate_id')
                            ->label('Governorate')
                            ->options(\App\Models\Governorate::where('is_active', true)->pluck('governorate_name_en', 'id'))
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('city_id', null)),

                        Forms\Components\Select::make('city_id')
                            ->label('City')
                            ->options(fn (Get $get): array =>
                                $get('governorate_id')
                                    ? \App\Models\City::where('governorate_id', $get('governorate_id'))
                                        ->where('is_active', true)
                                        ->pluck('city_name_en', 'id')
                                        ->toArray()
                                    : []
                            )
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                $zone = \App\Models\Zone::where('city_id', $get('city_id'))->first();
                                if ($zone) {
                                    $set('update_cost', true);
                                    $set('shipping_cost', $zone->shipping_cost);
                                }
                            }),

                        Forms\Components\Toggle::make('update_cost')
                            ->label('Update Shipping Cost Automatically')
                            ->default(true),

                        Forms\Components\TextInput::make('shipping_cost')
                            ->label('Custom Shipping Cost')
                            ->numeric()
                            ->prefix('EGP')
                            ->visible(fn (Get $get) => !$get('update_cost')),

                        Forms\Components\Toggle::make('update_delivery_date')
                            ->label('Update Expected Delivery Date')
                            ->default(true),
                    ])
                    ->action(function (array $data, $records) {
                        $zone = \App\Models\Zone::where('city_id', $data['city_id'])->first();
                        $city = \App\Models\City::find($data['city_id']);

                        $records->each(function ($record) use ($data, $zone, $city) {
                            $updateData = [
                                'governorate_id' => $data['governorate_id'],
                                'city_id' => $data['city_id'],
                                'zone_id' => $zone?->id,
                                'receiver_city' => $city?->city_name_en ?? $record->receiver_city,
                            ];

                            if ($data['update_cost']) {
                                if ($zone) {
                                    $updateData['shipping_cost'] = $zone->getCostByType($record->shipment_type ?? 'standard');
                                }
                            } elseif (!empty($data['shipping_cost'])) {
                                $updateData['shipping_cost'] = $data['shipping_cost'];
                            }

                            if ($data['update_delivery_date'] && $zone) {
                                $updateData['expected_delivery_date'] = now()->addDays($zone->estimated_delivery_days)->format('Y-m-d');
                            }

                            $record->update($updateData);
                        });

                        \Filament\Notifications\Notification::make()
                            ->title('Location & Pricing Updated Successfully')
                            ->body(count($records) . ' shipments have been updated with new location and pricing data.')
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Update Location & Pricing for Selected Shipments')
                    ->modalDescription('This will update the governorate, city, zone, and pricing for selected shipments.'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getNavigationItems(): array
    {
        return [
            // Main shipments page
            \Filament\Navigation\NavigationItem::make('All Shipments')
                ->icon(static::getNavigationIcon())
                ->url(static::getUrl('index'))
                ->badge(static::getNavigationBadge())
                ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.resources.shipments.index')),

            // Sub-pages for different statuses
            \Filament\Navigation\NavigationItem::make('In House')
                ->icon('heroicon-o-building-storefront')
                ->url(static::getUrl('in-house'))
                ->badge(fn () => static::getModel()::where('status', 'in_transit')->count() ?: null)
                ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.resources.shipments.in-house')),

            \Filament\Navigation\NavigationItem::make('Out for Delivery')
                ->icon('heroicon-o-truck')
                ->url(static::getUrl('out-for-delivery'))
                ->badge(fn () => static::getModel()::where('status', 'out_for_delivery')->count() ?: null)
                ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.resources.shipments.out-for-delivery')),

            \Filament\Navigation\NavigationItem::make('Delivered')
                ->icon('heroicon-o-check-circle')
                ->url(static::getUrl('delivered'))
                ->badge(fn () => static::getModel()::where('status', 'delivered')->count() ?: null)
                ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.resources.shipments.delivered')),

            \Filament\Navigation\NavigationItem::make('Canceled')
                ->icon('heroicon-o-x-circle')
                ->url(static::getUrl('canceled'))
                ->badge(fn () => static::getModel()::whereIn('status', ['canceled', 'returned'])->count() ?: null)
                ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.resources.shipments.canceled')),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShipments::route('/'),
            'create' => Pages\CreateShipment::route('/create'),
            'edit' => Pages\EditShipment::route('/{record}/edit'),

            // New status-based pages
            'in-house' => Pages\InHouseShipments::route('/in-house'),
            'out-for-delivery' => Pages\OutForDeliveryShipments::route('/out-for-delivery'),
            'delivered' => Pages\DeliveredShipments::route('/delivered'),
            'canceled' => Pages\CanceledShipments::route('/canceled'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();

        if ($user->hasRole('Driver')) {
            return static::getModel()::where('driver_id', $user->id)
                ->where('status', 'pending')
                ->count();
        }

        return static::getModel()::where('status', 'pending')->count();
    }
}
