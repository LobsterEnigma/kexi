<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Throwable;

class Turnstile
{
    public function __construct(private readonly SiteSettings $settings) {}

    public function enabled(string $action): bool
    {
        return $this->settings->bool('turnstile_enabled') && $this->settings->bool('turnstile_'.$action);
    }

    public function verify(Request $request, string $action): void
    {
        if (! $this->enabled($action)) {
            return;
        }
        $token = $request->input('cf-turnstile-response');
        if (! is_string($token) || $token === '' || strlen($token) > 2048) {
            throw ValidationException::withMessages(['turnstile' => '请先完成下方的人机验证。']);
        }
        $secret = $this->settings->get('turnstile_secret');
        $hostname = parse_url((string) $this->settings->get('site_url'), PHP_URL_HOST);
        if (! $secret || ! $hostname) {
            throw ValidationException::withMessages(['turnstile' => '人机验证配置暂不可用，请联系管理员。']);
        }
        try {
            $response = Http::asForm()->connectTimeout(3)->timeout(8)->withoutRedirecting()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => $secret, 'response' => $token,
            ]);
            $result = $response->json();
        } catch (Throwable) {
            throw ValidationException::withMessages(['turnstile' => '验证服务暂时无法连接，请重新验证后再试。']);
        }
        if (! $response->successful() || ! is_array($result) || ($result['success'] ?? false) !== true
            || ($result['action'] ?? null) !== $action || strtolower((string) ($result['hostname'] ?? '')) !== strtolower($hostname)) {
            throw ValidationException::withMessages(['turnstile' => '人机验证未通过或已过期，请重新验证。']);
        }
    }
}
