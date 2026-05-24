<?php

namespace Database\Factories;

use App\Models\OtpCode;
use App\Support\Enums\OtpPurpose;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OtpCode>
 */
class OtpCodeFactory extends Factory
{
    protected $model = OtpCode::class;

    public function definition(): array
    {
        return [
            'phone' => fake()->phoneNumber(),
            'code' => (string) fake()->numberBetween(100000, 999999),
            'purpose' => fake()->randomElement(OtpPurpose::cases()),
            'expires_at' => now()->addMinutes(5),
            'used_at' => null,
            'ip_address' => fake()->ipv4(),
        ];
    }
}
