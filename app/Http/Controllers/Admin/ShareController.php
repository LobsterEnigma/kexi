<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminActionRequest;
use App\Http\Requests\Admin\DisableShareRequest;
use App\Models\Share;
use App\Services\AdminAudit;
use App\Services\SiteSettings;
use App\Services\UserAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ShareController extends Controller
{
    public function index(Request $request, SiteSettings $settings): View
    {
        $search = Str::limit(trim((string) $request->query('q')), 100, '');

        $shares = Share::query()
            ->with(['timetable.user'])
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('label', 'like', "%{$search}%")
                    ->orWhereHas('timetable', fn ($query) => $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($query) => $query
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")));
            }))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.shares.index', [
            'shares' => $shares,
            'search' => $search,
            'sharingEnabled' => $settings->bool('sharing_enabled'),
        ]);
    }

    public function disable(
        DisableShareRequest $request,
        Share $share,
        AdminAudit $audit,
        UserAccessService $access,
    ): RedirectResponse {
        DB::transaction(function () use ($request, $share, $audit, $access): void {
            $access->lockAdminActor($request->user());
            $locked = Share::query()->lockForUpdate()->findOrFail($share->id);
            $this->assertMutable($locked);

            if ($locked->disabled_by_admin_at !== null) {
                throw ValidationException::withMessages(['share' => '该分享已暂停。']);
            }

            $before = $this->snapshot($locked);
            $locked->forceFill([
                'disabled_by_admin_at' => now(),
                'disabled_reason' => $request->validated('reason'),
                'access_version' => $locked->access_version + 1,
            ])->save();

            $audit->record($request, 'share.disabled', $locked, $before, $this->snapshot($locked));
        }, 3);

        return back()->with('status', '分享链接已暂停。');
    }

    public function enable(
        AdminActionRequest $request,
        Share $share,
        AdminAudit $audit,
        UserAccessService $access,
    ): RedirectResponse {
        DB::transaction(function () use ($request, $share, $audit, $access): void {
            $access->lockAdminActor($request->user());
            $locked = Share::query()->lockForUpdate()->findOrFail($share->id);
            $this->assertMutable($locked);

            if ($locked->disabled_by_admin_at === null) {
                throw ValidationException::withMessages(['share' => '该分享未暂停。']);
            }

            $before = $this->snapshot($locked);
            $locked->forceFill([
                'disabled_by_admin_at' => null,
                'disabled_reason' => null,
                'access_version' => $locked->access_version + 1,
            ])->save();

            $audit->record($request, 'share.enabled', $locked, $before, $this->snapshot($locked));
        }, 3);

        return back()->with('status', '分享链接已恢复。');
    }

    public function revoke(
        AdminActionRequest $request,
        Share $share,
        AdminAudit $audit,
        UserAccessService $access,
    ): RedirectResponse {
        DB::transaction(function () use ($request, $share, $audit, $access): void {
            $access->lockAdminActor($request->user());
            $locked = Share::query()->lockForUpdate()->findOrFail($share->id);
            $this->assertMutable($locked);

            $before = $this->snapshot($locked);
            $locked->forceFill([
                'revoked_at' => now(),
                'access_version' => $locked->access_version + 1,
            ])->save();

            $audit->record($request, 'share.revoked', $locked, $before, $this->snapshot($locked));
        }, 3);

        return back()->with('status', '分享链接已永久撤销。');
    }

    private function assertMutable(Share $share): void
    {
        if ($share->revoked_at !== null) {
            throw ValidationException::withMessages(['share' => '该分享已永久撤销。']);
        }
    }

    /** @return array<string, mixed> */
    private function snapshot(Share $share): array
    {
        return [
            'timetable_id' => $share->timetable_id,
            'label' => $share->label,
            'expires_at' => $share->expires_at?->toIso8601String(),
            'revoked_at' => $share->revoked_at?->toIso8601String(),
            'disabled_by_admin_at' => $share->disabled_by_admin_at?->toIso8601String(),
            'disabled_reason' => $share->disabled_reason,
            'access_version' => $share->access_version,
        ];
    }
}
