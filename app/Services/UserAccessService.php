<?php

namespace App\Services;

use App\Models\Share;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserAccessService
{
    public function lockAdminActor(User $actor): User
    {
        $lockedActor = User::query()->lockForUpdate()->findOrFail($actor->id);

        if (! $lockedActor->is_admin || $lockedActor->isAccessSuspended()) {
            throw new AuthorizationException('仅状态正常的管理员可执行此操作。');
        }

        return $lockedActor;
    }

    /**
     * @return array{user: User, before: array<string, mixed>, after: array<string, mixed>}
     */
    public function ban(User $actor, User $target, string $reason): array
    {
        return DB::transaction(function () use ($actor, $target, $reason): array {
            [$lockedActor, $lockedTarget, $activeAdminIds] = $this->lockActors($actor, $target);

            if ($lockedActor->is($lockedTarget)) {
                throw ValidationException::withMessages([
                    'user' => '不能封禁当前登录的管理员。',
                ]);
            }

            if ($lockedTarget->isBanned()) {
                throw ValidationException::withMessages([
                    'user' => '该用户已被封禁。',
                ]);
            }

            if ($lockedTarget->is_admin
                && $activeAdminIds->contains($lockedTarget->id)
                && $activeAdminIds->count() <= 1) {
                throw ValidationException::withMessages([
                    'user' => '系统至少必须保留一名状态正常的管理员。',
                ]);
            }

            $before = $this->snapshot($lockedTarget);
            $now = now();

            $lockedTarget->forceFill([
                'banned_at' => $now,
                'ban_reason' => $reason,
                'review_started_at' => null,
                'review_reason' => null,
                'auth_version' => $lockedTarget->auth_version + 1,
                'remember_token' => Str::random(60),
            ])->save();

            DB::table('sessions')->where('user_id', $lockedTarget->id)->delete();

            Share::query()
                ->whereHas('timetable', fn ($query) => $query->where('user_id', $lockedTarget->id))
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => $now,
                    'access_version' => DB::raw('access_version + 1'),
                    'updated_at' => $now,
                ]);

            $lockedTarget->refresh();

            return $this->result($lockedTarget, $before);
        }, 3);
    }

    /**
     * @return array{user: User, before: array<string, mixed>, after: array<string, mixed>}
     */
    public function unban(User $actor, User $target): array
    {
        return DB::transaction(function () use ($actor, $target): array {
            [, $lockedTarget] = $this->lockActors($actor, $target);

            if (! $lockedTarget->isBanned()) {
                throw ValidationException::withMessages([
                    'user' => '该用户未被封禁。',
                ]);
            }

            $before = $this->snapshot($lockedTarget);

            $lockedTarget->forceFill([
                'banned_at' => null,
                'ban_reason' => null,
                'auth_version' => $lockedTarget->auth_version + 1,
                'remember_token' => Str::random(60),
            ])->save();

            DB::table('sessions')->where('user_id', $lockedTarget->id)->delete();
            $lockedTarget->refresh();

            return $this->result($lockedTarget, $before);
        }, 3);
    }

    /**
     * @return array{user: User, before: array<string, mixed>, after: array<string, mixed>}
     */
    public function startReview(User $actor, User $target, string $reason): array
    {
        return DB::transaction(function () use ($actor, $target, $reason): array {
            [$lockedActor, $lockedTarget, $activeAdminIds] = $this->lockActors($actor, $target);

            if ($lockedActor->is($lockedTarget)) {
                throw ValidationException::withMessages([
                    'user' => '不能审查当前登录的管理员。',
                ]);
            }

            if ($lockedTarget->isBanned()) {
                throw ValidationException::withMessages([
                    'user' => '该用户已被封禁，无需重复设为审查中。',
                ]);
            }

            if ($lockedTarget->isUnderReview()) {
                throw ValidationException::withMessages([
                    'user' => '该用户已处于审查中。',
                ]);
            }

            if ($lockedTarget->is_admin
                && $activeAdminIds->contains($lockedTarget->id)
                && $activeAdminIds->count() <= 1) {
                throw ValidationException::withMessages([
                    'user' => '系统至少必须保留一名状态正常的管理员。',
                ]);
            }

            $before = $this->snapshot($lockedTarget);
            $now = now();

            $lockedTarget->forceFill([
                'review_started_at' => $now,
                'review_reason' => $reason,
                'auth_version' => $lockedTarget->auth_version + 1,
                'remember_token' => Str::random(60),
            ])->save();

            DB::table('sessions')->where('user_id', $lockedTarget->id)->delete();
            $this->invalidateShareUnlocks($lockedTarget, $now);
            $lockedTarget->refresh();

            return $this->result($lockedTarget, $before);
        }, 3);
    }

    /**
     * @return array{user: User, before: array<string, mixed>, after: array<string, mixed>}
     */
    public function clearReview(User $actor, User $target): array
    {
        return DB::transaction(function () use ($actor, $target): array {
            [, $lockedTarget] = $this->lockActors($actor, $target);

            if (! $lockedTarget->isUnderReview()) {
                throw ValidationException::withMessages([
                    'user' => '该用户当前不在审查中。',
                ]);
            }

            $before = $this->snapshot($lockedTarget);
            $now = now();

            $lockedTarget->forceFill([
                'review_started_at' => null,
                'review_reason' => null,
                'auth_version' => $lockedTarget->auth_version + 1,
                'remember_token' => Str::random(60),
            ])->save();

            DB::table('sessions')->where('user_id', $lockedTarget->id)->delete();
            $this->invalidateShareUnlocks($lockedTarget, $now);
            $lockedTarget->refresh();

            return $this->result($lockedTarget, $before);
        }, 3);
    }

    /**
     * @return array{user: User, before: array<string, mixed>, after: array<string, mixed>}
     */
    public function disableSharing(User $actor, User $target, string $reason): array
    {
        return DB::transaction(function () use ($actor, $target, $reason): array {
            [, $lockedTarget] = $this->lockActors($actor, $target);

            if ($lockedTarget->sharing_disabled_at !== null) {
                throw ValidationException::withMessages([
                    'user' => '该用户的分享功能已停用。',
                ]);
            }

            $before = $this->snapshot($lockedTarget);
            $now = now();

            $lockedTarget->forceFill([
                'sharing_disabled_at' => $now,
                'sharing_disabled_reason' => $reason,
                'sharing_disabled_source' => 'admin',
            ])->save();

            $this->invalidateShareUnlocks($lockedTarget, $now);
            $lockedTarget->refresh();

            return $this->result($lockedTarget, $before);
        }, 3);
    }

    /**
     * @return array{user: User, before: array<string, mixed>, after: array<string, mixed>}
     */
    public function enableSharing(User $actor, User $target): array
    {
        return DB::transaction(function () use ($actor, $target): array {
            [, $lockedTarget] = $this->lockActors($actor, $target);

            if ($lockedTarget->sharing_disabled_at === null) {
                throw ValidationException::withMessages([
                    'user' => '该用户的分享功能未停用。',
                ]);
            }

            if ($lockedTarget->sharing_disabled_source !== 'admin') {
                throw ValidationException::withMessages([
                    'user' => '用户自行停用的分享功能只能由用户本人恢复。',
                ]);
            }

            $before = $this->snapshot($lockedTarget);
            $now = now();

            $lockedTarget->forceFill([
                'sharing_disabled_at' => null,
                'sharing_disabled_reason' => null,
                'sharing_disabled_source' => null,
            ])->save();

            $this->invalidateShareUnlocks($lockedTarget, $now);
            $lockedTarget->refresh();

            return $this->result($lockedTarget, $before);
        }, 3);
    }

    /**
     * Lock every active administrator in a stable order so two concurrent
     * access changes cannot both remove the final active administrator.
     *
     * @return array{User, User, Collection<int, int>}
     */
    private function lockActors(User $actor, User $target): array
    {
        $activeAdminIds = User::query()
            ->where('is_admin', true)
            ->whereNull('banned_at')
            ->whereNull('review_started_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->pluck('id');

        $lockedActor = $this->lockAdminActor($actor);
        $lockedTarget = $lockedActor->is($target)
            ? $lockedActor
            : User::query()->lockForUpdate()->findOrFail($target->id);

        return [$lockedActor, $lockedTarget, $activeAdminIds];
    }

    private function invalidateShareUnlocks(User $user, mixed $timestamp): void
    {
        Share::query()
            ->whereHas('timetable', fn ($query) => $query->where('user_id', $user->id))
            ->whereNull('revoked_at')
            ->update([
                'access_version' => DB::raw('access_version + 1'),
                'updated_at' => $timestamp,
            ]);
    }

    /** @return array<string, mixed> */
    private function snapshot(User $user): array
    {
        return [
            'is_admin' => $user->is_admin,
            'banned_at' => $user->banned_at?->toIso8601String(),
            'ban_reason' => $user->ban_reason,
            'review_started_at' => $user->review_started_at?->toIso8601String(),
            'review_reason' => $user->review_reason,
            'sharing_disabled_at' => $user->sharing_disabled_at?->toIso8601String(),
            'sharing_disabled_reason' => $user->sharing_disabled_reason,
            'sharing_disabled_source' => $user->sharing_disabled_source,
            'auth_version' => $user->auth_version,
        ];
    }

    /**
     * @param  array<string, mixed>  $before
     * @return array{user: User, before: array<string, mixed>, after: array<string, mixed>}
     */
    private function result(User $user, array $before): array
    {
        return [
            'user' => $user,
            'before' => $before,
            'after' => $this->snapshot($user),
        ];
    }
}
