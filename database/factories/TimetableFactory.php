<?php

namespace Database\Factories;

use App\Models\Timetable;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Timetable> */
class TimetableFactory extends Factory
{
    protected $model = Timetable::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true).'课表',
            'term_name' => '2026 秋季学期',
            'term_start_date' => '2026-09-07',
            'week_count' => 18,
            'timezone' => 'Asia/Shanghai',
            'near_threshold_minutes' => 30,
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => ['is_default' => true]);
    }

    public function threshold(int $minutes): static
    {
        return $this->state(fn (array $attributes) => [
            'near_threshold_minutes' => $minutes,
        ]);
    }
}
