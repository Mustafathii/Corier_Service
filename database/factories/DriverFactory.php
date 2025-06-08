<?php

namespace Database\Factories;

use App\Models\Driver;
use Illuminate\Database\Eloquent\Factories\Factory;

class DriverFactory extends Factory
{
    protected $model = Driver::class;

    public function definition()
    {
        $statuses = ['available', 'busy', 'off_duty'];

        return [
            'name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
            'phone' => '01' . $this->faker->numerify('########'),
            'license_number' => 'DL-' . $this->faker->year . '-' . $this->faker->unique()->numberBetween(10000, 99999),
            'status' => $this->faker->randomElement($statuses),
            'notes' => $this->faker->optional()->sentence,
        ];
    }
}
