<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    public function timetables(): HasMany
    {
        return $this->hasMany(Timetable::class);
    }

    public function isBanned(): bool
    {
        return $this->banned_at !== null;
    }

    public function isUnderReview(): bool
    {
        return $this->review_started_at !== null;
    }

    public function isAccessSuspended(): bool
    {
        return $this->isBanned() || $this->isUnderReview();
    }

    public function accessRestrictionMessage(): ?string
    {
        if ($this->isBanned()) {
            return '账户已被封禁，暂时无法登录。原因：'.($this->ban_reason ?: '请联系站点管理员');
        }

        if ($this->isUnderReview()) {
            return '账户正在审查中，暂时无法登录。原因：'.($this->review_reason ?: '请联系站点管理员');
        }

        return null;
    }

    public function canShare(): bool
    {
        return ! $this->isAccessSuspended() && $this->sharing_disabled_at === null;
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'banned_at' => 'datetime',
            'review_started_at' => 'datetime',
            'sharing_disabled_at' => 'datetime',
        ];
    }
}
