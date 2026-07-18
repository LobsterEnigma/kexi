<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Share;
use App\Models\Timetable;
use App\Models\User;
use App\Services\SiteSettings;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(SiteSettings $settings): View
    {
        $sharingEnabled = $settings->bool('sharing_enabled');

        return view('admin.dashboard', [
            'stats' => [
                'users' => User::query()->count(),
                'banned_users' => User::query()->whereNotNull('banned_at')->count(),
                'review_users' => User::query()->whereNotNull('review_started_at')->count(),
                'timetables' => Timetable::query()->count(),
                'active_shares' => $sharingEnabled
                    ? Share::query()
                        ->whereNull('revoked_at')
                        ->whereNull('disabled_by_admin_at')
                        ->whereHas('timetable.user', fn ($query) => $query
                            ->whereNull('banned_at')
                            ->whereNull('review_started_at')
                            ->whereNull('sharing_disabled_at'))
                        ->where(fn ($query) => $query
                            ->whereNull('expires_at')
                            ->orWhere('expires_at', '>', now()))
                        ->count()
                    : 0,
            ],
            'registrationEnabled' => $settings->bool('registration_enabled'),
            'sharingEnabled' => $sharingEnabled,
            'latestAudits' => AdminAuditLog::query()
                ->with('actor')
                ->latest('id')
                ->limit(8)
                ->get(),
        ]);
    }
}
