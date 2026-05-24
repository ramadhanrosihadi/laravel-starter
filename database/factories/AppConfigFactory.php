<?php

namespace Database\Factories;

use App\Models\AppConfig;
use App\Support\Enums\AppConfigType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppConfig>
 */
class AppConfigFactory extends Factory
{
    protected $model = AppConfig::class;

    public function definition(): array
    {
        $type = fake()->randomElement(AppConfigType::cases());

        $value = match ($type) {
            AppConfigType::Boolean => fake()->randomElement(['true', 'false']),
            AppConfigType::Integer => (string) fake()->numberBetween(1, 100),
            AppConfigType::Json => json_encode(['enabled' => fake()->boolean()]),
            default => fake()->word(),
        };

        return [
            'key' => fake()->unique()->word(),
            'value' => $value,
            'type' => $type,
            'description' => fake()->sentence(),
        ];
    }
}
