<?php

namespace App\Http\Middleware;

use App\Services\SiteSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRegistrationEnabled
{
    public function __construct(private readonly SiteSettings $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->settings->bool('registration_enabled')) {
            return response()->view('auth.registration-closed', status: 403);
        }

        return $next($request);
    }
}
