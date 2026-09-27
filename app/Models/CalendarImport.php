<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CalendarImport extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['payload' => 'encrypted:array', 'expires_at' => 'immutable_datetime'];
    }
}
