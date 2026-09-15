<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Passkey extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['credential_id', 'credential_hash', 'public_key'];

    protected function casts(): array
    {
        return ['sign_count' => 'integer', 'last_used_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
