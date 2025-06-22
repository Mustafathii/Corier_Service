<?php

namespace App\Filament\Resources\ShipmentResource\Pages;

use App\Filament\Resources\ShipmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables;

class DeliveredShipments extends ListRecords
{
    protected static string $resource = ShipmentResource::class;

    protected static ?string $title = 'Delivered Shipments';
    protected static ?string $navigationLabel = 'Delivered';

    public function getTableQuery(): Builder
    {
        return ShipmentResource::getEloquentQueryForStatus('delivered');
    }

    protected function getHeaderActions(): array
    {
        return [
            // NO Create button for delivered page - doesn't make sense
            Actions\Action::make('exportAll')
                ->label('Export All Delivered')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    $records = $this->getTableQuery()->with(['seller', 'driver'])->get();
                    $filename = 'delivered_shipments_' . now()->format('Y-m-d_H-i-s') . '.xlsx';

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
            '#' => 'Delivered',
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

                Tables\Actions\ViewAction::make()
                    ->color('gray'),
                // NO EDIT OR DELETE for delivered shipments
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('exportSelected')
                    ->label('Export Selected')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(function ($records) {
                        $filename = 'delivered_selected_' . now()->format('Y-m-d_H-i-s') . '.xlsx';
                        return \Maatwebsite\Excel\Facades\Excel::download(
                            new \App\Exports\ShipmentsExport($records),
                            $filename
                        );
                    }),

                Tables\Actions\BulkAction::make('generateBills')
                    ->label('Print Bills')
                    ->icon('heroicon-o-document-text')
                    ->color('primary')
                    ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                        $shipments = $records->load(['seller', 'driver']);

                        if (count($shipments) === 1) {
                            return response()->streamDownload(function () use ($shipments) {
                                $shipment = $shipments->first();
                                $billContent = \App\Http\Controllers\BillOfLadingController::generateBillContent($shipment);
                                echo $billContent;
                            }, 'Bill_of_Lading_' . $shipments->first()->tracking_number . '.docx');
                        }
                    }),
                // NO ASSIGN DRIVER, NO STATUS CHANGES - these don't make sense for delivered
            ])
            ->defaultSort('created_at', 'desc');

    }

}
