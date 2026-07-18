<?php

namespace Database\Factories;

use App\Enums\WeekMode;
use App\Models\Course;
use App\Models\CourseMeeting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CourseMeeting> */
class CourseMeetingFactory extends Factory
{
    protected $model = CourseMeeting::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'label' => 'Lecture',
            'teacher' => fake()->name(),
            'weekday' => fake()->numberBetween(1, 5),
            'starts_at' => '09:00',
            'ends_at' => '10:00',
            'location' => fake()->bothify('教学楼 ?-###'),
            'week_mode' => WeekMode::All->value,
            'start_week' => 1,
            'end_week' => 18,
            'specific_weeks' => null,
            'sort_order' => 0,
        ];
    }

    public function odd(int $startWeek = 1, int $endWeek = 18): static
    {
        return $this->state(fn (array $attributes) => [
            'week_mode' => WeekMode::Odd->value,
            'start_week' => $startWeek,
            'end_week' => $endWeek,
            'specific_weeks' => null,
        ]);
    }

    public function even(int $startWeek = 1, int $endWeek = 18): static
    {
        return $this->state(fn (array $attributes) => [
            'week_mode' => WeekMode::Even->value,
            'start_week' => $startWeek,
            'end_week' => $endWeek,
            'specific_weeks' => null,
        ]);
    }

    /** @param list<int> $weeks */
    public function specific(array $weeks): static
    {
        return $this->state(fn (array $attributes) => [
            'week_mode' => WeekMode::Specific->value,
            'start_week' => null,
            'end_week' => null,
            'specific_weeks' => $weeks,
        ]);
    }

    public function at(string $startsAt, string $endsAt, int $weekday = 1): static
    {
        return $this->state(fn (array $attributes) => [
            'weekday' => $weekday,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
    }
}
