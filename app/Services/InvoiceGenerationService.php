<?php

// app/Services/InvoiceGenerationService.php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Shipment;
use App\Models\User;
use Carbon\Carbon;

class InvoiceGenerationService
{
    /**
     * توليد فواتير شهرية للعملاء
     */
    public function generateMonthlyCustomerInvoices(Carbon $month = null): array
    {
        $month = $month ?: now()->subMonth();
        $startOfMonth = $month->copy()->startOfMonth();
        $endOfMonth = $month->copy()->endOfMonth();

        $generated = [];

        // الحصول على العملاء الذين لديهم شحنات في الشهر المحدد
        $customers = User::whereHas('sellerShipments', function ($query) use ($startOfMonth, $endOfMonth) {
            $query->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                  ->whereIn('status', ['delivered', 'out_for_delivery']);
        })->whereHas('roles', function ($query) {
            $query->where('name', 'Seller');
        })->get();

        foreach ($customers as $customer) {
            $invoice = $this->createCustomerInvoice($customer, $startOfMonth, $endOfMonth);
            if ($invoice) {
                $generated[] = $invoice;
            }
        }

        return $generated;
    }

    /**
     * إنشاء فاتورة لعميل محدد
     */
    public function createCustomerInvoice(User $customer, Carbon $startDate, Carbon $endDate): ?Invoice
    {
        // الحصول على الشحنات القابلة للفوترة
        $shipments = Shipment::where('seller_id', $customer->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('status', ['delivered', 'out_for_delivery'])
            ->whereDoesntHave('invoiceItem') // لم يتم فوترتها من قبل
            ->get();

        if ($shipments->isEmpty()) {
            return null;
        }

        // إنشاء الفاتورة
        $invoice = Invoice::create([
            'type' => 'customer',
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone,
            'customer_address' => $customer->address,
            'status' => 'draft',
            'issue_date' => now(),
            'due_date' => now()->addDays(30),
            'tax_rate' => config('invoice.default_tax_rate', 14),
            'period_from' => $startDate->format('Y-m-d'),
            'period_to' => $endDate->format('Y-m-d'),
        ]);

        // إضافة بنود الفاتورة
        $subtotal = 0;
        foreach ($shipments as $shipment) {
            $price = $this->calculateShipmentPrice($shipment);

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'shipment_id' => $shipment->id,
                'description' => "Shipment to {$shipment->receiver_city} - {$shipment->package_type}",
                'item_type' => 'shipment',
                'quantity' => 1,
                'unit_price' => $price,
                'total_price' => $price,
                'tracking_number' => $shipment->tracking_number,
                'service_type' => $shipment->shipment_type,
            ]);

            $subtotal += $price;
        }

        // حساب الإجماليات
        $invoice->calculateTotals();
        $invoice->save();

        return $invoice;
    }

    /**
     * توليد فواتير عمولات السائقين
     */
    public function generateDriverCommissions(Carbon $month = null): array
    {
        $month = $month ?: now()->subMonth();
        $startOfMonth = $month->copy()->startOfMonth();
        $endOfMonth = $month->copy()->endOfMonth();

        $generated = [];

        // الحصول على السائقين الذين لديهم شحنات مُسلمة
        $drivers = User::whereHas('driverShipments', function ($query) use ($startOfMonth, $endOfMonth) {
            $query->whereBetween('actual_delivery_date', [$startOfMonth, $endOfMonth])
                  ->where('status', 'delivered');
        })->whereHas('roles', function ($query) {
            $query->where('name', 'Driver');
        })->get();

        foreach ($drivers as $driver) {
            $invoice = $this->createDriverCommissionInvoice($driver, $startOfMonth, $endOfMonth);
            if ($invoice) {
                $generated[] = $invoice;
            }
        }

        return $generated;
    }


    /**
     * إنشاء فاتورة عمولة للسائق
     */
    public function createDriverCommissionInvoice(User $driver, Carbon $startDate, Carbon $endDate): ?Invoice
    {
        // الحصول على الشحنات المُسلمة
        $deliveredShipments = Shipment::where('driver_id', $driver->id)
            ->whereBetween('actual_delivery_date', [$startDate, $endDate])
            ->where('status', 'delivered')
            ->whereDoesntHave('driverCommissionItem') // لم يتم حساب العمولة من قبل
            ->get();

        if ($deliveredShipments->isEmpty()) {
            return null;
        }

        // إنشاء فاتورة العمولة
        $invoice = Invoice::create([
            'type' => 'driver_commission',
            'driver_id' => $driver->id,
            'status' => 'draft',
            'issue_date' => now(),
            'due_date' => now()->addDays(7), // العمولات تُدفع أسبوعياً
            'tax_rate' => 0, // العمولات بدون ضريبة
            'period_from' => $startDate->format('Y-m-d'),
            'period_to' => $endDate->format('Y-m-d'),
        ]);

        // حساب العمولات
        $totalCommission = 0;
        $commissionRate = $this->getDriverCommissionRate($driver);

        foreach ($deliveredShipments as $shipment) {
            $shipmentValue = $shipment->shipping_cost;
            $commission = $shipmentValue * ($commissionRate / 100);

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'shipment_id' => $shipment->id,
                'description' => "Commission for delivery to {$shipment->receiver_city}",
                'item_type' => 'commission',
                'quantity' => 1,
                'unit_price' => $commission,
                'total_price' => $commission,
                'tracking_number' => $shipment->tracking_number,
                'service_type' => $shipment->shipment_type,
                'notes' => "Commission rate: {$commissionRate}%",
            ]);

            $totalCommission += $commission;
        }

        // إضافة مكافآت إضافية إذا كان الأداء ممتاز
        $bonus = $this->calculatePerformanceBonus($driver, $deliveredShipments->count());
        if ($bonus > 0) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'description' => 'Performance Bonus',
                'item_type' => 'bonus',
                'quantity' => 1,
                'unit_price' => $bonus,
                'total_price' => $bonus,
                'notes' => 'Excellent performance bonus',
            ]);
            $totalCommission += $bonus;
        }

        $invoice->calculateTotals();
        $invoice->save();

        return $invoice;
    }

    /**
     * حساب سعر الشحنة للفوترة
     */
    private function calculateShipmentPrice(Shipment $shipment): float
    {
        $basePrice = $shipment->shipping_cost;

        // إضافة رسوم إضافية حسب نوع الخدمة
        $multiplier = match($shipment->shipment_type) {
            'express' => 1.0,
            'same_day' => 1.0,
            'standard' => 1.0,
            default => 1.0
        };

        // إضافة رسوم COD
        $codFee = 0;
        if ($shipment->payment_method === 'cod' && $shipment->cod_amount > 0) {
            $codFee = $shipment->cod_amount * 0.02; // 2% رسوم COD
        }

        return ($basePrice * $multiplier) + $codFee;
    }

    /**
     * الحصول على معدل عمولة السائق
     */
    private function getDriverCommissionRate(User $driver): float
{
    $successRate = $this->calculateDriverSuccessRate($driver);
    $thresholds = config('invoice.performance_thresholds');
    $rates = config('invoice.commission_rates');

    return match(true) {
        $successRate >= $thresholds['excellent'] => $rates['excellent'],
        $successRate >= $thresholds['good'] => $rates['good'],
        $successRate >= $thresholds['average'] => $rates['average'],
        default => $rates['basic']
    };
}

private function calculateCODFee(float $amount): float
{
    $rate = config('invoice.pricing.cod_fee_rate');
    $minFee = config('invoice.pricing.cod_min_fee');
    $maxFee = config('invoice.pricing.cod_max_fee');

    $fee = ($amount * $rate) / 100;

    return max($minFee, min($fee, $maxFee));
}

    /**
     * حساب معدل نجاح السائق
     */
    private function calculateDriverSuccessRate(User $driver): float
    {
        $totalShipments = $driver->driverShipments()
            ->whereIn('status', ['delivered', 'failed_delivery'])
            ->count();

        if ($totalShipments === 0) {
            return 0;
        }

        $successfulDeliveries = $driver->driverShipments()
            ->where('status', 'delivered')
            ->count();

        return ($successfulDeliveries / $totalShipments) * 100;
    }

    /**
     * حساب مكافأة الأداء
     */
    private function calculatePerformanceBonus(User $driver, int $deliveriesCount): float
    {
        // مكافأة للسائقين الذين يسلمون أكثر من 50 شحنة شهرياً
        if ($deliveriesCount >= 100) {
            return 500; // مكافأة 500 جنيه
        } elseif ($deliveriesCount >= 50) {
            return 200; // مكافأة 200 جنيه
        }

        return 0;
    }

    /**
     * توليد فاتورة لشحنات محددة
     */
    public function createInvoiceForShipments(array $shipmentIds, array $invoiceData): Invoice
    {
        $shipments = Shipment::whereIn('id', $shipmentIds)->get();

        $invoice = Invoice::create($invoiceData);

        foreach ($shipments as $shipment) {
            $price = $this->calculateShipmentPrice($shipment);

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'shipment_id' => $shipment->id,
                'description' => "Shipment to {$shipment->receiver_city}",
                'item_type' => 'shipment',
                'quantity' => 1,
                'unit_price' => $price,
                'total_price' => $price,
                'tracking_number' => $shipment->tracking_number,
                'service_type' => $shipment->shipment_type,
            ]);
        }

        $invoice->calculateTotals();
        $invoice->save();

        return $invoice;
    }
}
