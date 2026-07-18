<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        $sessionVersion = $request->session()->get('auth_version');
        $restrictionMessage = $user->accessRestrictionMessage();
        if ($restrictionMessage !== null || ($sessionVersion !== null && (int) $sessionVersion !== (int) $user->auth_version)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => $restrictionMessage ?? '当前登录会话已失效，请重新登录。',
            ]);
        }

        if ($sessionVersion === null) {
            $request->session()->put('auth_version', $user->auth_version);
        }

        return $next($request);
    }
}
