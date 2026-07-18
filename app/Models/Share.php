<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Share extends Model
{
    use HasFactory;

    protected $fillable = [
        'label',
        'token_hash',
        'password_hash',
        'expires_at',
        'revoked_at',
        'disabled_by_admin_at',
        'disabled_reason',
        'access_version',
    ];

    protected $hidden = ['token_hash', 'password_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'disabled_by_admin_at' => 'datetime',
            'last_viewed_at' => 'datetime',
        ];
    }

    public function timetable(): BelongsTo
    {
        return $this->belongsTo(Timetable::class);
    }

    public function hasPassword(): bool
    {
        return filled($this->password_hash);
    }
}
