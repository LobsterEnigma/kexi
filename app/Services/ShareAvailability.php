<?php

namespace App\Services;

use App\Models\Share;

class ShareAvailability
{
    public function __construct(private readonly SiteSettings $settings) {}

    public function isAvailable(Share $share): bool
    {
        $share->loadMissing('timetable.user');
        $owner = $share->timetable->user;

        return $this->settings->bool('sharing_enabled')
            && $owner->canShare()
            && $share->revoked_at === null
            && $share->disabled_by_admin_at === null
            && ($share->expires_at === null || $share->expires_at->isFuture());
    }
}
