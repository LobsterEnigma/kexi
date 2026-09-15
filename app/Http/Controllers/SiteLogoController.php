<?php

namespace App\Http\Controllers;

use App\Services\SiteLogo;
use App\Services\SiteSettings;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SiteLogoController extends Controller
{
    public function __invoke(string $name, SiteLogo $logo, SiteSettings $settings): BinaryFileResponse
    {
        abort_unless($logo->exists($name) && $name === $settings->get('site_logo'), 404);

        $mime = match (pathinfo($name, PATHINFO_EXTENSION)) {
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'webp' => 'image/webp',
        };

        return response()->file(Storage::disk('local')->path('branding/'.$name), [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=3600',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }
}
