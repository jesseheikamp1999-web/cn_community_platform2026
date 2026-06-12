<?php

namespace Database\Factories;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => null,
            'discord_id' => (string) fake()->unique()->numberBetween(100000000000, 999999999999),
            'discord_username' => fake()->userName(),
            'role' => UserRole::Member,
            'remember_token' => Str::random(10),
        ];
    }
}
