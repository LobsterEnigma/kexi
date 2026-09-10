<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PersonalEvent extends Model
{
    protected $fillable = ['title', 'category', 'color', 'timezone', 'starts_at', 'ends_at', 'all_day', 'location', 'notes', 'repeat', 'weekdays', 'repeat_until'];

    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'all_day' => 'boolean', 'weekdays' => 'array', 'repeat_until' => 'immutable_date'];
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(PersonalEventException::class);
    }
}
