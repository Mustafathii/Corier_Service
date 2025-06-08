<?php

namespace Database\Seeders;

use App\Models\Driver;
use App\Models\Shipment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ShipmentSeeder extends Seeder
{
    public function run()
    {
        // تأكد من وجود سائقين أولاً
        $drivers = Driver::all();

        if ($drivers->isEmpty()) {
            Driver::factory()->count(3)->create();
            $drivers = Driver::all();
        }

        $shipments = [
            [
                'tracking_number' => 'SH' . now()->format('Ymd') . '001',
                'sender_name' => 'شركة المصادر للتجارة',
                'sender_phone' => '01012345678',
                'sender_address' => '15 شارع الجمهورية، القاهرة',
                'sender_city' => 'القاهرة',
                'receiver_name' => 'أحمد محمد',
                'receiver_phone' => '01123456789',
                'receiver_address' => '30 شارع النصر، الإسكندرية',
                'receiver_city' => 'الإسكندرية',
                'package_type' => 'وثائق',
                'weight' => 0.5,
                'description' => 'مستندات قانونية',
                'status' => 'delivered',
                'driver_id' => $drivers->random()->id,
                'pickup_date' => Carbon::now()->subDays(3),
                'expected_delivery_date' => Carbon::now()->subDays(1),
                'actual_delivery_date' => Carbon::now()->subDays(1),
            ],
            [
                'tracking_number' => 'SH' . now()->format('Ymd') . '002',
                'sender_name' => 'متجر الأجهزة الإلكترونية',
                'sender_phone' => '01234567890',
                'sender_address' => 'مول مصر، الدور الثالث',
                'sender_city' => 'الجيزة',
                'receiver_name' => 'مريم أسامة',
                'receiver_phone' => '01098765432',
                'receiver_address' => '8 شارع الحرية، المنصورة',
                'receiver_city' => 'المنصورة',
                'package_type' => 'إلكترونيات',
                'weight' => 2.3,
                'declared_value' => 4500,
                'description' => 'هاتف محمول جديد',
                'status' => 'in_transit',
                'driver_id' => $drivers->random()->id,
                'pickup_date' => Carbon::now()->subDays(1),
                'expected_delivery_date' => Carbon::now()->addDays(2),
            ],
            // يمكنك إضافة المزيد من الشحنات هنا
        ];

        foreach ($shipments as $shipment) {
            Shipment::create($shipment);
        }

        // إنشاء شحنات عشوائية باستخدام Factory
        Shipment::factory()->count(15)->create();
    }
}
