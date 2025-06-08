<?php

namespace Database\Seeders;

use App\Models\Driver;
use Illuminate\Database\Seeder;

class DriverSeeder extends Seeder
{
    public function run()
    {
        $drivers = [
            [
                'name' => 'محمد أحمد',
                'email' => 'mohamed.ahmed@example.com',
                'phone' => '01012345678',
                'license_number' => 'DL-2020-12345',
                'status' => 'available',
                'notes' => 'سائق متميز - خبرة 5 سنوات',
            ],
            [
                'name' => 'أحمد علي',
                'email' => 'ahmed.ali@example.com',
                'phone' => '01123456789',
                'license_number' => 'DL-2019-54321',
                'status' => 'available',
                'notes' => 'متخصص في توصيل الطرود الكبيرة',
            ],
            [
                'name' => 'محمود سعيد',
                'email' => 'mahmoud.said@example.com',
                'phone' => '01234567890',
                'license_number' => 'DL-2021-98765',
                'status' => 'busy',
                'notes' => 'في إجازة حتى نهاية الشهر',
            ],
            [
                'name' => 'خالد عمر',
                'email' => 'khaled.omar@example.com',
                'phone' => '01567891234',
                'license_number' => 'DL-2018-45678',
                'status' => 'busy',
                'notes' => 'يعمل في النوبتجيات الليلية',
            ],
            [
                'name' => 'ياسر وائل',
                'email' => 'yasser.wael@example.com',
                'phone' => '01098765432',
                'license_number' => 'DL-2022-13579',
                'status' => 'off_duty',
                'notes' => 'معلق بسبب مخالفات مرورية',
            ],
        ];

        foreach ($drivers as $driver) {
            Driver::create($driver);
        }

        // إنشاء سائقين إضافيين عشوائيين باستخدام Factory
        \App\Models\Driver::factory()->count(5)->create();
    }
}
