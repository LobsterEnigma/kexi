<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseMeetingCancellation extends Model
{
    protected $fillable = ['week_number'];

    protected function casts(): array
    {
        return ['week_number' => 'integer'];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(CourseMeeting::class, 'course_meeting_id');
    }
}
