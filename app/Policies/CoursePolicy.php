<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CoursePolicy
{
    public function update(User $user, Course $course): Response
    {
        return $this->owns($user, $course);
    }

    public function delete(User $user, Course $course): Response
    {
        return $this->owns($user, $course);
    }

    private function owns(User $user, Course $course): Response
    {
        return (int) $course->timetable->user_id === (int) $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
