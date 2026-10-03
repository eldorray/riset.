<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public function unlimited(): static
    {
        return $this->state(fn (): array => ['unlimited' => true]);
    }

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'google_id' => (string) fake()->unique()->numberBetween(100000000, 999999999),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'remember_token' => Str::random(10),
        ];
    }
}
