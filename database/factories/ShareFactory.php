<?php

namespace Database\Factories;

use App\Models\Share;
use App\Models\Timetable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Share> */
class ShareFactory extends Factory
{
    protected $model = Share::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'timetable_id' => Timetable::factory(),
            'token_hash' => hash('sha256', Str::random(64)),
            'label' => null,
            'password_hash' => null,
            'expires_at' => null,
            'revoked_at' => null,
            'disabled_by_admin_at' => null,
            'disabled_reason' => null,
            'access_version' => 1,
            'views_count' => 0,
            'last_viewed_at' => null,
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => ['revoked_at' => now()]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => ['expires_at' => now()->subMinute()]);
    }

    public function disabledByAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'disabled_by_admin_at' => now(),
            'disabled_reason' => 'Factory generated moderation action',
        ]);
    }
}
