<?php

namespace Database\Factories;

use App\Models\ResidentCsvSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResidentCsvSetting>
 */
class ResidentCsvSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'interval_minutes' => fake()->numberBetween(1, 60),
            'atomicity' => fake()->randomElement(['per_file', 'all_files']),
        ];
    }
}
