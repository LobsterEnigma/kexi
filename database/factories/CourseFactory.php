<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Timetable;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Course> */
class CourseFactory extends Factory
{
    protected $model = Course::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'timetable_id' => Timetable::factory(),
            'name' => fake()->randomElement(['高等数学', '大学英语', '数据结构', '线性代数']),
            'code' => strtoupper(fake()->bothify('??###')),
            'notes' => null,
            'sort_order' => 0,
            'is_archived' => false,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => ['is_archived' => true]);
    }
}
