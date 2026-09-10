<?php

namespace App\Models;

use App\Support\CourseColors;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicTask extends Model
{
    public const TYPES = ['assignment' => 'Assignment 作业', 'quiz' => 'Quiz 测验', 'exam' => '考试', 'project' => 'Project 项目', 'reading' => '阅读', 'other' => '其他'];

    public const REMINDERS = [0 => '到时提醒', 15 => '提前 15 分钟', 60 => '提前 1 小时', 1440 => '提前 1 天', 4320 => '提前 3 天', 10080 => '提前 1 周'];

    protected $guarded = ['id', 'timetable_id'];

    protected function casts(): array
    {
        return ['opens_at' => 'immutable_datetime', 'due_at' => 'immutable_datetime', 'starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime', 'reminder_ack_at' => 'immutable_datetime', 'reminder_minutes' => 'integer'];
    }

    public function timetable(): BelongsTo
    {
        return $this->belongsTo(Timetable::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(AcademicTaskEntry::class)->orderBy('due_at')->orderBy('starts_at')->orderBy('id');
    }

    public function color(): string
    {
        // Use the same stable course palette as the timetable; tasks have no teaching type.
        return $this->course_id ? CourseColors::PRESETS[$this->course_id % 6] : '#526174';
    }
}
