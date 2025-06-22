<?php

namespace App\Filament\Widgets;

use App\Models\Invoice;
use App\Models\Payment;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FinancialDashboardWidget extends BaseWidget
{
    protected static ?int $sort = 2;
    protected static ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        return [
            Stat::make('📊 Total Revenue', 'E£ ' . number_format($this->getTotalRevenue()))
                ->description('All customer invoices')
                ->color('success'),

            Stat::make('💰 Payments Received', 'E£ ' . number_format($this->getPaymentsReceived()))
                ->description('Completed payments')
                ->color('info'),

            Stat::make('📋 Outstanding', 'E£ ' . number_format($this->getOutstanding()))
                ->description('Unpaid invoices')
                ->color('warning'),

            Stat::make('🚗 Driver Commissions', 'E£ ' . number_format($this->getCommissions()))
                ->description('Total commissions')
                ->color('primary'),

            Stat::make('📈 This Month Revenue', 'E£ ' . number_format($this->getMonthlyRevenue()))
                ->description('Current month')
                ->color('success'),

            Stat::make('⚠️ Overdue Invoices', $this->getOverdueCount())
                ->description('Need attention')
                ->color('danger'),
        ];
    }

    private function getTotalRevenue(): float
    {
        return Invoice::where('type', 'customer')->sum('total_amount');
    }

    private function getPaymentsReceived(): float
    {
        return Payment::where('status', 'completed')->sum('amount');
    }

    private function getOutstanding(): float
    {
        return Invoice::where('type', 'customer')
            ->whereIn('status', ['sent', 'overdue'])
            ->sum('remaining_amount');
    }

    private function getCommissions(): float
    {
        return Invoice::where('type', 'driver_commission')->sum('total_amount');
    }

    private function getMonthlyRevenue(): float
    {
        return Invoice::where('type', 'customer')
            ->whereMonth('issue_date', now()->month)
            ->sum('total_amount');
    }

    private function getOverdueCount(): int
    {
        return Invoice::where('type', 'customer')
            ->where('due_date', '<', now())
            ->where('status', '!=', 'paid')
            ->count();
    }
}
