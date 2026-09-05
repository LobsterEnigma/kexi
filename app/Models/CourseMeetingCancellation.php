<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseMeetingCancellation extends Model
{
    public const REASONS = [
        'holiday' => '节假日',
        'illness' => '身体不适',
        'personal' => '个人请假',
        'teacher' => '教师停课',
        'emergency' => '突发事件',
        'other' => '其他',
    ];

    protected $fillable = ['week_number', 'reason', 'note'];

    protected function casts(): array
    {
        return ['week_number' => 'integer'];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(CourseMeeting::class, 'course_meeting_id');
    }
}
