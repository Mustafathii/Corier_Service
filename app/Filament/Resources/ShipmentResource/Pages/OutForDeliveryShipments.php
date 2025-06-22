<?php

namespace App\Filament\Resources\ShipmentResource\Pages;

use App\Filament\Resources\ShipmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables;
use Filament\Forms;

class OutForDeliveryShipments extends ListRecords
{
    protected static string $resource = ShipmentResource::class;

    protected static ?string $title = 'Out for Delivery';


    protected static ?string $navigationLabel = 'Out for Delivery';

    public function getTableQuery(): Builder
    {
        return ShipmentResource::getEloquentQueryForStatus('out_for_delivery');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New Shipment')
                ->url(ShipmentResource::getUrl('create')),

            Actions\Action::make('exportAll')
                ->label('Export All Out for Delivery')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    $records = $this->getTableQuery()->with(['seller', 'driver'])->get();
                    $filename = 'out_for_delivery_' . now()->format('Y-m-d_H-i-s') . '.xlsx';

                    return \Maatwebsite\Excel\Facades\Excel::download(
                        new \App\Exports\ShipmentsExport($records),
                        $filename
                    );
                }),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            ShipmentResource::getUrl() => 'Shipments',
            '#' => 'Out for Delivery',
        ];
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns(ShipmentResource::table($table)->getColumns())
            ->filters(ShipmentResource::table($table)->getFilters())
            ->headerActions([])
            ->actions([
                Tables\Actions\Action::make('printBillOfLading')
                    ->label('Print')
                    ->icon('heroicon-m-document-text')
                    ->url(fn (\App\Models\Shipment $record): string => route('bill-of-lading.generate', $record->id))
                    ->openUrlInNewTab()
                    ->button()
                    ->color('primary'),

                Tables\Actions\Action::make('viewHistory')
                    ->label('History')
                    ->icon('heroicon-m-clock')
                    ->color('info')
                    ->modalHeading(fn (\App\Models\Shipment $record) => 'History for ' . $record->tracking_number)
                    ->modalContent(fn (\App\Models\Shipment $record) => view('filament.modals.shipment-history', [
                        'shipment' => $record,
                        'histories' => $record->histories()->with('user')->get()
                    ]))
                    ->modalWidth('4xl')
                    ->button(),

                Tables\Actions\EditAction::make()
                    ->visible(fn () => auth()->user()->can('edit_shipments')),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('markDelivered')
                    ->label('Mark as Delivered')
                    ->icon('heroicon-m-check-circle')
                    ->color('success')
                    ->action(function ($records) {
                        $records->each(function ($record) {
                            $record->update([
                                'status' => 'delivered',
                                'actual_delivery_date' => now()
                            ]);
                        });

                        \Filament\Notifications\Notification::make()
                            ->title('Shipments Delivered')
                            ->body(count($records) . ' shipments marked as delivered.')
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation(),

                Tables\Actions\BulkAction::make('markFailed')
                    ->label('Mark as Failed Delivery')
                    ->icon('heroicon-m-x-circle')
                    ->color('danger')
                    ->action(function ($records) {
                        $records->each(function ($record) {
                            $record->update(['status' => 'failed_delivery']);
                        });

                        \Filament\Notifications\Notification::make()
                            ->title('Delivery Failed')
                            ->body(count($records) . ' shipments marked as failed delivery.')
                            ->warning()
                            ->send();
                    })
                    ->requiresConfirmation(),

                Tables\Actions\BulkAction::make('exportSelected')
                    ->label('Export Selected')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(function ($records) {
                        $filename = 'out_for_delivery_selected_' . now()->format('Y-m-d_H-i-s') . '.xlsx';
                        return \Maatwebsite\Excel\Facades\Excel::download(
                            new \App\Exports\ShipmentsExport($records),
                            $filename
                        );
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
