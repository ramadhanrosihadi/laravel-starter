<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserDevice;
use App\Support\Enums\DevicePlatform;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserDevice>
 */
class UserDeviceFactory extends Factory
{
    protected $model = UserDevice::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'device_id' => fake()->uuid(),
            'platform' => fake()->randomElement(DevicePlatform::cases()),
            'os_version' => fake()->randomElement(['15.0', '16.2', '17.4', '14.0', '12.0']),
            'app_version' => fake()->randomElement(['1.0.0', '1.1.2', '2.0.0']),
            'device_name' => fake()->randomElement(['iPhone 15 Pro', 'Samsung Galaxy S24', 'Google Pixel 8', 'Xiaomi 14']),
            'push_token' => fake()->sha256(),
            'last_active_at' => now(),
        ];
    }
}
