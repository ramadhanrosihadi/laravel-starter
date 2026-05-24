<?php

namespace Database\Factories;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(),
            'body' => fake()->paragraph(),
            'data' => ['click_action' => 'FLUTTER_NOTIFICATION_CLICK', 'id' => fake()->uuid()],
            'type' => fake()->randomElement(['info', 'warning', 'transaction']),
            'read_at' => null,
            'sent_at' => now(),
            'failed_at' => null,
        ];
    }
}
