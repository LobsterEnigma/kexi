<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminActionRequest;
use App\Http\Requests\Admin\BanUserRequest;
use App\Http\Requests\Admin\DisableUserSharingRequest;
use App\Http\Requests\Admin\ReviewUserRequest;
use App\Models\User;
use App\Services\AdminAudit;
use App\Services\UserAccessService;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $search = Str::limit(trim((string) $request->query('q')), 100, '');
        $statusFilter = in_array($request->query('status'), ['normal', 'review', 'banned'], true)
            ? (string) $request->query('status')
            : 'all';

        $users = User::query()
            ->withCount('timetables')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->when($statusFilter === 'normal', fn ($query) => $query
                ->whereNull('banned_at')
                ->whereNull('review_started_at'))
            ->when($statusFilter === 'review', fn ($query) => $query->whereNotNull('review_started_at'))
            ->when($statusFilter === 'banned', fn ($query) => $query->whereNotNull('banned_at'))
            ->orderByDesc('is_admin')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.users.index', compact('users', 'search', 'statusFilter'));
    }

    public function ban(
        BanUserRequest $request,
        User $user,
        UserAccessService $access,
        AdminAudit $audit,
    ): RedirectResponse {
        $this->mutateAccess(function () use ($request, $user, $access, $audit): void {
            $result = $access->ban($request->user(), $user, $request->validated('reason'));
            $audit->record(
                $request,
                'user.banned',
                $result['user'],
                $result['before'],
                $result['after'],
            );
        });

        return back()->with('status', '用户已封禁，其会话与现有分享已失效。');
    }

    public function unban(
        AdminActionRequest $request,
        User $user,
        UserAccessService $access,
        AdminAudit $audit,
    ): RedirectResponse {
        $this->mutateAccess(function () use ($request, $user, $access, $audit): void {
            $result = $access->unban($request->user(), $user);
            $audit->record(
                $request,
                'user.unbanned',
                $result['user'],
                $result['before'],
                $result['after'],
            );
        });

        return back()->with('status', '用户已解封，需要重新登录。');
    }

    public function startReview(
        ReviewUserRequest $request,
        User $user,
        UserAccessService $access,
        AdminAudit $audit,
    ): RedirectResponse {
        $this->mutateAccess(function () use ($request, $user, $access, $audit): void {
            $result = $access->startReview($request->user(), $user, $request->validated('reason'));
            $audit->record(
                $request,
                'user.review_started',
                $result['user'],
                $result['before'],
                $result['after'],
            );
        });

        return back()->with('status', '用户已设为审查中，其登录与公开分享已暂停。');
    }

    public function clearReview(
        AdminActionRequest $request,
        User $user,
        UserAccessService $access,
        AdminAudit $audit,
    ): RedirectResponse {
        $this->mutateAccess(function () use ($request, $user, $access, $audit): void {
            $result = $access->clearReview($request->user(), $user);
            $audit->record(
                $request,
                'user.review_cleared',
                $result['user'],
                $result['before'],
                $result['after'],
            );
        });

        return back()->with('status', '用户审查已结束，可以重新登录。');
    }

    public function disableSharing(
        DisableUserSharingRequest $request,
        User $user,
        UserAccessService $access,
        AdminAudit $audit,
    ): RedirectResponse {
        $this->mutateAccess(function () use ($request, $user, $access, $audit): void {
            $result = $access->disableSharing($request->user(), $user, $request->validated('reason'));
            $audit->record(
                $request,
                'user.sharing_disabled',
                $result['user'],
                $result['before'],
                $result['after'],
            );
        });

        return back()->with('status', '该用户的分享功能已停用。');
    }

    public function enableSharing(
        AdminActionRequest $request,
        User $user,
        UserAccessService $access,
        AdminAudit $audit,
    ): RedirectResponse {
        $this->mutateAccess(function () use ($request, $user, $access, $audit): void {
            $result = $access->enableSharing($request->user(), $user);
            $audit->record(
                $request,
                'user.sharing_enabled',
                $result['user'],
                $result['before'],
                $result['after'],
            );
        });

        return back()->with('status', '该用户的分享功能已恢复。');
    }

    private function mutateAccess(Closure $callback): void
    {
        Cache::lock('admin:user-access:mutation', 15)->block(5, function () use ($callback): void {
            DB::transaction($callback, 3);
        });
    }
}
