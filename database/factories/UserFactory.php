<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_admin' => true,
        ]);
    }

    public function banned(): static
    {
        return $this->state(fn (array $attributes) => [
            'banned_at' => now(),
            'ban_reason' => 'Factory generated ban',
        ]);
    }

    public function underReview(): static
    {
        return $this->state(fn (array $attributes) => [
            'review_started_at' => now(),
            'review_reason' => 'Factory generated review',
        ]);
    }

    public function sharingDisabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'sharing_disabled_at' => now(),
            'sharing_disabled_reason' => 'Factory generated restriction',
            'sharing_disabled_source' => 'admin',
        ]);
    }
}
