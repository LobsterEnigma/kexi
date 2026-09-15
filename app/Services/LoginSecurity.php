<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class LoginSecurity
{
    public const KEYS = ['turnstile_enabled', 'turnstile_login', 'turnstile_register', 'turnstile_site_key', 'passkeys_enabled', 'passkeys_name'];

    public function __construct(private readonly SiteSettings $settings) {}

    public function origin(): string
    {
        $url = rtrim(strtolower((string) $this->settings->get('site_url')), '/');
        $parts = parse_url($url);
        if (! $parts || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) || ! empty($parts['path'])
            || ! (($parts['scheme'] ?? '') === 'https' || (($parts['scheme'] ?? '') === 'http' && $parts['host'] === 'localhost' && ! app()->isProduction()))) {
            throw ValidationException::withMessages(['security' => '请先在系统设置中保存正确的 HTTPS 站点网址。']);
        }
        $port = $parts['port'] ?? null;
        $defaultPort = $parts['scheme'] === 'https' ? 443 : 80;

        return $parts['scheme'].'://'.$parts['host'].($port && $port !== $defaultPort ? ':'.$port : '');
    }

    public function passkeyConfig(): array
    {
        if (! $this->settings->bool('passkeys_enabled')) {
            throw ValidationException::withMessages(['passkey' => '管理员尚未开启通行密钥，请使用密码登录。']);
        }
        $origin = $this->origin();
        if (filter_var(parse_url($origin, PHP_URL_HOST), FILTER_VALIDATE_IP)) {
            throw ValidationException::withMessages(['passkey' => '通行密钥需要使用域名，请在系统设置中填写正式域名。']);
        }
        $name = $this->settings->get('passkeys_name') ?: $this->settings->get('site_name');

        return ['origin' => $origin, 'rpId' => parse_url($origin, PHP_URL_HOST), 'name' => $name,
            'revision' => hash('sha256', json_encode([$origin, $name, $this->settings->getMany(self::KEYS), $this->settings->get('turnstile_secret')]))];
    }
}
