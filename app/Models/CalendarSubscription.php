<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarSubscription extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['token_hash', 'token'];

    protected function casts(): array
    {
        return ['token' => 'encrypted', 'contents' => 'array', 'date_from' => 'immutable_date', 'date_to' => 'immutable_date', 'revoked_at' => 'datetime'];
    }

    public function timetable(): BelongsTo
    {
        return $this->belongsTo(Timetable::class);
    }
}
