<?php

namespace App\Filament\Resources\ShipmentResource\Pages;

use App\Filament\Resources\ShipmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables;

class CanceledShipments extends ListRecords
{
    protected static string $resource = ShipmentResource::class;

    protected static ?string $title = 'Canceled & Returned Shipments';
    protected static ?string $navigationLabel = 'Canceled';

    public function getTableQuery(): Builder
    {
        return ShipmentResource::getEloquentQueryForStatus('canceled');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('exportAll')
                ->label('Export All Canceled')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    $records = $this->getTableQuery()->with(['seller', 'driver'])->get();
                    $filename = 'canceled_shipments_' . now()->format('Y-m-d_H-i-s') . '.xlsx';

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
            '#' => 'Canceled & Returned',
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

                Tables\Actions\ViewAction::make()
                    ->color('gray'),

                Tables\Actions\Action::make('reactivate')
                    ->label('Reactivate')
                    ->icon('heroicon-m-arrow-path')
                    ->color('warning')
                    ->action(function (\App\Models\Shipment $record) {
                        // Update the shipment status
                        $record->update([
                            'status' => 'pending',
                            'driver_id' => null, // Remove any assigned driver
                        ]);

                        // Show success notification
                        \Filament\Notifications\Notification::make()
                            ->title('Shipment Reactivated')
                            ->body("Shipment {$record->tracking_number} has been reactivated and moved to pending.")
                            ->success()
                            ->send();

                        // Force refresh the table to remove the record
                        $this->resetTable();
                        return;
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Reactivate Shipment')
                    ->modalDescription('This will change the shipment status from canceled to pending and remove it from this list.')
                    ->modalSubmitActionLabel('Yes, Reactivate')
                    ->visible(fn (\App\Models\Shipment $record) => $record->status === 'canceled'),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn () => auth()->user()->can('delete_shipments'))
                    ->successNotification(
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Shipment deleted')
                            ->body('The shipment has been permanently deleted.')
                    ),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('exportSelected')
                    ->label('Export Selected')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(function ($records) {
                        $filename = 'canceled_selected_' . now()->format('Y-m-d_H-i-s') . '.xlsx';
                        return \Maatwebsite\Excel\Facades\Excel::download(
                            new \App\Exports\ShipmentsExport($records),
                            $filename
                        );
                    }),

                Tables\Actions\BulkAction::make('reactivateSelected')
                    ->label('Reactivate Selected')
                    ->icon('heroicon-m-arrow-path')
                    ->color('warning')
                    ->action(function ($records) {
                        $reactivatedCount = 0;

                        foreach ($records as $record) {
                            if (in_array($record->status, ['canceled', 'returned'])) {
                                $record->update([
                                    'status' => 'pending',
                                    'driver_id' => null,
                                ]);
                                $reactivatedCount++;
                            }
                        }

                        \Filament\Notifications\Notification::make()
                            ->title('Shipments Reactivated')
                            ->body("{$reactivatedCount} shipments have been reactivated and moved to pending.")
                            ->success()
                            ->send();

                        // Force refresh the table
                        $this->resetTable();
                        return;
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Reactivate Selected Shipments')
                    ->modalDescription('This will change the selected shipments status to pending and remove them from this list.')
                    ->modalSubmitActionLabel('Yes, Reactivate All')
                    ->deselectRecordsAfterCompletion(),

                Tables\Actions\DeleteBulkAction::make()
                    ->visible(fn () => auth()->user()->can('delete_shipments'))
                    ->modalHeading('Delete Selected Shipments')
                    ->modalDescription('Are you sure you want to permanently delete these shipments? This action cannot be undone.')
                    ->modalSubmitActionLabel('Yes, Delete Forever'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    // Add method to reset/refresh the table
    public function resetTable(): void
    {
        $this->resetPage();
        $this->dispatch('$refresh');
    }
}
