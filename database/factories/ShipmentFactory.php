<?php

namespace Database\Factories;

use App\Models\Driver;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShipmentFactory extends Factory
{
    protected $model = Shipment::class;

    public function definition()
    {
        $statuses = [
            'pending',
            'picked_up',
            'in_transit',
            'out_for_delivery',
            'delivered',
            'failed_delivery',
            'returned',
            'canceled'
        ];

        return [
            'tracking_number' => 'SH' . now()->format('YmdHis') . $this->faker->unique()->numberBetween(100, 999),
            'sender_name' => $this->faker->name,
            'sender_phone' => '01' . $this->faker->numerify('########'),
            'sender_address' => $this->faker->address,
            'sender_city' => $this->faker->city,
            'receiver_name' => $this->faker->name,
            'receiver_phone' => '01' . $this->faker->numerify('########'),
            'receiver_address' => $this->faker->address,
            'receiver_city' => $this->faker->city,
            'package_type' => $this->faker->randomElement(['وثائق', 'إلكترونيات', 'ملابس', 'أدوات منزلية', 'أخرى']),
            'weight' => $this->faker->randomFloat(2, 0.1, 20),
            'description' => $this->faker->sentence,
            'declared_value' => $this->faker->optional()->randomFloat(2, 100, 10000),
            'status' => $this->faker->randomElement($statuses),
            'driver_id' => Driver::inRandomOrder()->first()->id,
            'notes' => $this->faker->optional()->sentence,
        ];
    }
}
