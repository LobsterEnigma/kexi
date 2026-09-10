<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademicTaskEntry extends Model
{
    protected $guarded = ['id', 'academic_task_id'];

    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'due_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime', 'reminder_ack_at' => 'immutable_datetime', 'reminder_minutes' => 'integer'];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(AcademicTask::class, 'academic_task_id');
    }
}
