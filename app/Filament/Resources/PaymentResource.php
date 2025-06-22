<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Payment;
use App\Models\Invoice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Payments';
    protected static ?string $navigationGroup = 'Accounting';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Payment Information')
                    ->schema([
                        Forms\Components\Select::make('invoice_id')
                            ->label('Invoice')
                            ->options(function () {
                                return Invoice::where('type', 'customer')
                                    ->where('status', '!=', 'paid')
                                    ->where('remaining_amount', '>', 0)
                                    ->get()
                                    ->mapWithKeys(function ($invoice) {
                                        return [
                                            $invoice->id => "{$invoice->invoice_number} - {$invoice->customer_name} - Remaining: E£" . number_format($invoice->remaining_amount, 2)
                                        ];
                                    });
                            })
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set, $state) {
                                if ($state) {
                                    $invoice = Invoice::find($state);
                                    if ($invoice) {
                                        $set('amount', $invoice->remaining_amount);
                                    }
                                }
                            }),

                        Forms\Components\TextInput::make('amount')
                            ->label('Payment Amount')
                            ->numeric()
                            ->prefix('E£')
                            ->required()
                            ->step(0.01)
                            ->minValue(0.01),

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
                            ->required()
                            ->live(),

                        Forms\Components\DateTimePicker::make('payment_date')
                            ->label('Payment Date')
                            ->default(now())
                            ->required(),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'pending' => 'Pending',
                                'completed' => 'Completed',
                                'failed' => 'Failed',
                                'refunded' => 'Refunded',
                            ])
                            ->default('completed')
                            ->required(),

                        Forms\Components\TextInput::make('reference_number')
                            ->label('Reference/Transaction Number')
                            ->placeholder('Enter reference number')
                            ->visible(fn (Forms\Get $get) =>
                                in_array($get('payment_method'), ['bank_transfer', 'credit_card', 'vodafone_cash', 'orange_cash', 'etisalat_cash'])
                            ),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Additional Information')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label('Payment Notes')
                            ->rows(3)
                            ->placeholder('Any additional notes about this payment')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('payment_number')
                    ->label('Payment #')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('invoice.invoice_number')
                    ->label('Invoice')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('invoice.customer_name')
                    ->label('Customer')
                    ->searchable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->money('EGP')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('payment_method')
                    ->label('Method')
                    ->formatStateUsing(fn (string $state): string => match($state) {
                        'cash' => 'Cash',
                        'bank_transfer' => 'Bank Transfer',
                        'credit_card' => 'Credit Card',
                        'vodafone_cash' => 'Vodafone Cash',
                        'orange_cash' => 'Orange Cash',
                        'etisalat_cash' => 'Etisalat Cash',
                        default => ucfirst(str_replace('_', ' ', $state)),
                    })
                    ->colors([
                        'success' => 'cash',
                        'info' => 'bank_transfer',
                        'warning' => 'credit_card',
                        'primary' => ['vodafone_cash', 'orange_cash', 'etisalat_cash'],
                    ]),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'completed',
                        'danger' => 'failed',
                        'gray' => 'refunded',
                    ]),

                Tables\Columns\TextColumn::make('payment_date')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('reference_number')
                    ->label('Reference')
                    ->placeholder('N/A'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('payment_method')
                    ->options([
                        'cash' => 'Cash',
                        'bank_transfer' => 'Bank Transfer',
                        'credit_card' => 'Credit Card',
                        'vodafone_cash' => 'Vodafone Cash',
                        'orange_cash' => 'Orange Cash',
                    ]),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                        'refunded' => 'Refunded',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->defaultSort('payment_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
            'create' => Pages\CreatePayment::route('/create'),
            'edit' => Pages\EditPayment::route('/{record}/edit'),
        ];
    }
}
