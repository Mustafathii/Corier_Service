<?php

// app/Filament/Widgets/FinancialOverviewWidget.php

namespace App\Filament\Widgets;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Shipment;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Carbon\Carbon;

class FinancialOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;
    protected static ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $currentMonth = now()->startOfMonth();
        $lastMonth = now()->subMonth()->startOfMonth();

        return [
            // إجمالي الإيرادات الشهرية
            Stat::make('Monthly Revenue', $this->formatMoney($this->getMonthlyRevenue()))
                ->description($this->getRevenueChange() . ' from last month')
                ->descriptionIcon($this->getRevenueChange() >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($this->getRevenueChange() >= 0 ? 'success' : 'danger')
                ->chart($this->getRevenueChart()),

            // المدفوعات المستلمة
            Stat::make('Payments Received', $this->formatMoney($this->getPaymentsReceived()))
                ->description($this->getPaymentsChange() . ' from last month')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            // الفواتير المستحقة
            Stat::make('Outstanding Invoices', $this->formatMoney($this->getOutstandingAmount()))
                ->description($this->getOverdueCount() . ' overdue invoices')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($this->getOverdueCount() > 0 ? 'danger' : 'success'),

            // عمولات السائقين
            Stat::make('Driver Commissions', $this->formatMoney($this->getDriverCommissions()))
                ->description('This month')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('info'),

            // معدل التحصيل
            Stat::make('Collection Rate', $this->getCollectionRate() . '%')
                ->description('Invoice collection efficiency')
                ->descriptionIcon('heroicon-m-chart-pie')
                ->color($this->getCollectionRate() >= 80 ? 'success' : 'warning'),

            // صافي الربح
            Stat::make('Net Profit', $this->formatMoney($this->getNetProfit()))
                ->description('Revenue - Commissions - Expenses')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color($this->getNetProfit() >= 0 ? 'success' : 'danger'),
        ];
    }

    private function getMonthlyRevenue(): float
    {
        return Invoice::where('type', 'customer')
            ->whereMonth('issue_date', now()->month)
            ->whereYear('issue_date', now()->year)
            ->sum('total_amount');
    }

    private function getLastMonthRevenue(): float
    {
        return Invoice::where('type', 'customer')
            ->whereMonth('issue_date', now()->subMonth()->month)
            ->whereYear('issue_date', now()->subMonth()->year)
            ->sum('total_amount');
    }

    private function getRevenueChange(): string
    {
        $current = $this->getMonthlyRevenue();
        $last = $this->getLastMonthRevenue();

        if ($last == 0) return '100%';

        $change = (($current - $last) / $last) * 100;
        return ($change >= 0 ? '+' : '') . number_format($change, 1) . '%';
    }

    private function getPaymentsReceived(): float
    {
        return Payment::where('status', 'completed')
            ->whereMonth('payment_date', now()->month)
            ->whereYear('payment_date', now()->year)
            ->sum('amount');
    }

    private function getPaymentsChange(): string
    {
        $current = $this->getPaymentsReceived();
        $last = Payment::where('status', 'completed')
            ->whereMonth('payment_date', now()->subMonth()->month)
            ->whereYear('payment_date', now()->subMonth()->year)
            ->sum('amount');

        if ($last == 0) return '100%';

        $change = (($current - $last) / $last) * 100;
        return ($change >= 0 ? '+' : '') . number_format($change, 1) . '%';
    }

    private function getOutstandingAmount(): float
    {
        return Invoice::where('type', 'customer')
            ->whereIn('status', ['sent', 'overdue'])
            ->sum('remaining_amount');
    }

    private function getOverdueCount(): int
    {
        return Invoice::where('type', 'customer')
            ->where('due_date', '<', now())
            ->where('status', '!=', 'paid')
            ->count();
    }

    private function getDriverCommissions(): float
    {
        return Invoice::where('type', 'driver_commission')
            ->whereMonth('issue_date', now()->month)
            ->whereYear('issue_date', now()->year)
            ->sum('total_amount');
    }

    private function getCollectionRate(): float
    {
        $totalInvoiced = Invoice::where('type', 'customer')
            ->whereMonth('issue_date', now()->month)
            ->sum('total_amount');

        $totalCollected = Invoice::where('type', 'customer')
            ->whereMonth('issue_date', now()->month)
            ->sum('paid_amount');

        return $totalInvoiced > 0 ? ($totalCollected / $totalInvoiced) * 100 : 0;
    }

    private function getNetProfit(): float
    {
        $revenue = $this->getMonthlyRevenue();
        $commissions = $this->getDriverCommissions();
        // يمكن إضافة مصروفات أخرى هنا

        return $revenue - $commissions;
    }

    private function getRevenueChart(): array
    {
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dailyRevenue = Invoice::where('type', 'customer')
                ->whereDate('issue_date', $date)
                ->sum('total_amount');
            $data[] = $dailyRevenue;
        }
        return $data;
    }

    private function formatMoney(float $amount): string
    {
        return 'E£ ' . number_format($amount, 0);
    }
}

// app/Filament/Pages/FinancialReports.php

namespace App\Filament\Pages;

use App\Models\Invoice;
use App\Models\Payment;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Carbon\Carbon;

class FinancialOverviewWidget extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Financial Reports';
    protected static ?string $navigationGroup = 'Accounting';
    protected static ?int $navigationSort = 5;
    protected static string $view = 'filament.pages.financial-reports';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'date_from' => now()->startOfMonth(),
            'date_to' => now()->endOfMonth(),
            'report_type' => 'revenue',
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('report_type')
                    ->label('Report Type')
                    ->options([
                        'revenue' => 'Revenue Report',
                        'payments' => 'Payments Report',
                        'commissions' => 'Driver Commissions',
                        'outstanding' => 'Outstanding Invoices',
                        'profit_loss' => 'Profit & Loss',
                    ])
                    ->required()
                    ->live(),

                DatePicker::make('date_from')
                    ->label('From Date')
                    ->required(),

                DatePicker::make('date_to')
                    ->label('To Date')
                    ->required(),
            ])
            ->columns(3)
            ->statePath('data');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns($this->getTableColumns())
            ->defaultSort('created_at', 'desc');
    }

    protected function getTableQuery()
    {
        $data = $this->form->getState();
        $reportType = $data['report_type'] ?? 'revenue';
        $dateFrom = $data['date_from'] ?? now()->startOfMonth();
        $dateTo = $data['date_to'] ?? now()->endOfMonth();

        return match($reportType) {
            'revenue' => Invoice::where('type', 'customer')
                ->whereBetween('issue_date', [$dateFrom, $dateTo]),

            'payments' => Payment::whereBetween('payment_date', [$dateFrom, $dateTo]),

            'commissions' => Invoice::where('type', 'driver_commission')
                ->whereBetween('issue_date', [$dateFrom, $dateTo]),

            'outstanding' => Invoice::where('type', 'customer')
                ->whereIn('status', ['sent', 'overdue']),

            default => Invoice::whereBetween('issue_date', [$dateFrom, $dateTo]),
        };
    }

    protected function getTableColumns(): array
    {
        $data = $this->form->getState();
        $reportType = $data['report_type'] ?? 'revenue';

        return match($reportType) {
            'revenue' => [
                TextColumn::make('invoice_number')->label('Invoice #'),
                TextColumn::make('customer.name')->label('Customer'),
                TextColumn::make('total_amount')->money('EGP'),
                TextColumn::make('status')->badge(),
                TextColumn::make('issue_date')->date(),
            ],

            'payments' => [
                TextColumn::make('payment_number')->label('Payment #'),
                TextColumn::make('invoice.customer.name')->label('Customer'),
                TextColumn::make('amount')->money('EGP'),
                TextColumn::make('payment_method')->badge(),
                TextColumn::make('payment_date')->dateTime(),
            ],

            'commissions' => [
                TextColumn::make('invoice_number')->label('Commission #'),
                TextColumn::make('driver.name')->label('Driver'),
                TextColumn::make('total_amount')->money('EGP'),
                TextColumn::make('status')->badge(),
                TextColumn::make('issue_date')->date(),
            ],

            default => [
                TextColumn::make('invoice_number')->label('Invoice #'),
                TextColumn::make('total_amount')->money('EGP'),
                TextColumn::make('status')->badge(),
                TextColumn::make('issue_date')->date(),
            ],
        };
    }

    // Methods to get summary data for the view
    public function getReportSummary(): array
    {
        $data = $this->form->getState();
        $dateFrom = $data['date_from'] ?? now()->startOfMonth();
        $dateTo = $data['date_to'] ?? now()->endOfMonth();

        return [
            'total_revenue' => Invoice::where('type', 'customer')
                ->whereBetween('issue_date', [$dateFrom, $dateTo])
                ->sum('total_amount'),

            'total_payments' => Payment::where('status', 'completed')
                ->whereBetween('payment_date', [$dateFrom, $dateTo])
                ->sum('amount'),

            'total_commissions' => Invoice::where('type', 'driver_commission')
                ->whereBetween('issue_date', [$dateFrom, $dateTo])
                ->sum('total_amount'),

            'outstanding_amount' => Invoice::where('type', 'customer')
                ->whereIn('status', ['sent', 'overdue'])
                ->sum('remaining_amount'),
        ];
    }
}
