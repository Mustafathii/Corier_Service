<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class FinancialReports extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Financial Reports';
    protected static ?string $navigationGroup = 'Accounting';
    protected static ?int $navigationSort = 2;

    // إزالة السطر ده تماماً:
    // protected static string $view = 'filament.pages.financial-reports';

    // بدلاً منه استخدم:
    protected static string $view = 'filament.pages.financial-reports';

    public function getViewData(): array
    {
        return [
            'totalRevenue' => \App\Models\Invoice::where('type', 'customer')->sum('total_amount'),
            'paymentsReceived' => \App\Models\Payment::where('status', 'completed')->sum('amount'),
            'outstanding' => \App\Models\Invoice::whereIn('status', ['sent', 'overdue'])->sum('remaining_amount'),
            'commissions' => \App\Models\Invoice::where('type', 'driver_commission')->sum('total_amount'),
        ];
    }
}
