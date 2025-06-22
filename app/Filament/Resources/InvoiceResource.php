<?php

// app/Filament/Resources/InvoiceResource.php

namespace App\Filament\Resources;

use App\Filament\Resources\InvoiceResource\Pages;
use App\Models\Invoice;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Invoices';
    protected static ?string $navigationGroup = 'Accounting';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Invoice Details')
                    ->schema([
                        Forms\Components\TextInput::make('invoice_number')
                            ->label('Invoice Number')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\Select::make('type')
                            ->label('Invoice Type')
                            ->options([
                                'customer' => 'Customer Invoice',
                                'driver_commission' => 'Driver Commission',
                            ])
                            ->required()
                            ->live()
                            ->default('customer'),

                        Forms\Components\Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'sent' => 'Sent',
                                'paid' => 'Paid',
                                'overdue' => 'Overdue',
                                'cancelled' => 'Cancelled',
                            ])
                            ->required()
                            ->default('draft'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Customer/Driver Information')
                    ->schema([
                        Forms\Components\Select::make('customer_id')
                            ->label('Customer')
                            ->relationship('customer', 'name')
                            ->searchable()
                            ->preload()
                            ->visible(fn (Forms\Get $get) => $get('type') === 'customer')
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set, $state) {
                                if ($state) {
                                    $customer = User::find($state);
                                    if ($customer) {
                                        $set('customer_name', $customer->name);
                                        $set('customer_email', $customer->email);
                                        $set('customer_phone', $customer->phone);
                                    }
                                }
                            }),

                        Forms\Components\Select::make('driver_id')
                            ->label('Driver')
                            ->relationship('driver', 'name', fn ($query) =>
                                $query->whereHas('roles', fn ($q) => $q->where('name', 'Driver'))
                            )
                            ->searchable()
                            ->preload()
                            ->visible(fn (Forms\Get $get) => $get('type') === 'driver_commission'),

                        Forms\Components\TextInput::make('customer_name')
                            ->label('Customer Name')
                            ->visible(fn (Forms\Get $get) => $get('type') === 'customer'),

                        Forms\Components\TextInput::make('customer_email')
                            ->email()
                            ->visible(fn (Forms\Get $get) => $get('type') === 'customer'),

                        Forms\Components\TextInput::make('customer_phone')
                            ->tel()
                            ->visible(fn (Forms\Get $get) => $get('type') === 'customer'),

                        Forms\Components\Textarea::make('customer_address')
                            ->rows(3)
                            ->columnSpanFull()
                            ->visible(fn (Forms\Get $get) => $get('type') === 'customer'),
                    ])
                    ->columns(2),

                // قسم جديد للبنود
                Forms\Components\Section::make('Invoice Items')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship('items')
                            ->schema([
                                Forms\Components\TextInput::make('description')
                                    ->label('Description')
                                    ->required()
                                    ->placeholder('Service or product description')
                                    ->columnSpan(3),

                                Forms\Components\Select::make('item_type')
                                    ->label('Type')
                                    ->options([
                                        'shipment' => 'Shipment',
                                        'service' => 'Service',
                                        'fee' => 'Fee',
                                        'commission' => 'Commission',
                                        'bonus' => 'Bonus',
                                    ])
                                    ->default('service')
                                    ->required(),

                                Forms\Components\TextInput::make('quantity')
                                    ->numeric()
                                    ->default(1)
                                    ->required()
                                    ->minValue(1)
                                    ->live()
                                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                        $unitPrice = floatval($get('unit_price') ?? 0);
                                        $quantity = floatval($state ?? 1);
                                        $total = $unitPrice * $quantity;
                                        $set('total_price', number_format($total, 2, '.', ''));
                                    }),

                                Forms\Components\TextInput::make('unit_price')
                                    ->label('Unit Price')
                                    ->numeric()
                                    ->prefix('E£')
                                    ->required()
                                    ->step(0.01)
                                    ->live()
                                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                        $unitPrice = floatval($state ?? 0);
                                        $quantity = floatval($get('quantity') ?? 1);
                                        $total = $unitPrice * $quantity;
                                        $set('total_price', number_format($total, 2, '.', ''));
                                    }),

                                Forms\Components\TextInput::make('total_price')
                                    ->label('Total')
                                    ->numeric()
                                    ->prefix('E£')
                                    ->disabled()
                                    ->dehydrated(),

                                Forms\Components\TextInput::make('tracking_number')
                                    ->label('Tracking #')
                                    ->placeholder('Optional')
                                    ->visible(fn (Forms\Get $get) => $get('item_type') === 'shipment'),

                                Forms\Components\Textarea::make('notes')
                                    ->label('Item Notes')
                                    ->rows(2)
                                    ->placeholder('Optional notes')
                                    ->columnSpan(2),
                            ])
                            ->columns(4)
                            ->addActionLabel('Add Item')
                            ->defaultItems(1)
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                // حساب المجموع الفرعي
                                $subtotal = collect($state)->sum(function ($item) {
                                    return floatval($item['total_price'] ?? 0);
                                });

                                $set('subtotal', number_format($subtotal, 2, '.', ''));

                                // حساب الضريبة
                                $taxRate = floatval($get('tax_rate') ?? 14);
                                $taxAmount = ($subtotal * $taxRate) / 100;
                                $set('tax_amount', number_format($taxAmount, 2, '.', ''));

                                // حساب الإجمالي
                                $discount = floatval($get('discount_amount') ?? 0);
                                $total = $subtotal + $taxAmount - $discount;
                                $set('total_amount', number_format($total, 2, '.', ''));
                                $set('remaining_amount', number_format($total, 2, '.', ''));
                            }),
                    ])
                    ->columnSpanFull(),

                Forms\Components\Section::make('Financial Details')
                    ->schema([
                        Forms\Components\TextInput::make('subtotal')
                            ->label('Subtotal')
                            ->numeric()
                            ->prefix('E£')
                            ->default(0)
                            ->disabled()
                            ->dehydrated(),

                        Forms\Components\TextInput::make('tax_rate')
                            ->label('Tax Rate (%)')
                            ->numeric()
                            ->suffix('%')
                            ->default(14)
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, $state) {
                                $subtotal = floatval($get('subtotal') ?? 0);
                                $taxRate = floatval($state ?? 0);
                                $discount = floatval($get('discount_amount') ?? 0);

                                $taxAmount = ($subtotal * $taxRate) / 100;
                                $total = $subtotal + $taxAmount - $discount;

                                $set('tax_amount', number_format($taxAmount, 2, '.', ''));
                                $set('total_amount', number_format($total, 2, '.', ''));
                                $set('remaining_amount', number_format($total, 2, '.', ''));
                            }),

                        Forms\Components\TextInput::make('tax_amount')
                            ->label('Tax Amount')
                            ->numeric()
                            ->prefix('E£')
                            ->default(0)
                            ->disabled()
                            ->dehydrated(),

                        Forms\Components\TextInput::make('discount_amount')
                            ->label('Discount')
                            ->numeric()
                            ->prefix('E£')
                            ->default(0)
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, $state) {
                                $subtotal = floatval($get('subtotal') ?? 0);
                                $taxAmount = floatval($get('tax_amount') ?? 0);
                                $discount = floatval($state ?? 0);

                                $total = $subtotal + $taxAmount - $discount;
                                $set('total_amount', number_format($total, 2, '.', ''));
                                $set('remaining_amount', number_format($total, 2, '.', ''));
                            }),

                        Forms\Components\TextInput::make('total_amount')
                            ->label('Total Amount')
                            ->numeric()
                            ->prefix('E£')
                            ->default(0)
                            ->disabled()
                            ->dehydrated(),

                        Forms\Components\TextInput::make('paid_amount')
                            ->label('Paid Amount')
                            ->numeric()
                            ->prefix('E£')
                            ->default(0)
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, $state) {
                                $total = floatval($get('total_amount') ?? 0);
                                $paid = floatval($state ?? 0);
                                $remaining = $total - $paid;
                                $set('remaining_amount', number_format($remaining, 2, '.', ''));
                            }),

                        Forms\Components\TextInput::make('remaining_amount')
                            ->label('Remaining Amount')
                            ->numeric()
                            ->prefix('E£')
                            ->default(0)
                            ->disabled()
                            ->dehydrated(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Dates')
                    ->schema([
                        Forms\Components\DatePicker::make('issue_date')
                            ->label('Issue Date')
                            ->required()
                            ->default(today()),

                        Forms\Components\DatePicker::make('due_date')
                            ->label('Due Date')
                            ->required()
                            ->default(today()->addDays(30)),

                        Forms\Components\DateTimePicker::make('paid_at')
                            ->label('Paid At')
                            ->visible(fn (Forms\Get $get) => $get('status') === 'paid'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Additional Information')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull()
                            ->placeholder('Any additional notes or instructions'),

                        Forms\Components\DatePicker::make('period_from')
                            ->label('Service Period From'),

                        Forms\Components\DatePicker::make('period_to')
                            ->label('Service Period To'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('Invoice #')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\BadgeColumn::make('type')
                    ->formatStateUsing(fn (string $state): string => match($state) {
                        'customer' => 'Customer',
                        'driver_commission' => 'Driver Commission',
                        default => ucfirst($state),
                    })
                    ->colors([
                        'primary' => 'customer',
                        'success' => 'driver_commission',
                    ]),

                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Customer')
                    ->searchable()
                    ->placeholder('N/A')
                    ->description(fn ($record) => $record->customer?->email),

                Tables\Columns\TextColumn::make('driver.name')
                    ->label('Driver')
                    ->searchable()
                    ->placeholder('N/A'),

                Tables\Columns\TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('EGP')
                    ->sortable(),

                Tables\Columns\TextColumn::make('paid_amount')
                    ->label('Paid')
                    ->money('EGP')
                    ->sortable(),

                Tables\Columns\TextColumn::make('remaining_amount')
                    ->label('Remaining')
                    ->money('EGP')
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success')
                    ->weight(fn ($state) => $state > 0 ? 'bold' : 'normal'),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'gray' => 'draft',
                        'info' => 'sent',
                        'success' => 'paid',
                        'danger' => 'overdue',
                        'warning' => 'cancelled',
                    ]),

                Tables\Columns\TextColumn::make('issue_date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('due_date')
                    ->date()
                    ->sortable()
                    ->color(fn ($record) => $record->isOverdue() ? 'danger' : null),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'customer' => 'Customer Invoice',
                        'driver_commission' => 'Driver Commission',
                    ]),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'sent' => 'Sent',
                        'paid' => 'Paid',
                        'overdue' => 'Overdue',
                        'cancelled' => 'Cancelled',
                    ]),

                Tables\Filters\Filter::make('overdue')
                    ->label('Overdue Only')
                    ->query(fn (Builder $query): Builder => $query->where('due_date', '<', now())->where('status', '!=', 'paid')),

                Tables\Filters\Filter::make('unpaid')
                    ->label('Unpaid Only')
                    ->query(fn (Builder $query): Builder => $query->where('status', '!=', 'paid')),

                Tables\Filters\Filter::make('has_items')
                    ->label('With Items')
                    ->query(fn (Builder $query): Builder => $query->whereHas('items')),
            ])
            ->actions([
                Tables\Actions\Action::make('addPayment')
                    ->label('Add Payment')
                    ->icon('heroicon-m-banknotes')
                    ->color('success')
                    ->visible(fn ($record) => $record->remaining_amount > 0 && $record->status !== 'paid')
                    ->form([
                        Forms\Components\TextInput::make('amount')
                            ->label('Payment Amount')
                            ->numeric()
                            ->prefix('E£')
                            ->required()
                            ->default(fn ($record) => $record->remaining_amount)
                            ->helperText(fn ($record) => "Remaining amount: E£" . number_format($record->remaining_amount, 2)),

                        Forms\Components\Select::make('payment_method')
                            ->label('Payment Method')
                            ->options([
                                'cash' => '💵 Cash',
                                'bank_transfer' => '🏦 Bank Transfer',
                                'credit_card' => '💳 Credit Card',
                                'vodafone_cash' => '📱 Vodafone Cash',
                                'orange_cash' => '🍊 Orange Cash',
                                'etisalat_cash' => '📞 Etisalat Cash',
                            ])
                            ->required(),

                        Forms\Components\TextInput::make('reference')
                            ->label('Reference Number')
                            ->placeholder('Transaction reference (optional)'),

                        Forms\Components\Textarea::make('notes')
                            ->label('Payment Notes')
                            ->placeholder('Any additional notes'),
                    ])
                    ->action(function ($record, array $data) {
                        $record->addPayment(
                            $data['amount'],
                            $data['payment_method'],
                            [
                                'reference' => $data['reference'],
                                'notes' => $data['notes'],
                            ]
                        );

                        \Filament\Notifications\Notification::make()
                            ->title('Payment Added Successfully')
                            ->body("E£{$data['amount']} payment recorded for invoice {$record->invoice_number}")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('downloadPDF')
                    ->label('Download PDF')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->color('primary')
                    ->url(fn ($record) => route('invoice.pdf', $record))
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('previewItems')
                    ->label('View Items')
                    ->icon('heroicon-m-list-bullet')
                    ->color('info')
                    ->modalHeading(fn ($record) => "Items for Invoice {$record->invoice_number}")
                    ->modalContent(fn ($record) => view('filament.modals.invoice-items', [
                        'items' => $record->items
                    ]))
                    ->modalWidth('4xl'),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),

                    Tables\Actions\BulkAction::make('markAsSent')
                        ->label('Mark as Sent')
                        ->icon('heroicon-m-paper-airplane')
                        ->color('info')
                        ->action(function ($records) {
                            $records->each(fn ($record) => $record->update(['status' => 'sent']));

                            \Filament\Notifications\Notification::make()
                                ->title('Invoices Updated')
                                ->body(count($records) . ' invoices marked as sent')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('downloadPDFs')
                        ->label('Download PDFs')
                        ->icon('heroicon-m-arrow-down-tray')
                        ->color('primary')
                        ->action(function ($records) {
                            // Future: ZIP download functionality
                            \Filament\Notifications\Notification::make()
                                ->title('Feature Coming Soon')
                                ->body('Bulk PDF download will be available soon')
                                ->info()
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoices::route('/'),
            'create' => Pages\CreateInvoice::route('/create'),
            'edit' => Pages\EditInvoice::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'overdue')->count() ?: null;
    }
}
