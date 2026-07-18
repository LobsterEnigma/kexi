<?php

namespace App\Models;

use App\Enums\WeekMode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseMeeting extends Model
{
    use HasFactory;

    protected $fillable = [
        'label',
        'teacher',
        'weekday',
        'starts_at',
        'ends_at',
        'location',
        'week_mode',
        'start_week',
        'end_week',
        'specific_weeks',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'week_mode' => WeekMode::class,
            'specific_weeks' => 'array',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function cancellations(): HasMany
    {
        return $this->hasMany(CourseMeetingCancellation::class);
    }

    public function occursInWeek(int $week): bool
    {
        if ($week < 1) {
            return false;
        }

        return match ($this->week_mode) {
            WeekMode::All => $week >= $this->start_week && $week <= $this->end_week,
            WeekMode::Odd => $week >= $this->start_week && $week <= $this->end_week && $week % 2 === 1,
            WeekMode::Even => $week >= $this->start_week && $week <= $this->end_week && $week % 2 === 0,
            WeekMode::Specific => in_array($week, $this->specific_weeks ?? [], true),
        };
    }

    public function isCanceledInWeek(int $week): bool
    {
        if ($this->relationLoaded('cancellations')) {
            return $this->cancellations->contains(
                fn (CourseMeetingCancellation $cancellation): bool => $cancellation->week_number === $week,
            );
        }

        return $this->cancellations()->where('week_number', $week)->exists();
    }

    public function startMinute(): int
    {
        return $this->timeToMinute($this->starts_at);
    }

    public function endMinute(): int
    {
        return $this->timeToMinute($this->ends_at);
    }

    private function timeToMinute(string $time): int
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return ($hour * 60) + $minute;
    }
}
