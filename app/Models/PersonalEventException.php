<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonalEventException extends Model
{
    protected $fillable = ['occurrence_date', 'canceled', 'overrides'];

    protected function casts(): array
    {
        return ['canceled' => 'boolean', 'overrides' => 'array'];
    }
}
