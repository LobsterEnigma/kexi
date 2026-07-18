<?php

namespace App\Http\Controllers;

use App\Models\Share;
use App\Models\Timetable;
use App\Services\CanonicalUrl;
use App\Services\SiteSettings;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ShareController extends Controller
{
    public function store(
        Request $request,
        Timetable $timetable,
        SiteSettings $settings,
        CanonicalUrl $canonicalUrl,
    ): RedirectResponse {
        $this->authorize('update', $timetable);

        if (! $settings->bool('sharing_enabled')) {
            throw ValidationException::withMessages(['share' => '站点分享功能当前已关闭。']);
        }

        if (! $request->user()->canShare()) {
            $reason = $request->user()->sharing_disabled_reason;
            $message = $reason
                ? '你的分享功能已被停用。原因：'.$reason
                : '当前账户暂时不能创建分享链接。';

            throw ValidationException::withMessages(['share' => $message]);
        }

        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:100'],
            'password' => ['nullable', 'string', 'min:6', 'max:100', 'confirmed'],
            'expires_at' => ['nullable', 'date_format:Y-m-d\\TH:i'],
        ]);
        $expiresAt = $this->parseExpiration($data['expires_at'] ?? null, (string) $settings->get('timezone'));

        $token = bin2hex(random_bytes(32));
        $share = $timetable->shares()->create([
            'label' => $data['label'] ?? null,
            'token_hash' => hash('sha256', $token),
            'password_hash' => filled($data['password'] ?? null) ? Hash::make($data['password']) : null,
            'expires_at' => $expiresAt,
        ]);

        return back()
            ->with('status', '分享链接已创建，请立即保存。')
            ->with('new_share_url', $canonicalUrl->route('public-shares.show', ['token' => $token]))
            ->with('new_share_id', $share->id);
    }

    public function destroy(Request $request, Timetable $timetable, Share $share): RedirectResponse
    {
        abort_unless($share->timetable_id === $timetable->id, 404);
        $this->authorize('delete', $share);
        $share->update([
            'revoked_at' => now(),
            'access_version' => $share->access_version + 1,
        ]);

        return back()->with('status', '分享链接已撤销。');
    }

    private function parseExpiration(?string $value, string $timezone): ?CarbonImmutable
    {
        if (! filled($value)) {
            return null;
        }

        $expiresAt = CarbonImmutable::createFromFormat('Y-m-d\\TH:i', $value, $timezone);

        if ($expiresAt->format('Y-m-d\\TH:i') !== $value) {
            throw ValidationException::withMessages(['expires_at' => '失效时间在所选时区中无效。']);
        }

        $now = CarbonImmutable::now('UTC');

        if (! $expiresAt->isAfter($now)) {
            throw ValidationException::withMessages(['expires_at' => '失效时间必须晚于当前时间。']);
        }

        if ($expiresAt->isAfter($now->addYear())) {
            throw ValidationException::withMessages(['expires_at' => '失效时间最长只能设置为一年。']);
        }

        return $expiresAt->utc();
    }
}
