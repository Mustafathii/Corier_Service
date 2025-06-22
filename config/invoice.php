<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Invoice Default Settings
    |--------------------------------------------------------------------------
    |
    | Default settings for invoice generation and calculations
    |
    */

    // معدل الضريبة الافتراضي (14% في مصر)
    'default_tax_rate' => 14.0,

    // عدد الأيام للاستحقاق (30 يوم افتراضي)
    'due_days' => 30,

    // أيام التذكير قبل الاستحقاق
    'reminder_days' => [3, 1], // تذكير قبل 3 أيام ويوم واحد

    // أيام التذكير بعد الاستحقاق
    'overdue_reminder_days' => [1, 7, 14, 30], // بعد يوم، أسبوع، أسبوعين، شهر

    /*
    |--------------------------------------------------------------------------
    | Company Information
    |--------------------------------------------------------------------------
    |
    | Company details that appear on invoices
    |
    */
    'company' => [
        'name' => 'شركة التوصيل السريع',
        'name_en' => 'Fast Delivery Company',
        'address' => 'القاهرة، مصر',
        'address_en' => 'Cairo, Egypt',
        'phone' => '+20 123 456 789',
        'email' => 'info@delivery.com',
        'website' => 'www.delivery.com',
        'tax_number' => '123456789',
        'commercial_register' => '987654321',
        'logo' => 'images/company-logo.png', // مسار اللوجو
    ],

    /*
    |--------------------------------------------------------------------------
    | Commission Rates
    |--------------------------------------------------------------------------
    |
    | Driver commission rates based on performance
    |
    */
    'commission_rates' => [
        'excellent' => 15.0, // 95%+ معدل نجاح
        'good' => 12.0,      // 90%+ معدل نجاح
        'average' => 10.0,   // 85%+ معدل نجاح
        'basic' => 8.0,      // أقل من 85%
    ],

    // حدود معدل النجاح
    'performance_thresholds' => [
        'excellent' => 95,
        'good' => 90,
        'average' => 85,
    ],

    /*
    |--------------------------------------------------------------------------
    | Pricing Settings
    |--------------------------------------------------------------------------
    |
    | Default pricing and fee calculations
    |
    */
    'pricing' => [
        // رسوم COD (نسبة مئوية من قيمة الشحنة)
        'cod_fee_rate' => 2.0, // 2%

        // الحد الأدنى لرسوم COD
        'cod_min_fee' => 5.0,

        // الحد الأقصى لرسوم COD
        'cod_max_fee' => 100.0,

        // رسوم إضافية حسب نوع الخدمة
        'service_multipliers' => [
            'standard' => 1.0,
            'express' => 1.2,
            'same_day' => 1.5,
        ],

        // رسوم المناطق النائية
        'remote_area_fee' => 20.0,

        // المناطق النائية
        'remote_areas' => [
            'سيوة', 'مرسى علم', 'الوادي الجديد', 'جنوب سيناء'
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Bonus Settings
    |--------------------------------------------------------------------------
    |
    | Performance bonus calculations for drivers
    |
    */
    'bonuses' => [
        // مكافآت حسب عدد التسليمات الشهرية
        'delivery_bonuses' => [
            100 => 500, // 100+ تسليمة = 500 جنيه
            75 => 300,  // 75+ تسليمة = 300 جنيه
            50 => 150,  // 50+ تسليمة = 150 جنيه
        ],

        // مكافأة عدم وجود شكاوى
        'no_complaints_bonus' => 100,

        // مكافأة التقييم الممتاز (5 نجوم)
        'excellent_rating_bonus' => 200,
    ],

    /*
    |--------------------------------------------------------------------------
    | Invoice Numbering
    |--------------------------------------------------------------------------
    |
    | Invoice number format and sequences
    |
    */
    'numbering' => [
        // تنسيق أرقام الفواتير
        'customer_prefix' => 'INV',     // INV-2024-0001
        'commission_prefix' => 'COM',   // COM-2024-0001
        'payment_prefix' => 'PAY',      // PAY-2024-0001

        // طول الرقم التسلسلي
        'sequence_length' => 4,

        // إعادة تعيين الترقيم سنوياً
        'reset_yearly' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Settings
    |--------------------------------------------------------------------------
    |
    | Email templates and settings for invoice notifications
    |
    */
    'email' => [
        // إرسال الفاتورة تلقائياً عند الإنشاء
        'auto_send_on_create' => true,

        // إرسال تأكيد الدفع
        'send_payment_confirmation' => true,

        // قوالب الإيميل
        'templates' => [
            'new_invoice' => 'emails.invoice.new',
            'reminder' => 'emails.invoice.reminder',
            'overdue' => 'emails.invoice.overdue',
            'payment_received' => 'emails.invoice.payment_received',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | PDF Settings
    |--------------------------------------------------------------------------
    |
    | PDF generation settings and styling
    |
    */
    'pdf' => [
        // حجم الورق
        'paper_size' => 'a4',

        // اتجاه الورق
        'orientation' => 'portrait',

        // الخط الافتراضي
        'font' => 'DejaVu Sans',

        // حجم الخط
        'font_size' => '12px',

        // ألوان الشركة
        'colors' => [
            'primary' => '#007bff',
            'secondary' => '#6c757d',
            'success' => '#28a745',
            'danger' => '#dc3545',
            'warning' => '#ffc107',
            'info' => '#17a2b8',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Methods
    |--------------------------------------------------------------------------
    |
    | Available payment methods and their settings
    |
    */
    'payment_methods' => [
        'cash' => [
            'name' => 'نقداً',
            'name_en' => 'Cash',
            'enabled' => true,
            'requires_reference' => false,
        ],
        'bank_transfer' => [
            'name' => 'تحويل بنكي',
            'name_en' => 'Bank Transfer',
            'enabled' => true,
            'requires_reference' => true,
            'account_details' => [
                'bank_name' => 'البنك الأهلي المصري',
                'account_number' => '123456789',
                'swift_code' => 'NBEAEGCX',
            ],
        ],
        'credit_card' => [
            'name' => 'بطاقة ائتمان',
            'name_en' => 'Credit Card',
            'enabled' => true,
            'requires_reference' => true,
        ],
        'vodafone_cash' => [
            'name' => 'فودافون كاش',
            'name_en' => 'Vodafone Cash',
            'enabled' => true,
            'requires_reference' => true,
            'number' => '01012345678',
        ],
        'orange_cash' => [
            'name' => 'أورنج مني',
            'name_en' => 'Orange Money',
            'enabled' => true,
            'requires_reference' => true,
            'number' => '01112345678',
        ],
        'etisalat_cash' => [
            'name' => 'اتصالات كاش',
            'name_en' => 'Etisalat Cash',
            'enabled' => true,
            'requires_reference' => true,
            'number' => '01512345678',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Automation Settings
    |--------------------------------------------------------------------------
    |
    | Automated invoice generation and processing settings
    |
    */
    'automation' => [
        // توليد فواتير العملاء تلقائياً
        'auto_generate_customer_invoices' => true,

        // توليد فواتير عمولات السائقين تلقائياً
        'auto_generate_driver_commissions' => true,

        // يوم الشهر لتوليد الفواتير (1 = أول يوم)
        'generation_day' => 1,

        // ساعة التوليد
        'generation_hour' => 9,

        // إرسال تذكيرات تلقائية
        'auto_send_reminders' => true,

        // تحديث حالة الفواتير المتأخرة تلقائياً
        'auto_update_overdue_status' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    |
    | Business rules and validation settings
    |
    */
    'validation' => [
        // الحد الأدنى لمبلغ الفاتورة
        'min_invoice_amount' => 1.0,

        // الحد الأقصى لمبلغ الفاتورة
        'max_invoice_amount' => 100000.0,

        // الحد الأقصى لفترة الاستحقاق (بالأيام)
        'max_due_days' => 90,

        // السماح بالفواتير السالبة (استرداد)
        'allow_negative_invoices' => false,

        // مطلوب موافقة لحذف الفواتير المدفوعة
        'require_approval_for_paid_deletion' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Reporting Settings
    |--------------------------------------------------------------------------
    |
    | Financial reporting configurations
    |
    */
    'reporting' => [
        // العملة الافتراضية
        'default_currency' => 'EGP',
        'currency_symbol' => 'ج.م',

        // السنة المالية (شهر البداية)
        'fiscal_year_start' => 1, // يناير

        // تقسيم التقارير
        'report_periods' => [
            'daily' => 'يومي',
            'weekly' => 'أسبوعي',
            'monthly' => 'شهري',
            'quarterly' => 'ربعي',
            'yearly' => 'سنوي',
        ],

        // أنواع التقارير المتاحة
        'available_reports' => [
            'revenue' => 'تقرير الإيرادات',
            'payments' => 'تقرير المدفوعات',
            'commissions' => 'تقرير العمولات',
            'outstanding' => 'تقرير المستحقات',
            'profit_loss' => 'تقرير الأرباح والخسائر',
            'tax' => 'تقرير الضرائب',
        ],
    ],
];
