<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSiteSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin && ! $this->user()->isAccessSuspended();
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'settings_revision' => ['required', 'string', 'size:64'],
            'site_name' => ['required', 'string', 'max:80', 'not_regex:/[\x00-\x1F\x7F]/'],
            'site_url' => [
                'required',
                'url:http,https',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $parts = is_string($value) ? parse_url($value) : false;

                    if ($parts === false
                        || ! isset($parts['host'])
                        || isset($parts['user'])
                        || isset($parts['pass'])
                        || isset($parts['query'])
                        || isset($parts['fragment'])
                        || (isset($parts['path']) && $parts['path'] !== '' && $parts['path'] !== '/')) {
                        $fail('站点网址必须是 http 或 https 站点根地址。');

                        return;
                    }

                    if (app()->isProduction() && strtolower($parts['scheme']) !== 'https') {
                        $fail('生产环境的站点网址必须使用 https。');
                    }
                },
            ],
            'timezone' => ['required', 'timezone'],
            'session_lifetime_minutes' => ['required', 'integer', 'between:15,10080'],
            'registration_enabled' => ['required', 'boolean'],
            'sharing_enabled' => ['required', 'boolean'],
            'mail_mailer' => ['required', Rule::in(['log', 'smtp'])],
            'mail_host' => [
                'nullable',
                'required_if:mail_mailer,smtp',
                'string',
                'max:255',
                'not_regex:/[\x00-\x20\x7F\/@?#]/',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! filled($value) || $value === 'localhost') {
                        return;
                    }

                    $isIp = filter_var($value, FILTER_VALIDATE_IP) !== false;
                    $isHostname = filter_var($value, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;

                    if (! $isIp && ! $isHostname) {
                        $fail('SMTP 主机必须是有效的主机名或 IP 地址。');
                    }
                },
            ],
            'mail_port' => ['nullable', 'required_if:mail_mailer,smtp', 'integer', 'between:1,65535'],
            'mail_scheme' => ['nullable', Rule::in(['smtp', 'smtps'])],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:2048', 'prohibited_if:clear_mail_password,1'],
            'clear_mail_password' => ['required', 'boolean'],
            'mail_from_address' => ['required', 'email:rfc', 'max:255'],
            'mail_from_name' => ['required', 'string', 'max:80', 'not_regex:/[\x00-\x1F\x7F]/'],
        ];
    }
}
