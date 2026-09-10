<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;

class Timetable extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'term_name',
        'term_start_date',
        'term_end_date',
        'week_count',
        'timezone',
        'near_threshold_minutes',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'term_start_date' => 'date',
            'term_end_date' => 'date',
            'is_default' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class)->chaperone()->orderBy('sort_order')->orderBy('id');
    }

    public function meetings(): HasManyThrough
    {
        return $this->hasManyThrough(CourseMeeting::class, Course::class);
    }

    public function shares(): HasMany
    {
        return $this->hasMany(Share::class);
    }

    public function academicTasks(): HasMany
    {
        return $this->hasMany(AcademicTask::class);
    }

    public function currentWeek(): int
    {
        if (! $this->term_start_date) {
            return 1;
        }

        $days = $this->weekStartDate()->startOfDay()
            ->diffInDays(now($this->timezone)->startOfDay(), false);
        $week = (int) floor($days / 7) + 1;

        return min(max($week, 1), $this->week_count);
    }

    public function resolvedTermEndDate(): ?Carbon
    {
        if ($this->term_end_date) {
            return $this->term_end_date->copy();
        }

        return $this->weekStartDate()?->addDays(($this->week_count * 7) - 1);
    }

    public function weekStartDate(int $week = 1): ?Carbon
    {
        return $this->term_start_date
            ? Carbon::parse($this->term_start_date->toDateString(), $this->timezone)->startOfWeek(Carbon::MONDAY)->addWeeks($week - 1)
            : null;
    }

    public function occurrenceDate(int $week, int $weekday): ?Carbon
    {
        return $this->weekStartDate($week)?->addDays($weekday - 1);
    }
}
