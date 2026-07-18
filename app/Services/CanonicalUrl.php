<?php

namespace App\Services;

use DateTimeInterface;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Uri;
use InvalidArgumentException;
use Throwable;

class CanonicalUrl
{
    public function __construct(private readonly SiteSettings $settings) {}

    /** @param array<string, mixed> $parameters */
    public function route(string $name, array $parameters = []): string
    {
        $path = route($name, $parameters, false);

        return $this->origin().'/'.ltrim($path, '/');
    }

    /** @param array<string, mixed> $parameters */
    public function temporarySignedRoute(string $name, DateTimeInterface $expiration, array $parameters = []): string
    {
        if (array_key_exists('expires', $parameters) || array_key_exists('signature', $parameters)) {
            throw new InvalidArgumentException('Signed URL parameters may not contain expires or signature.');
        }

        $parameters['expires'] = $expiration->getTimestamp();
        ksort($parameters);
        $url = $this->route($name, $parameters);
        $signature = hash_hmac('sha256', $url, (string) config('app.key'));

        return $url.(str_contains($url, '?') ? '&' : '?').'signature='.$signature;
    }

    private function origin(): string
    {
        $siteUrl = null;

        try {
            if (Schema::hasTable('site_settings')) {
                $siteUrl = $this->settings->get('site_url');
            }
        } catch (Throwable) {
            // Installation and recovery commands may run before the database is available.
        }

        if (! filled($siteUrl) && request()->route() !== null) {
            $siteUrl = request()->root();
        }

        return rtrim((string) Uri::of((string) ($siteUrl ?: config('app.url'))), '/');
    }
}
