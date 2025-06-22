<?php

namespace App\Filament\Resources\ShipmentResource\Pages;

use App\Filament\Resources\ShipmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables;
use Filament\Forms;

class InHouseShipments extends ListRecords
{
    protected static string $resource = ShipmentResource::class;

    protected static ?string $title = 'In House Shipments';


    protected static ?string $navigationLabel = 'In House';

    public function getTableQuery(): Builder
    {
        return ShipmentResource::getEloquentQueryForStatus('in_transit');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New Shipment')
                ->url(ShipmentResource::getUrl('create')),

            // Only logical header actions for In House
            Actions\Action::make('exportAll')
                ->label('Export All In House')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    $records = $this->getTableQuery()->with(['seller', 'driver'])->get();
                    $filename = 'in_house_shipments_' . now()->format('Y-m-d_H-i-s') . '.xlsx';

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
            '#' => 'In House',
        ];
    }

    // Override the table to remove header actions and customize bulk actions
    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns(ShipmentResource::table($table)->getColumns())
            ->filters(ShipmentResource::table($table)->getFilters())
            ->headerActions([]) // Remove default header actions
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
                Tables\Actions\BulkAction::make('assignDriver')
                    ->label('Assign Driver')
                    ->icon('heroicon-m-user-plus')
                    ->color('success')
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
                                'status' => 'out_for_delivery'
                            ]);
                        });

                        \Filament\Notifications\Notification::make()
                            ->title('Shipments Assigned Successfully')
                            ->body(count($records) . ' shipments have been assigned to driver.')
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation(),

                Tables\Actions\BulkAction::make('exportSelected')
                    ->label('Export Selected')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(function ($records) {
                        $filename = 'in_house_selected_' . now()->format('Y-m-d_H-i-s') . '.xlsx';
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
                        } else {
                            return response()->streamDownload(function () use ($shipments) {
                                $billContent = \App\Http\Controllers\BillOfLadingController::generateMultipleBillsContent($shipments);
                                echo $billContent;
                            }, 'Bills_of_Lading_' . count($shipments) . '_shipments.docx');
                        }
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
