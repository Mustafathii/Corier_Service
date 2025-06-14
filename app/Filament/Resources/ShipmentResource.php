<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShipmentResource\Pages;
use App\Models\Shipment;
use App\Models\User;
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
    protected static ?string $navigationGroup = 'Management';
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
                            ->label('City')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Delivery & Shipment Info')
                    ->schema([
                        Forms\Components\DateTimePicker::make('pickup_date')
                            ->label('Pickup Date')
                            ->placeholder('Optional - when package will be picked up'),

                        Forms\Components\DatePicker::make('expected_delivery_date')
                            ->label('Expected Delivery Date')
                            ->default(Carbon::tomorrow())
                            ->required()
                            ->minDate(today()),

                        Forms\Components\DateTimePicker::make('actual_delivery_date')
                            ->label('Actual Delivery Date')
                            ->visible(fn (Get $get) => in_array($get('status'), ['delivered', 'failed_delivery'])),

                        Forms\Components\Select::make('shipment_type')
                            ->label('Shipment Type')
                            ->options([
                                'express' => 'Express',
                                'standard' => 'Standard',
                                'same_day' => 'Same Day',
                            ])
                            ->required()
                            ->default('standard')
                            ->live()
                            ->afterStateUpdated(function (Set $set, $state) {
                                $costs = [
                                    'express' => 25.00,
                                    'standard' => 15.00,
                                    'same_day' => 35.00,
                                ];
                                $set('shipping_cost', $costs[$state] ?? 15.00);
                            }),

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
                    ->columns(3),

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

                        Forms\Components\TextInput::make('shipping_cost')
                            ->label('Shipping Cost')
                            ->numeric()
                            ->required()
                            ->prefix('E£')
                            ->step(0.01)
                            ->minValue(0),

                        Forms\Components\TextInput::make('cod_amount')
                            ->label('COD Amount')
                            ->numeric()
                            ->prefix('E£')
                            ->step(0.01)
                            ->minValue(0)
                            ->required(fn (Get $get) => $get('payment_method') === 'cod')
                            ->visible(fn (Get $get) => $get('payment_method') === 'cod')
                            ->helperText('Amount to collect from receiver'),
                    ])
                    ->columns(3),

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
                    ->label('City')
                    ->searchable()
                    ->sortable(),

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
                    ]),

                Tables\Columns\TextColumn::make('weight')
                    ->suffix(' kg')
                    ->sortable(),

                Tables\Columns\TextColumn::make('shipping_cost')
                    ->money('USD')
                    ->sortable(),

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
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShipments::route('/'),
            'create' => Pages\CreateShipment::route('/create'),
            'edit' => Pages\EditShipment::route('/{record}/edit'),
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
