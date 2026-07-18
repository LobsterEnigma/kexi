<?php

namespace App\Policies;

use App\Models\Share;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SharePolicy
{
    public function delete(User $user, Share $share): Response
    {
        return (int) $share->timetable->user_id === (int) $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
