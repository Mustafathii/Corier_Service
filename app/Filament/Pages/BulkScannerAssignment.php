<?php

namespace App\Filament\Pages;

use App\Models\Shipment;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;

class BulkScannerAssignment extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-qr-code';
    protected static ?string $navigationLabel = 'Scanner Assignment';
    protected static ?string $title = 'Live Scanner Assignment';
    protected static ?int $navigationSort = 6;
    protected static ?string $navigationGroup = 'Shipments Management';
    protected static string $view = 'filament.pages.bulk-scanner-assignment';

    public ?array $data = [];
    public array $scannedShipments = [];
    public int $successCount = 0;
    public int $failedCount = 0;

    public static function canAccess(): bool
    {
        return auth()->user()->can('assign_shipments') ||
               auth()->user()->hasAnyRole(['Admin', 'Operations Manager']);
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('👤 Driver Selection')
                    ->description('Select a driver first, then start scanning')
                    ->schema([
                        Select::make('driver_id')
                            ->label('Select Driver')
                            ->placeholder('Choose a driver to assign shipments to')
                            ->options(function () {
                                return User::whereHas('roles', function ($q) {
                                    $q->where('name', 'Driver');
                                })->where('is_active', true)->pluck('name', 'id');
                            })
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function () {
                                $this->resetScanning();
                            }),
                    ])
                    ->collapsible()
                    ->compact(),

                Section::make('📱 Live Scanner Interface')
                    ->description('High-tech scanner ready for action!')
                    ->visible(fn () => !empty($this->data['driver_id']))
                    ->schema([
                        Placeholder::make('scanner_interface')
                            ->label('')
                            ->content(fn () => new \Illuminate\Support\HtmlString('
                                <div id="scanner-container" class="space-y-6">
                                    <!-- Scanner Input Section -->
                                    <div class="relative">
                                        <div class="absolute inset-0 bg-gradient-to-r from-blue-600 to-purple-600 rounded-2xl blur opacity-20"></div>
                                        <div class="relative bg-white rounded-2xl p-6 border border-gray-200 shadow-lg">
                                            <div class="flex items-center space-x-3 mb-4">
                                                <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center">
                                                    <i class="fas fa-qrcode text-white"></i>
                                                </div>
                                                <div>
                                                    <h3 class="text-lg font-bold text-gray-800">Scanner Input</h3>
                                                    <p class="text-sm text-gray-500">Click here and scan barcode</p>
                                                </div>
                                            </div>

                                            <input
                                                type="text"
                                                id="scanner-input"
                                                placeholder="🔍 Ready to scan... Click here and scan barcode"
                                                class="w-full px-4 py-4 text-md font-mono border-2 border-gray-300 rounded-xl focus:outline-none focus:ring-4 focus:ring-blue-500 focus:border-blue-500 transition-all duration-300 bg-gray-50"
                                                autocomplete="off"
                                            />

                                            <div class="flex items-center justify-between mt-3">
                                                <p class="text-sm text-gray-500">
                                                    <i class="fas fa-info-circle mr-1"></i>
                                                    Scanner will automatically process each barcode
                                                </p>
                                                <div class="flex items-center space-x-2 text-xs text-gray-400">
                                                    <span class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></span>
                                                    <span>Live</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Stats Dashboard -->
                                    <div class="grid grid-cols-3 gap-6">
                                        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-green-500 to-emerald-600 p-6 text-white shadow-lg">
                                            <div class="absolute top-0 right-0 w-20 h-20 -mt-10 -mr-10 bg-white bg-opacity-20 rounded-full"></div>
                                            <div class="relative">
                                                <div class="flex items-center justify-between mb-2">
                                                    <i class="fas fa-check-circle text-2xl opacity-80"></i>
                                                    <span class="text-xs bg-white bg-opacity-20 px-2 py-1 rounded-full">Valid</span>
                                                </div>
                                                <div class="text-3xl font-bold" id="success-count">0</div>
                                                <div class="text-sm opacity-90">Successful Scans</div>
                                            </div>
                                        </div>

                                        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-red-500 to-pink-600 p-6 text-white shadow-lg">
                                            <div class="absolute top-0 right-0 w-20 h-20 -mt-10 -mr-10 bg-white bg-opacity-20 rounded-full"></div>
                                            <div class="relative">
                                                <div class="flex items-center justify-between mb-2">
                                                    <i class="fas fa-times-circle text-2xl opacity-80"></i>
                                                    <span class="text-xs bg-white bg-opacity-20 px-2 py-1 rounded-full">Failed</span>
                                                </div>
                                                <div class="text-3xl font-bold" id="failed-count">0</div>
                                                <div class="text-sm opacity-90">Failed Scans</div>
                                            </div>
                                        </div>

                                        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-blue-500 to-indigo-600 p-6 text-white shadow-lg">
                                            <div class="absolute top-0 right-0 w-20 h-20 -mt-10 -mr-10 bg-white bg-opacity-20 rounded-full"></div>
                                            <div class="relative">
                                                <div class="flex items-center justify-between mb-2">
                                                    <i class="fas fa-list text-2xl opacity-80"></i>
                                                    <span class="text-xs bg-white bg-opacity-20 px-2 py-1 rounded-full">Total</span>
                                                </div>
                                                <div class="text-3xl font-bold" id="total-count">0</div>
                                                <div class="text-sm opacity-90">Total Scanned</div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Loading Indicator -->
                                    <div id="loading-indicator" class="hidden">
                                        <div class="flex items-center justify-center p-6 bg-white rounded-2xl border border-gray-200 shadow-lg">
                                            <div class="flex items-center space-x-3">
                                                <div class="relative">
                                                    <div class="w-8 h-8 border-4 border-blue-200 rounded-full"></div>
                                                    <div class="w-8 h-8 border-4 border-blue-600 border-t-transparent rounded-full animate-spin absolute top-0"></div>
                                                </div>
                                                <div>
                                                    <div class="text-lg font-semibold text-gray-800">Processing...</div>
                                                    <div class="text-sm text-gray-500">Checking tracking number</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Recent Scans List -->
                                    <div class="bg-white rounded-2xl border border-gray-200 shadow-lg overflow-hidden">
                                        <div class="bg-gradient-to-r from-gray-50 to-gray-100 px-6 py-4 border-b border-gray-200">
                                            <div class="flex items-center space-x-3">
                                                <div class="w-8 h-8 bg-gradient-to-r from-blue-500 to-purple-600 rounded-lg flex items-center justify-center">
                                                    <i class="fas fa-history text-white text-sm"></i>
                                                </div>
                                                <h4 class="text-lg font-bold text-gray-800">Recent Scans</h4>
                                                <span class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded-full">Live Updates</span>
                                            </div>
                                        </div>

                                        <div class="max-h-96 overflow-y-auto p-4">
                                            <div id="scans-list">
                                                <div id="empty-state" class="text-center py-12 text-gray-500">
                                                    <div class="bg-gradient-to-br from-gray-50 to-gray-100 rounded-2xl p-8 mx-auto max-w-md border border-gray-200">
                                                        <div class="w-16 h-16 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full mx-auto mb-4 flex items-center justify-center shadow-lg">
                                                            <i class="fas fa-qrcode text-white text-2xl"></i>
                                                        </div>
                                                        <h3 class="text-lg font-semibold text-gray-700 mb-2">Ready to Scan!</h3>
                                                        <p class="text-sm text-gray-500">Start scanning barcodes to see live results here</p>
                                                        <div class="mt-4 flex justify-center space-x-1">
                                                            <div class="w-2 h-2 bg-blue-400 rounded-full animate-pulse"></div>
                                                            <div class="w-2 h-2 bg-purple-400 rounded-full animate-pulse" style="animation-delay: 0.2s"></div>
                                                            <div class="w-2 h-2 bg-blue-400 rounded-full animate-pulse" style="animation-delay: 0.4s"></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            ')),

                        Actions::make([
                            Action::make('reset_session')
                                ->label('🔄 Reset Session')
                                ->icon('heroicon-o-arrow-path')
                                ->color('gray')
                                ->size('lg')
                                ->action('resetScanning'),

                            Action::make('assign_all_valid')
                                ->label('✅ Assign All Valid Shipments')
                                ->icon('heroicon-o-check-circle')
                                ->color('success')
                                ->size('lg')
                                ->visible(fn () => count(array_filter($this->scannedShipments, fn($item) => $item['status'] === 'valid')) > 0)
                                ->action('assignAllValidShipments'),
                        ])->fullWidth(),
                    ])
                    ->collapsible(),
            ])
            ->statePath('data');
    }

    // AJAX endpoint للتحقق من رقم التتبع
    public function checkTrackingNumber()
    {
        $request = request();
        $trackingNumber = $request->input('tracking_number');
        $driverId = $request->input('driver_id') ?: ($this->data['driver_id'] ?? null);

        if (!$driverId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please select a driver first',
                'color' => 'red'
            ]);
        }

        $result = $this->validateTrackingNumber($trackingNumber, $driverId);

        // إضافة النتيجة للقائمة
        $this->scannedShipments[] = [
            'tracking_number' => $trackingNumber,
            'status' => $result['status'],
            'message' => $result['message'],
            'shipment_info' => $result['shipment_info'] ?? null,
            'timestamp' => now()->format('H:i:s'),
        ];

        // تحديث العدادات
        if ($result['status'] === 'valid') {
            $this->successCount++;
        } else {
            $this->failedCount++;
        }

        return response()->json($result);
    }

    protected function validateTrackingNumber(string $trackingNumber, int $driverId): array
    {
        $shipment = Shipment::where('tracking_number', $trackingNumber)->first();

        // الشحنة غير موجودة
        if (!$shipment) {
            return [
                'status' => 'not_found',
                'message' => '❌ Tracking number not found in system',
                'color' => 'red'
            ];
        }

        // مخصصة بالفعل لنفس السائق
        if ($shipment->driver_id == $driverId) {
            return [
                'status' => 'already_assigned',
                'message' => '⚠️ Already assigned to this driver',
                'color' => 'yellow',
                'shipment_info' => [
                    'recipient' => $shipment->recipient_name,
                    'city' => $shipment->recipient_city,
                    'current_status' => $shipment->status
                ]
            ];
        }

        // لا يمكن تخصيصها بسبب الحالة
        if (in_array($shipment->status, ['delivered', 'canceled', 'returned'])) {
            return [
                'status' => 'cannot_assign',
                'message' => '🚫 Cannot assign - Status: ' . ucfirst($shipment->status),
                'color' => 'red',
                'shipment_info' => [
                    'recipient' => $shipment->recipient_name,
                    'city' => $shipment->recipient_city,
                    'current_status' => $shipment->status
                ]
            ];
        }

        // مخصصة لسائق آخر
        if ($shipment->driver_id && $shipment->driver_id != $driverId) {
            $currentDriver = User::find($shipment->driver_id);
            return [
                'status' => 'assigned_to_other',
                'message' => '👤 Assigned to: ' . ($currentDriver ? $currentDriver->name : 'Unknown Driver'),
                'color' => 'blue',
                'shipment_info' => [
                    'recipient' => $shipment->recipient_name,
                    'city' => $shipment->recipient_city,
                    'current_status' => $shipment->status
                ]
            ];
        }

        // صالحة للتخصيص
        return [
            'status' => 'valid',
            'message' => '✅ Ready to assign',
            'color' => 'green',
            'shipment_info' => [
                'recipient' => $shipment->recipient_name,
                'city' => $shipment->recipient_city,
                'current_status' => $shipment->status
            ]
        ];
    }

    public function assignAllValidShipments(): void
    {
        $driverId = $this->data['driver_id'];
        $driver = User::find($driverId);
        $validShipments = array_filter($this->scannedShipments, fn($item) => $item['status'] === 'valid');

        $assignedCount = 0;

        foreach ($validShipments as $scannedShipment) {
            $shipment = Shipment::where('tracking_number', $scannedShipment['tracking_number'])->first();

            if ($shipment && !in_array($shipment->status, ['delivered', 'canceled', 'returned'])) {
                $shipment->update([
                    'driver_id' => $driverId,
                    'status' => 'out_for_delivery'
                ]);
                $assignedCount++;
            }
        }

        Notification::make()
            ->title('🎉 Assignment Complete!')
            ->body("Successfully assigned {$assignedCount} shipments to {$driver->name}")
            ->success()
            ->persistent()
            ->send();

        // إعادة تعيين الجلسة
        $this->resetScanning();
    }

    public function resetScanning(): void
    {
        $this->scannedShipments = [];
        $this->successCount = 0;
        $this->failedCount = 0;

        $this->dispatch('reset-scanner');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Shipment::whereNull('driver_id')
            ->where('status', 'in_transit')
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    protected function getViewData(): array
    {
        return [
            'scannedShipments' => $this->scannedShipments,
            'successCount' => $this->successCount,
            'failedCount' => $this->failedCount,
        ];
    }
}
