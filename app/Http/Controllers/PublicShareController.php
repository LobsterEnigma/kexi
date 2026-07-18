<?php

namespace App\Http\Controllers;

use App\Models\Share;
use App\Services\ScheduleAnalyzer;
use App\Services\ShareAvailability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PublicShareController extends Controller
{
    public function show(
        Request $request,
        string $token,
        ShareAvailability $availability,
        ScheduleAnalyzer $analyzer,
    ): Response {
        $share = $this->resolveShare($token);
        abort_unless($availability->isAvailable($share), 404);

        if ($share->hasPassword() && ! $this->isUnlocked($request, $share)) {
            return $this->secureResponse(view('shares.unlock', compact('share', 'token')));
        }

        $timetable = $share->timetable;
        $requestedWeek = $request->query('week');
        $week = min(max(
            $requestedWeek === null ? $timetable->currentWeek() : $request->integer('week'),
            1,
        ), $timetable->week_count);
        $analysis = $analyzer->forWeek($timetable, $week);

        $share->increment('views_count');
        $share->forceFill(['last_viewed_at' => now()])->save();

        return $this->secureResponse(view('shares.show', compact(
            'share',
            'token',
            'timetable',
            'week',
            'analysis',
        )));
    }

    public function unlock(
        Request $request,
        string $token,
        ShareAvailability $availability,
    ): RedirectResponse {
        $share = $this->resolveShare($token);
        abort_unless($availability->isAvailable($share), 404);
        $data = $request->validate(['password' => ['required', 'string', 'max:100']]);

        if (! Hash::check($data['password'], (string) $share->password_hash)) {
            throw ValidationException::withMessages(['password' => '密码不正确，请重试。']);
        }

        $request->session()->put("share_unlock.{$share->id}", [
            'access_version' => $share->access_version,
            'expires_at' => now()->addHours(12)->timestamp,
        ]);

        return redirect()->route('public-shares.show', $token);
    }

    private function resolveShare(string $token): Share
    {
        abort_unless((bool) preg_match('/\A[a-f0-9]{64}\z/', $token), 404);

        return Share::query()
            ->with('timetable.user', 'timetable.courses.meetings')
            ->where('token_hash', hash('sha256', $token))
            ->firstOrFail();
    }

    private function isUnlocked(Request $request, Share $share): bool
    {
        $unlock = $request->session()->get("share_unlock.{$share->id}");

        return is_array($unlock)
            && ($unlock['access_version'] ?? null) === $share->access_version
            && ($unlock['expires_at'] ?? 0) > now()->timestamp;
    }

    private function secureResponse(mixed $content): Response
    {
        return response($content)->withHeaders([
            'Cache-Control' => 'private, no-store, max-age=0',
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ]);
    }
}
