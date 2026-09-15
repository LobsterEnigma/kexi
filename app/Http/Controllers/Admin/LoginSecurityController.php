<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminAudit;
use App\Services\LoginSecurity;
use App\Services\SiteSettings;
use App\Services\UserAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LoginSecurityController extends Controller
{
    public function edit(SiteSettings $settings)
    {
        return view('admin.settings.security', ['settings' => $settings->getMany(LoginSecurity::KEYS),
            'siteUrl' => $settings->get('site_url'), 'secretStatus' => $settings->secretStatus('turnstile_secret'), 'revision' => $settings->revision()]);
    }

    public function update(Request $request, SiteSettings $settings, LoginSecurity $security, AdminAudit $audit, UserAccessService $access)
    {
        $data = $request->validate([
            'revision' => ['required', 'string', 'size:64'],
            'turnstile_enabled' => ['required', 'boolean'], 'turnstile_login' => ['required', 'boolean'], 'turnstile_register' => ['required', 'boolean'],
            'turnstile_site_key' => ['nullable', 'string', 'max:255', 'regex:/^[a-zA-Z0-9_-]+$/'],
            'turnstile_secret' => ['nullable', 'string', 'max:2048', 'prohibited_if:clear_turnstile_secret,1'],
            'clear_turnstile_secret' => ['required', 'boolean'],
            'passkeys_enabled' => ['required', 'boolean'], 'passkeys_name' => ['nullable', 'string', 'max:80', 'not_regex:/[\x00-\x1F\x7F]/'],
        ]);
        Cache::lock('admin:site-settings:update', 15)->block(5, function () use ($request, $settings, $security, $audit, $access, $data) {
            DB::transaction(function () use ($request, $settings, $security, $audit, $access, $data) {
                $access->lockAdminActor($request->user());
                if (! hash_equals($settings->revision(), $data['revision'])) {
                    throw ValidationException::withMessages(['revision' => '设置已变更，请刷新页面后重新确认。']);
                }
                $before = $settings->getMany(LoginSecurity::KEYS);
                $values = array_intersect_key($data, array_flip(LoginSecurity::KEYS));
                if ($request->boolean('clear_turnstile_secret')) {
                    $values['turnstile_secret'] = null;
                } elseif (filled($data['turnstile_secret'] ?? null)) {
                    $values['turnstile_secret'] = $data['turnstile_secret'];
                }
                if ($request->boolean('turnstile_enabled')) {
                    $security->origin();
                    if (! filled($values['turnstile_site_key']) || ! filled(array_key_exists('turnstile_secret', $values) ? $values['turnstile_secret'] : $settings->get('turnstile_secret'))) {
                        throw ValidationException::withMessages(['turnstile_site_key' => '开启 Turnstile 前请填写 Site Key 和 Secret Key。']);
                    }
                    if (! $request->boolean('turnstile_login') && ! $request->boolean('turnstile_register')) {
                        throw ValidationException::withMessages(['turnstile_enabled' => '请选择至少一个验证场景：登录或注册。']);
                    }
                }
                if ($request->boolean('passkeys_enabled')) {
                    $security->origin();
                    if (filter_var(parse_url($security->origin(), PHP_URL_HOST), FILTER_VALIDATE_IP)) {
                        throw ValidationException::withMessages(['passkeys_enabled' => '通行密钥需要域名，不能使用 IP 地址作为站点网址。']);
                    }
                }
                $settings->setMany($values);
                DB::table('passkey_challenges')->delete();
                $after = $settings->getMany(LoginSecurity::KEYS);
                $after['turnstile_secret_configured'] = $settings->hasStoredSecret('turnstile_secret');
                $after['turnstile_secret_changed'] = array_key_exists('turnstile_secret', $values);
                $audit->record($request, 'site.login_security_updated', null, $before, $after);
            });
        });

        return back()->with('status', '登录安全设置已保存。');
    }
}
