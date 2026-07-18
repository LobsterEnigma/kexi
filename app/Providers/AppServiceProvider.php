<?php

namespace App\Providers;

use App\Services\CanonicalUrl;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::createUrlUsing(fn ($notifiable, string $token): string => app(CanonicalUrl::class)->route(
            'password.reset',
            ['token' => $token, 'email' => $notifiable->getEmailForPasswordReset()],
        ));
        VerifyEmail::createUrlUsing(fn ($notifiable): string => app(CanonicalUrl::class)->temporarySignedRoute(
            'verification.verify',
            now()->addMinutes((int) config('auth.verification.expire', 60)),
            ['id' => $notifiable->getKey(), 'hash' => sha1($notifiable->getEmailForVerification())],
        ));

        RateLimiter::for('registration', fn (Request $request) => [
            Limit::perMinute(3)->by('minute:'.$request->ip()),
            Limit::perHour(10)->by('hour:'.$request->ip()),
        ]);
        RateLimiter::for('share-lookup', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
        RateLimiter::for('share-password', fn (Request $request) => Limit::perMinute(5)->by(
            $request->ip().'|'.hash('sha256', (string) $request->route('token')),
        ));
        RateLimiter::for('share-create', fn (Request $request) => Limit::perHour(20)->by(
            (string) ($request->user()?->id ?? $request->ip()),
        ));
        RateLimiter::for('admin-mutation', fn (Request $request) => Limit::perMinute(60)->by(
            (string) ($request->user()?->id ?? $request->ip()),
        ));
    }
}
