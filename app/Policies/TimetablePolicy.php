<?php

namespace App\Policies;

use App\Models\Timetable;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TimetablePolicy
{
    public function view(User $user, Timetable $timetable): Response
    {
        return $this->owns($user, $timetable);
    }

    public function update(User $user, Timetable $timetable): Response
    {
        return $this->owns($user, $timetable);
    }

    public function delete(User $user, Timetable $timetable): Response
    {
        return $this->owns($user, $timetable);
    }

    private function owns(User $user, Timetable $timetable): Response
    {
        return (int) $timetable->user_id === (int) $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
