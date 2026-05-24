<?php

namespace Database\Factories;

use App\Models\AppVersion;
use App\Support\Enums\DevicePlatform;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppVersion>
 */
class AppVersionFactory extends Factory
{
    protected $model = AppVersion::class;

    public function definition(): array
    {
        return [
            'platform' => fake()->randomElement([DevicePlatform::Android, DevicePlatform::Ios]),
            'min_version' => '1.0.0',
            'latest_version' => '1.2.0',
            'force_update' => fake()->boolean(),
            'store_url' => fake()->url(),
            'release_notes' => fake()->paragraph(),
        ];
    }
}
