<?php

namespace App\Http\Middleware;

use App\Services\RuntimeSiteConfiguration;
use Closure;
use Illuminate\Database\SQLiteDatabaseDoesNotExistException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class ApplySiteConfiguration
{
    public function __construct(private readonly RuntimeSiteConfiguration $configuration) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            if (Schema::hasTable('site_settings')) {
                $this->configuration->apply();
            }
        } catch (SQLiteDatabaseDoesNotExistException) {
            // Keep the pre-install page reachable until kexi:install creates SQLite.
        }

        return $next($request);
    }
}
