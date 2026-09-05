<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'notes',
        'sort_order',
        'is_archived',
        'type_colors',
    ];

    protected function casts(): array
    {
        return ['is_archived' => 'boolean', 'type_colors' => 'array'];
    }

    public function timetable(): BelongsTo
    {
        return $this->belongsTo(Timetable::class);
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(CourseMeeting::class)->chaperone()->orderBy('sort_order')->orderBy('id');
    }
}
