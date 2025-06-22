<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@200;300;400;600;700&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Cairo', 'DejaVu Sans', sans-serif;
            font-size: 14px;
            line-height: 1.6;
            color: #333;
            direction: rtl;
            text-align: right;
        }

        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background: white;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #007bff;
            padding-bottom: 20px;
        }

        .company-logo {
            font-size: 28px;
            font-weight: 700;
            color: #007bff;
            margin-bottom: 10px;
            font-family: 'Cairo', sans-serif;
        }

        .company-details {
            font-size: 12px;
            color: #666;
            font-family: 'Cairo', sans-serif;
        }

        .invoice-title {
            font-size: 32px;
            font-weight: 700;
            color: #007bff;
            margin: 25px 0;
            text-align: center;
            font-family: 'Cairo', sans-serif;
        }

        .invoice-info {
            display: table;
            width: 100%;
            margin-bottom: 30px;
        }

        .invoice-info .left,
        .invoice-info .right {
            display: table-cell;
            width: 48%;
            vertical-align: top;
            padding: 0 1%;
        }

        .info-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #e9ecef;
        }

        .info-box h3 {
            font-size: 16px;
            margin-bottom: 15px;
            color: #007bff;
            border-bottom: 2px solid #007bff;
            padding-bottom: 8px;
            font-family: 'Cairo', sans-serif;
            font-weight: 600;
        }

        .info-box p {
            margin-bottom: 8px;
            font-family: 'Cairo', sans-serif;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 25px 0;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .items-table th,
        .items-table td {
            border: 1px solid #ddd;
            padding: 12px 15px;
            text-align: right;
            font-family: 'Cairo', sans-serif;
        }

        .items-table th {
            background: #007bff;
            color: white;
            font-weight: 600;
            font-size: 14px;
        }

        .items-table tr:nth-child(even) {
            background: #f8f9fa;
        }

        .items-table tr:hover {
            background: #e3f2fd;
        }

        .totals {
            margin-top: 30px;
            text-align: left;
        }

        .totals table {
            margin-left: auto;
            border-collapse: collapse;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .totals td {
            padding: 10px 20px;
            border-bottom: 1px solid #ddd;
            font-family: 'Cairo', sans-serif;
            font-size: 14px;
        }

        .totals .total-row {
            font-weight: 700;
            font-size: 18px;
            background: #007bff;
            color: white;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            font-family: 'Cairo', sans-serif;
        }

        .status-paid { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-overdue { background: #f8d7da; color: #721c24; }
        .status-draft { background: #e2e3e5; color: #383d41; }

        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 12px;
            color: #666;
            border-top: 2px solid #007bff;
            padding-top: 20px;
            font-family: 'Cairo', sans-serif;
        }

        .payment-details {
            background: #e3f2fd;
            padding: 20px;
            border-radius: 8px;
            margin-top: 25px;
            border: 1px solid #90caf9;
        }

        .payment-details h3 {
            color: #0d47a1;
            margin-bottom: 15px;
            font-family: 'Cairo', sans-serif;
        }

        .arabic-number {
            font-family: 'Cairo', 'DejaVu Sans', monospace;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <!-- Header -->
        <div class="header">
            <div class="company-logo">شركة التوصيل السريع</div>
            <div class="company-details">
                العنوان: القاهرة، مصر | الهاتف: +20 123 456 789 | البريد الإلكتروني: info@delivery.com
            </div>
        </div>

        <!-- Invoice Title -->
        <div class="invoice-title">
            @if($invoice->type === 'customer')
                فاتورة العميل
            @else
                فاتورة عمولة السائق
            @endif
        </div>

        <!-- Invoice Information -->
        <div class="invoice-info">
            <div class="right">
                <div class="info-box">
                    <h3>معلومات الفاتورة</h3>
                    <p><strong>رقم الفاتورة:</strong> <span class="arabic-number">{{ $invoice->invoice_number }}</span></p>
                    <p><strong>تاريخ الإصدار:</strong> <span class="arabic-number">{{ $invoice->issue_date->format('Y/m/d') }}</span></p>
                    <p><strong>تاريخ الاستحقاق:</strong> <span class="arabic-number">{{ $invoice->due_date->format('Y/m/d') }}</span></p>
                    <p><strong>الحالة:</strong>
                        <span class="status-badge status-{{ $invoice->status }}">
                            {{ $invoice->status === 'paid' ? 'مدفوعة' : ($invoice->status === 'draft' ? 'مسودة' : 'مرسلة') }}
                        </span>
                    </p>
                    @if($invoice->period_from && $invoice->period_to)
                        <p><strong>فترة الخدمة:</strong> من {{ $invoice->period_from }} إلى {{ $invoice->period_to }}</p>
                    @endif
                </div>
            </div>

            <div class="left">
                @if($invoice->type === 'customer')
                    <div class="info-box">
                        <h3>معلومات العميل</h3>
                        <p><strong>الاسم:</strong> {{ $invoice->customer_name ?? $invoice->customer?->name }}</p>
                        @if($invoice->customer_phone)
                            <p><strong>الهاتف:</strong> <span class="arabic-number">{{ $invoice->customer_phone }}</span></p>
                        @endif
                        @if($invoice->customer_email)
                            <p><strong>البريد الإلكتروني:</strong> {{ $invoice->customer_email }}</p>
                        @endif
                        @if($invoice->customer_address)
                            <p><strong>العنوان:</strong> {{ $invoice->customer_address }}</p>
                        @endif
                    </div>
                @else
                    <div class="info-box">
                        <h3>معلومات السائق</h3>
                        <p><strong>الاسم:</strong> {{ $invoice->driver?->name }}</p>
                        <p><strong>الهاتف:</strong> <span class="arabic-number">{{ $invoice->driver?->phone }}</span></p>
                        <p><strong>البريد الإلكتروني:</strong> {{ $invoice->driver?->email }}</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Invoice Items -->
        <table class="items-table">
            <thead>
                <tr>
                    <th>الوصف</th>
                    @if($invoice->type === 'customer')
                        <th>رقم التتبع</th>
                        <th>نوع الخدمة</th>
                    @endif
                    <th>الكمية</th>
                    <th>سعر الوحدة</th>
                    <th>الإجمالي</th>
                </tr>
            </thead>
            <tbody>
                @forelse($invoice->items as $item)
                    <tr>
                        <td>{{ $item->description }}</td>
                        @if($invoice->type === 'customer')
                            <td class="arabic-number">{{ $item->tracking_number ?? '-' }}</td>
                            <td>{{ $item->service_type === 'standard' ? 'عادي' : ($item->service_type === 'express' ? 'سريع' : 'نفس اليوم') }}</td>
                        @endif
                        <td class="arabic-number">{{ $item->quantity }}</td>
                        <td class="arabic-number">{{ number_format($item->unit_price, 2) }} ج.م</td>
                        <td class="arabic-number">{{ number_format($item->total_price, 2) }} ج.م</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $invoice->type === 'customer' ? 6 : 4 }}" style="text-align: center; padding: 30px; color: #666;">
                            لا توجد عناصر في هذه الفاتورة
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <!-- Totals -->
        <div class="totals">
            <table>
                <tr>
                    <td><strong>المجموع الفرعي:</strong></td>
                    <td class="arabic-number">{{ number_format($invoice->subtotal, 2) }} ج.م</td>
                </tr>
                @if($invoice->discount_amount > 0)
                    <tr>
                        <td><strong>الخصم:</strong></td>
                        <td class="arabic-number">-{{ number_format($invoice->discount_amount, 2) }} ج.م</td>
                    </tr>
                @endif
                @if($invoice->tax_amount > 0)
                    <tr>
                        <td><strong>الضريبة ({{ $invoice->tax_rate }}%):</strong></td>
                        <td class="arabic-number">{{ number_format($invoice->tax_amount, 2) }} ج.م</td>
                    </tr>
                @endif
                <tr class="total-row">
                    <td><strong>الإجمالي النهائي:</strong></td>
                    <td class="arabic-number"><strong>{{ number_format($invoice->total_amount, 2) }} ج.م</strong></td>
                </tr>
                @if($invoice->paid_amount > 0)
                    <tr>
                        <td><strong>المبلغ المدفوع:</strong></td>
                        <td class="arabic-number">{{ number_format($invoice->paid_amount, 2) }} ج.م</td>
                    </tr>
                    <tr>
                        <td><strong>المبلغ المتبقي:</strong></td>
                        <td class="arabic-number">{{ number_format($invoice->remaining_amount, 2) }} ج.م</td>
                    </tr>
                @endif
            </table>
        </div>

        <!-- Payment Details -->
        @if($invoice->payments->count() > 0)
            <div class="payment-details">
                <h3>تفاصيل المدفوعات</h3>
                @foreach($invoice->payments as $payment)
                    <p>
                        <strong>{{ $payment->payment_date->format('Y/m/d') }}:</strong>
                        <span class="arabic-number">{{ number_format($payment->amount, 2) }} ج.م</span>
                        ({{ $payment->payment_method === 'cash' ? 'نقداً' : ($payment->payment_method === 'bank_transfer' ? 'تحويل بنكي' : 'آخر') }})
                        @if($payment->reference_number)
                            - مرجع: <span class="arabic-number">{{ $payment->reference_number }}</span>
                        @endif
                    </p>
                @endforeach
            </div>
        @endif

        <!-- Notes -->
        @if($invoice->notes)
            <div class="info-box">
                <h3>ملاحظات</h3>
                <p>{{ $invoice->notes }}</p>
            </div>
        @endif

        <!-- Footer -->
        <div class="footer">
            <p><strong>شكراً لاختياركم خدماتنا</strong></p>
            <p>تم إنشاء هذه الفاتورة إلكترونياً في <span class="arabic-number">{{ now()->format('Y/m/d H:i') }}</span></p>
            <p>هذا المستند صالح قانونياً ولا يحتاج إلى توقيع</p>
        </div>
    </div>
</body>
</html>
